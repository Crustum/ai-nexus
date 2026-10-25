<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Browser\Support;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer as AmpHttpServer;
use Amp\Http\Server\HttpServerStatus;
use Amp\Http\Server\Request as AmpRequest;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Cake\Core\Configure;
use Cake\Http\Server as CakeServer;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Crustum\AiNexus\Frontend\Assets;
use Laminas\Diactoros\UploadedFile;
use Pest\Browser\Contracts\HttpServer;
use Pest\Browser\Exceptions\ServerNotFoundException;
use Pest\Browser\Execution;
use Pest\Browser\GlobalState;
use Pest\Browser\Playwright\Playwright;
use Psr\Log\NullLogger;
use TestApp\Application;
use Throwable;

/**
 * In-process HTTP driver for browser tests.
 *
 * Boots the TestApp through `Cake\Http\Server` inside the same PHP process,
 * so the sqlite `:memory:` database and faked agents are shared between the
 * test and the browser. Static frontend files are served from the build
 * directory; every other path is dispatched through the Cake stack with the
 * response body captured out of the output buffer.
 */
final class CakeHttpServer implements HttpServer
{
    /**
     * The underlying socket server instance, if any.
     */
    private ?AmpHttpServer $socket = null;

    /**
     * The last throwable that occurred during the server's execution.
     */
    private ?Throwable $lastThrowable = null;

    /**
     * Temp files backing the current request's parsed uploads.
     *
     * @var array<int, string>
     */
    private array $pendingTempFiles = [];

    /**
     * Creates a new CakePHP HTTP server instance.
     *
     * @param string $host Host interface to bind.
     * @param int $port Port to listen on.
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {
    }

    /**
     * Rewrite the given URL to match the server's host and port.
     *
     * @param string $url URL to rewrite.
     * @return string
     */
    public function rewrite(string $url): string
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = '/' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return sprintf('http://%s:%d%s%s', $this->host, $this->port, $path, $query);
    }

    /**
     * Start the server and listen for incoming connections.
     *
     * @return void
     */
    public function start(): void
    {
        if ($this->socket instanceof AmpHttpServer) {
            return;
        }

        $this->socket = SocketHttpServer::createForDirectAccess(new NullLogger());
        $server = $this->socket;

        $server->expose("{$this->host}:{$this->port}");
        $server->start(
            new ClosureRequestHandler($this->handleRequest(...)),
            new DefaultErrorHandler(),
        );
    }

    /**
     * Stop the server and close all connections.
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->socket instanceof AmpHttpServer) {
            $this->flush();

            if ($this->socket instanceof AmpHttpServer) {
                if (in_array($this->socket->getStatus(), [HttpServerStatus::Starting, HttpServerStatus::Started], true)) {
                    $this->socket->stop();
                }

                $this->socket = null;
            }
        }
    }

    /**
     * Flush pending requests and close all connections.
     *
     * @return void
     */
    public function flush(): void
    {
        if (!$this->socket instanceof AmpHttpServer) {
            return;
        }

        Execution::instance()->tick();

        $this->lastThrowable = null;
    }

    /**
     * Bootstrap the server and set the application URL.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        $this->start();

        Configure::write('App.fullBaseUrl', $this->url());
    }

    /**
     * Get the last throwable that occurred during the server's execution.
     *
     * @return \Throwable|null
     */
    public function lastThrowable(): ?Throwable
    {
        return $this->lastThrowable;
    }

    /**
     * Throws the last throwable if it should be thrown.
     *
     * @return void
     * @throws \Throwable
     */
    public function throwLastThrowableIfNeeded(): void
    {
        if (!$this->lastThrowable instanceof Throwable) {
            return;
        }

        throw $this->lastThrowable;
    }

    /**
     * Get the public path for the given path.
     *
     * @return string
     */
    private function url(): string
    {
        if (!$this->socket instanceof AmpHttpServer) {
            throw new ServerNotFoundException('The HTTP server is not running.');
        }

        return sprintf('http://%s:%d', $this->host, $this->port);
    }

    /**
     * Handle the incoming request and return a response.
     *
     * @param \Amp\Http\Server\Request $request Incoming request.
     * @return \Amp\Http\Server\Response
     */
    private function handleRequest(AmpRequest $request): Response
    {
        GlobalState::flush();

        if (Execution::instance()->isWaiting() === false) {
            Execution::instance()->tick();
        }

        $uri = $request->getUri();
        $path = $uri->getPath();
        $path = in_array($path, ['', '0'], true) ? '/' : $path;

        $query = $uri->getQuery();
        $fullPath = $path . ($query !== '' ? '?' . $query : '');

        $static = $this->staticResponse($path);

        if ($static instanceof Response) {
            return $static;
        }

        try {
            return $this->dispatch($request, $fullPath, $query);
        } catch (Throwable $throwable) {
            $this->lastThrowable = $throwable;

            throw $throwable;
        }
    }

    /**
     * Serve a static frontend file, if the path matches the build directory.
     *
     * @param string $path Request path.
     * @return \Amp\Http\Server\Response|null Null when the path is not static.
     */
    private function staticResponse(string $path): ?Response
    {
        if ($path === '/favicon.ico') {
            return new Response(404);
        }

        $prefix = '/' . Assets::urlPath() . '/';

        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $file = Assets::diskPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($path, strlen($prefix)));

        if (!is_file($file)) {
            return new Response(404);
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return new Response(404);
        }

        return new Response(200, [
            'Content-Type' => $this->mimeType($file),
        ], $content);
    }

    /**
     * Dispatch a request through the Cake stack in-process.
     *
     * @param \Amp\Http\Server\Request $request Incoming request.
     * @param string $fullPath Path with query string.
     * @param string $query Raw query string.
     * @return \Amp\Http\Server\Response
     */
    private function dispatch(AmpRequest $request, string $fullPath, string $query): Response
    {
        Router::reload();

        $method = strtoupper($request->getMethod());
        $rawBody = (string)$request->getBody();
        $headers = $request->getHeaders();
        $contentType = $this->headerValue($headers, 'content-type');

        $cookies = [];

        foreach ($request->getCookies() as $name => $cookie) {
            $cookies[$name] = urldecode($cookie->getValue());
        }

        /** @var array<string, mixed> $queryParams */
        $queryParams = [];
        parse_str($query, $queryParams);

        /** @var array<string, mixed> $post */
        $post = [];
        /** @var array<string, mixed> $files */
        $files = [];

        if ($method !== 'GET' && str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            parse_str($rawBody, $post);
        } elseif ($method !== 'GET' && str_starts_with(strtolower($contentType), 'multipart/form-data')) {
            [$post, $files] = $this->parseMultipart($rawBody, $contentType);
        }

        $environment = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $fullPath,
            'QUERY_STRING' => $query,
            'CONTENT_TYPE' => $contentType,
            'CONTENT_LENGTH' => (string)strlen($rawBody),
            'HTTP_HOST' => sprintf('%s:%d', $this->host, $this->port),
            'SERVER_NAME' => $this->host,
            'SERVER_PORT' => (string)$this->port,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTPS' => 'off',
        ];

        foreach ($headers as $name => $values) {
            $value = implode(', ', $values);
            $lower = strtolower($name);

            if ($lower === 'content-type') {
                $environment['CONTENT_TYPE'] = $value;
            } elseif ($lower === 'content-length') {
                $environment['CONTENT_LENGTH'] = $value;
            } else {
                $environment['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
            }
        }

        $configuredHost = Playwright::host();

        if ($configuredHost !== null) {
            $environment['HTTP_HOST'] = sprintf('%s:%d', $configuredHost, $this->port);
            $environment['SERVER_NAME'] = $configuredHost;
        }

        $this->pendingTempFiles = [];

        $cakeRequest = new ServerRequest([
            'url' => $fullPath,
            'environment' => $environment,
            'query' => $queryParams,
            'cookies' => $cookies,
            'input' => $rawBody,
            'post' => $post,
            'files' => [],
        ]);

        if ($files !== []) {
            $cakeRequest = $cakeRequest->withUploadedFiles($files);
            $parsedBody = $cakeRequest->getParsedBody();

            if (is_array($parsedBody)) {
                $cakeRequest = $cakeRequest->withParsedBody(Hash::merge($parsedBody, $files));
            }
        }

        $server = new CakeServer(new Application(CONFIG));

        ob_start();

        try {
            // The chat stream echoes each part as the turn produces it, so the
            // body is read out of the output buffer exactly like the
            // `sendMessage()` test helper does.
            $response = $server->run($cakeRequest);
            $responseBody = (string)$response->getBody();
        } finally {
            $echoed = (string)ob_get_clean();

            $this->cleanTempFiles();
        }

        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            $echoed . $responseBody,
        );
    }

    /**
     * Remove the temp files backing the current request's parsed uploads.
     *
     * @return void
     */
    private function cleanTempFiles(): void
    {
        foreach ($this->pendingTempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->pendingTempFiles = [];
    }

    /**
     * Read a single header value from the Amp header map.
     *
     * @param array<string, array<int, string>> $headers Header map.
     * @param string $name Header name.
     * @return string
     */
    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $headerName => $values) {
            if (strtolower($headerName) === $name) {
                return implode(', ', $values);
            }
        }

        return '';
    }

    /**
     * Parse a multipart body into fields and uploaded files.
     *
     * @param string $rawBody Raw request body.
     * @param string $contentType Request content type with boundary.
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function parseMultipart(string $rawBody, string $contentType): array
    {
        $post = [];
        $files = [];

        if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/', $contentType, $matches)) {
            return [$post, $files];
        }

        $boundary = trim($matches[1] !== '' ? $matches[1] : $matches[2]);

        if ($boundary === '') {
            return [$post, $files];
        }

        foreach (explode('--' . $boundary, $rawBody) as $chunk) {
            $chunk = trim($chunk, "\r\n");

            if ($chunk === '' || $chunk === '--') {
                continue;
            }

            $separator = strpos($chunk, "\r\n\r\n");

            if ($separator === false) {
                continue;
            }

            $rawHeaders = substr($chunk, 0, $separator);
            $body = substr($chunk, $separator + 4);
            $body = preg_replace('/\r\n$/', '', $body) ?? $body;

            if (!preg_match('/name="([^"]*)"/', $rawHeaders, $nameMatch)) {
                continue;
            }

            $name = $nameMatch[1];

            if (
                preg_match('/filename="([^"]*)"/', $rawHeaders, $fileMatch)
                && $fileMatch[1] !== ''
            ) {
                $mediaType = 'application/octet-stream';

                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $rawHeaders, $typeMatch)) {
                    $mediaType = trim($typeMatch[1]);
                }

                $tempPath = tempnam(sys_get_temp_dir(), 'ainexus');

                if ($tempPath === false) {
                    continue;
                }

                file_put_contents($tempPath, $body);
                $this->pendingTempFiles[] = $tempPath;
                $this->assignField(
                    $files,
                    $name,
                    new UploadedFile($tempPath, strlen($body), UPLOAD_ERR_OK, $fileMatch[1], $mediaType),
                );

                continue;
            }

            $this->assignField($post, $name, $body);
        }

        return [$post, $files];
    }

    /**
     * Assign a parsed multipart value, honoring `name[]` array syntax.
     *
     * @param array<string, mixed> $target Fields or files map.
     * @param string $name Field name.
     * @param mixed $value Field value.
     * @return void
     */
    private function assignField(array &$target, string $name, mixed $value): void
    {
        if (str_ends_with($name, '[]')) {
            $key = substr($name, 0, -2);

            if (!isset($target[$key]) || !is_array($target[$key])) {
                $target[$key] = [];
            }

            $target[$key][] = $value;

            return;
        }

        $target[$name] = $value;
    }

    /**
     * Guess the content type for a static file from its extension.
     *
     * @param string $file Absolute file path.
     * @return string
     */
    private function mimeType(string $file): string
    {
        $types = [
            'js' => 'application/javascript',
            'mjs' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'map' => 'application/json',
            'html' => 'text/html',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];

        return $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}

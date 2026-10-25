<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Cake\Http\Client\Exception\ClientException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use WeakMap;

/**
 * Digs the provider's own explanation out of a failed HTTP call.
 *
 * The client exception message only ever carries the status code; the part
 * that actually says why lives in the response body, which the SDK passes
 * through only on exceptions that still hold a response. The exception chain
 * is followed rather than just the outermost throwable, as the SDK converts
 * some failures into its own exception types with the original attached.
 */
class ProviderErrorDetail
{
    /**
     * Response bodies are small, but a provider echoing back a large request
     * should not bloat every stored error row.
     *
     * @var int
     */
    protected const int MAX_BODY = 8000;

    /**
     * Bodies already read, keyed by the throwable they came from.
     *
     * A failure from a streaming request can carry a non-seekable stream, so
     * the first read may drain it. A WeakMap rather than a plain array: entries
     * disappear with the throwable, so nothing is held past the request.
     *
     * @var \WeakMap<\Throwable, array{status: int, body: string|null}>|null
     */
    protected static ?WeakMap $cache = null;

    /**
     * The status and body of the failed request, walking the exception chain.
     *
     * @param \Throwable|null $e Failure to inspect.
     * @return array{status: int, body: string|null}|null
     */
    public static function for(?Throwable $e): ?array
    {
        if (!$e instanceof Throwable) {
            return null;
        }

        static::$cache ??= new WeakMap();

        if (isset(static::$cache[$e])) {
            return static::$cache[$e];
        }

        $found = static::read($e);

        if ($found !== null) {
            static::$cache[$e] = $found;
        }

        return $found;
    }

    /**
     * Scan the chain for the first throwable holding response detail.
     *
     * @param \Throwable $e Failure to inspect.
     * @return array{status: int, body: string|null}|null
     */
    protected static function read(Throwable $e): ?array
    {
        $current = $e;

        while ($current instanceof Throwable) {
            if ($current instanceof GuzzleRequestException && $current->getResponse() instanceof ResponseInterface) {
                $response = $current->getResponse();

                return [
                    'status' => $response->getStatusCode(),
                    'body' => mb_substr((string)$response->getBody(), 0, self::MAX_BODY),
                ];
            }

            $code = $current instanceof ClientException ? $current->getCode() : null;

            if (is_int($code) && $code > 0) {
                return [
                    'status' => $code,
                    'body' => null,
                ];
            }

            $current = $current->getPrevious();
        }

        return null;
    }
}

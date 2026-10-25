<?php
declare(strict_types=1);

namespace TestApp\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test middleware denying every dashboard request with 403.
 */
class DenyMiddleware implements MiddlewareInterface
{
    /**
     * Deny the request without calling the handler.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Next handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new Response(['status' => 403]);
    }
}

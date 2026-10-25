<?php
declare(strict_types=1);

namespace TestApp\Ai\Middleware;

use Closure;

/**
 * Sample pass-through middleware fixture.
 */
class PiiRedactor
{
    /**
     * Handle the incoming prompt.
     *
     * @param mixed $prompt Incoming prompt.
     * @param \Closure $next Next middleware.
     * @return mixed
     */
    public function handle(mixed $prompt, Closure $next): mixed
    {
        return $next($prompt);
    }
}

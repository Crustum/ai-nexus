<?php
declare(strict_types=1);

namespace TestApp\Ai\Contracts;

/**
 * Dependency the container cannot build in tests.
 */
interface UnresolvableDependency
{
    /**
     * Handle the dependency call.
     *
     * @return string
     */
    public function handle(): string;
}

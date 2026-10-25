<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Cake\Utility\Inflector;
use function Cake\Collection\collection;

/**
 * Converts an agent FQCN into a URL-safe slug.
 *
 * Slugs resolve back to a class only by lookup among the discovered agents,
 * never by transforming a slug into a class name.
 */
class AgentSlug
{
    /**
     * Build the slug for the given agent class.
     *
     * @param string $class Agent FQCN.
     * @return string
     */
    public static function make(string $class): string
    {
        $segments = collection(explode('\\', $class))
            ->map(fn(string $segment): string => Inflector::dasherize($segment))
            ->toList();

        return implode('.', $segments);
    }
}

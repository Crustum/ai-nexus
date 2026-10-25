<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Cake\Core\Configure;
use Composer\Autoload\ClassLoader;
use Crustum\Ai\Contracts\Agent;
use FilesystemIterator;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Finds the agent classes in the host application.
 *
 * Results are cached for the lifetime of the instance. There is deliberately
 * no persistent cache: in development classes change constantly, and a stale
 * dashboard is worse than a rescan.
 */
class AgentDiscovery
{
    /**
     * Discovered agents memoized per instance.
     *
     * @var array<int, \Crustum\AiNexus\Discovery\DiscoveredAgent>|null
     */
    protected ?array $agents = null;

    /**
     * Wire discovery with its metadata reader and agent container.
     *
     * @param \Crustum\AiNexus\Discovery\AgentMetadata $metadata Agent metadata.
     * @param \Psr\Container\ContainerInterface $container Container used to build agents.
     */
    public function __construct(
        protected AgentMetadata $metadata,
        protected ContainerInterface $container,
    ) {
    }

    /**
     * All discovered agents, sorted by name.
     *
     * @return array<int, \Crustum\AiNexus\Discovery\DiscoveredAgent>
     */
    public function all(): array
    {
        if ($this->agents !== null) {
            return $this->agents;
        }

        $metadata = $this->metadata;

        $agents = array_map(
            $metadata->for(...),
            $this->classes(),
        );

        usort($agents, static fn(DiscoveredAgent $a, DiscoveredAgent $b): int => strcmp($a->name, $b->name));

        return $this->agents = $agents;
    }

    /**
     * Resolve a discovered agent by its slug.
     *
     * @param string $slug Agent slug.
     * @return \Crustum\AiNexus\Discovery\DiscoveredAgent|null
     */
    public function find(string $slug): ?DiscoveredAgent
    {
        foreach ($this->all() as $agent) {
            if ($agent->slug === $slug) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Resolve a slug to a live agent instance.
     *
     * @param string $slug Agent slug.
     * @return object|null
     */
    public function resolve(string $slug): ?object
    {
        $agent = $this->find($slug);

        if (!$agent instanceof DiscoveredAgent || !$agent->available) {
            return null;
        }

        $instance = $this->container->get($agent->class);

        return is_object($instance) ? $instance : null;
    }

    /**
     * The agent class names found in the configured paths.
     *
     * @return array<int, class-string>
     */
    protected function classes(): array
    {
        $paths = Configure::read('AiNexus.discovery.paths', []);
        $paths = is_array($paths) ? array_filter($paths, static fn(mixed $path): bool => is_string($path) && is_dir($path)) : [];

        if ($paths === []) {
            return [];
        }

        $ignored = Configure::read('AiNexus.discovery.ignore', []);
        $ignored = is_array($ignored) ? $ignored : [];

        $classes = [];

        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFor($file->getRealPath() ?: $file->getPathname());

                if ($class !== null && !in_array($class, $ignored, true) && $this->isAgent($class)) {
                    $classes[] = $class;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Whether the class is an instantiable agent implementation.
     *
     * @param class-string $class Class name.
     * @return bool
     */
    protected function isAgent(string $class): bool
    {
        $reflection = new ReflectionClass($class);

        return $reflection->isInstantiable() && $reflection->implementsInterface(Agent::class);
    }

    /**
     * Map a file path to its class name using Composer's PSR-4 prefixes, falling
     * back to parsing the file when no prefix matches.
     *
     * @param string $path File path.
     * @return class-string|null
     */
    protected function classFor(string $path): ?string
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                foreach ($directories as $directory) {
                    $directory = realpath($directory);

                    if ($directory === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
                        continue;
                    }

                    $relative = substr($path, strlen($directory) + 1);
                    $class = $prefix . str_replace(['/', '.php'], ['\\', ''], $relative);

                    if (class_exists($class)) {
                        return $class;
                    }
                }
            }
        }

        return $this->classFromSource($path);
    }

    /**
     * Parse the namespace and class out of a file when PSR-4 cannot tell.
     *
     * @param string $path File path.
     * @return class-string|null
     */
    protected function classFromSource(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        preg_match('/^\s*namespace\s+([^;{\s]+)/m', $contents, $namespace);
        preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $contents, $name);

        if (!isset($name[1])) {
            return null;
        }

        $class = isset($namespace[1]) ? $namespace[1] . '\\' . $name[1] : $name[1];

        return class_exists($class) ? $class : null;
    }
}

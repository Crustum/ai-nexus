<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Crustum\Ai\Contracts\HasMiddleware;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Throwable;

/**
 * Builds the full Info-panel payload for a single agent.
 *
 * Every section resolves defensively: a misbehaving tool or middleware list
 * degrades that section rather than blanking the panel.
 */
class AgentDetail
{
    /**
     * Wire the detail with its option resolvers and agent container.
     *
     * @param \Crustum\AiNexus\Discovery\GenerationOptions $generation Generation options.
     * @param \Crustum\AiNexus\Discovery\ToolDetail $tools Tool detail.
     * @param \Crustum\AiNexus\Discovery\ModelOptions $models Model options.
     * @param \Psr\Container\ContainerInterface $container Container used to build agents.
     */
    public function __construct(
        protected GenerationOptions $generation,
        protected ToolDetail $tools,
        protected ModelOptions $models,
        protected ContainerInterface $container,
    ) {
    }

    /**
     * Build the Info-panel payload for the given discovered agent.
     *
     * @param \Crustum\AiNexus\Discovery\DiscoveredAgent $discovered Discovered agent.
     * @return array<string, mixed>
     */
    public function for(DiscoveredAgent $discovered): array
    {
        $payload = $discovered->toArray();

        if (!$discovered->available) {
            return array_merge($payload, $this->emptyDetail());
        }

        try {
            $agent = $this->container->get($discovered->class);
        } catch (Throwable) {
            return array_merge($payload, $this->emptyDetail());
        }

        return array_merge($payload, [
            'instructions' => $this->instructions($agent),
            'generation' => $this->generation->for($agent),
            'provider_options' => $this->generation->providerOptions($agent, $discovered->provider),
            'middleware' => $this->middleware($agent),
            'tools' => $this->tools->all($agent, $discovered->provider),
            'output_schema' => $this->tools->outputSchema($agent),
            'models' => $this->models->for($discovered),
        ]);
    }

    /**
     * Resolve the agent instructions, degrading to null.
     *
     * Only public declarations are called; anything else degrades the
     * section instead of failing the panel.
     *
     * @param object $agent Agent under inspection.
     * @return string|null
     */
    protected function instructions(object $agent): ?string
    {
        if (!method_exists($agent, 'instructions')) {
            return null;
        }

        if (!(new ReflectionMethod($agent, 'instructions'))->isPublic()) {
            return null;
        }

        try {
            return (string)$agent->instructions();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Middleware entries may be class strings or instances.
     *
     * @param object $agent Agent under inspection.
     * @return array<int, string>
     */
    protected function middleware(object $agent): array
    {
        if (!$agent instanceof HasMiddleware) {
            return [];
        }

        try {
            $middleware = $agent->middleware();
        } catch (Throwable) {
            return [];
        }

        $names = [];

        foreach ($middleware as $entry) {
            $names[] = is_object($entry) ? $entry::class : (string)$entry;
        }

        return $names;
    }

    /**
     * Blank detail sections for an unavailable agent.
     *
     * @return array<string, mixed>
     */
    protected function emptyDetail(): array
    {
        return [
            'instructions' => null,
            'generation' => null,
            'provider_options' => [],
            'middleware' => [],
            'tools' => [],
            'output_schema' => null,
            'models' => [],
        ];
    }
}

<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

/**
 * A single agent as the dashboard sees it.
 */
class DiscoveredAgent
{
    /**
     * Describe one agent as the dashboard sees it.
     *
     * @param array<int, array{name: string, type: string}> $tools Tool summaries.
     * @param array<string, bool> $capabilities Capability flags.
     * @param array<int, array{parameter: string, type: string|null, reason: string}> $unresolvable Unresolvable constructor parameters.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $class,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly string $modelTier = 'default',
        public readonly array $tools = [],
        public readonly array $capabilities = [],
        public readonly bool $available = true,
        public readonly ?string $error = null,
        public readonly ?string $errorKind = null,
        public readonly array $unresolvable = [],
    ) {
    }

    /**
     * Build the payload for an agent that could not be instantiated.
     *
     * @param string $class Agent FQCN.
     * @param string $error Human-readable failure.
     * @param string $errorKind Whether the container failed to wire the agent, or something else threw.
     * @param array<int, array{parameter: string, type: string|null, reason: string}> $unresolvable Unresolvable constructor parameters.
     * @return self
     */
    public static function unavailable(
        string $class,
        string $error,
        string $errorKind,
        array $unresolvable = [],
    ): self {
        return new self(
            slug: AgentSlug::make($class),
            name: self::basename($class),
            class: $class,
            available: false,
            error: $error,
            errorKind: $errorKind,
            unresolvable: $unresolvable,
        );
    }

    /**
     * Serialize the agent for dashboard payloads.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'class' => $this->class,
            'provider' => $this->provider,
            'model' => $this->model,
            'model_tier' => $this->modelTier,
            'tools' => $this->tools,
            'capabilities' => $this->capabilities,
            'available' => $this->available,
            'error' => $this->error,
            'error_kind' => $this->errorKind,
            'unresolvable' => $this->unresolvable,
        ];
    }

    /**
     * Short class name without the namespace.
     *
     * @param string $class Agent FQCN.
     * @return string
     */
    protected static function basename(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}

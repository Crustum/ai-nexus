<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\HasStructuredOutput;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Providers\Tools\ProviderTool;
use Crustum\Ai\Tools\AgentTool;
use Crustum\Ai\Tools\McpServerTool;
use Crustum\Ai\Tools\McpTool;
use Crustum\JsonSchema\JsonSchemaTypeFactory;
use Crustum\JsonSchema\Types\ObjectType;
use Crustum\JsonSchema\Types\Type;
use InvalidArgumentException;
use Throwable;

/**
 * Full detail for each of an agent's tools: description and parameters.
 *
 * Tool kind is always decided before any accessor is touched, as provider
 * tools expose neither description nor schema.
 */
class ToolDetail
{
    /**
     * Detail rows for every tool the agent exposes.
     *
     * @param object $agent Agent under inspection.
     * @param string|null $provider Provider name used for provider tool options.
     * @return array<int, array<string, mixed>>
     */
    public function all(object $agent, ?string $provider = null): array
    {
        if (!$agent instanceof HasTools) {
            return [];
        }

        $tools = [];

        foreach ($agent->tools() as $tool) {
            $tools[] = $this->detail($tool, $provider);
        }

        return $tools;
    }

    /**
     * The agent's structured output schema, when it declares one.
     *
     * @param object $agent Agent under inspection.
     * @return array<int, array<string, mixed>>|null
     */
    public function outputSchema(object $agent): ?array
    {
        if (!$agent instanceof HasStructuredOutput) {
            return null;
        }

        try {
            return $this->parameters($agent->schema(new JsonSchemaTypeFactory()));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Detail row for a single tool value.
     *
     * @param mixed $tool Tool value from the agent.
     * @param string|null $provider Provider name used for provider tool options.
     * @return array<string, mixed>
     */
    protected function detail(mixed $tool, ?string $provider): array
    {
        return match (true) {
            $tool instanceof Agent => $this->agentTool($tool),
            $tool instanceof Tool => $this->userTool($tool, 'tool'),
            McpTool::supports($tool) => $this->userTool(new McpTool($tool), 'mcp'),
            McpServerTool::supports($tool) => $this->userTool(new McpServerTool($tool), 'mcp'),
            $tool instanceof ProviderTool => $this->providerTool($tool, $provider),
            default => $this->base(is_object($tool) ? self::basename($tool::class) : 'Unknown', 'unknown'),
        };
    }

    /**
     * Detail row for a user-defined tool.
     *
     * @param \Crustum\Ai\Contracts\Tool $tool Tool under inspection.
     * @param string $type Tool kind label.
     * @return array<string, mixed>
     */
    protected function userTool(Tool $tool, string $type): array
    {
        $detail = $this->base($this->name($tool), $type);

        try {
            $detail['description'] = (string)$tool->description();
        } catch (Throwable $throwable) {
            $detail['schema_error'] = $throwable->getMessage();
        }

        try {
            $detail['parameters'] = $this->parameters($tool->schema(new JsonSchemaTypeFactory()));
        } catch (Throwable $throwable) {
            $detail['schema_error'] = $throwable->getMessage();
        }

        return $detail;
    }

    /**
     * Detail row for a sub-agent exposed as a tool.
     *
     * @param \Crustum\Ai\Contracts\Agent $agent Sub-agent under inspection.
     * @return array<string, mixed>
     */
    protected function agentTool(Agent $agent): array
    {
        $tool = new AgentTool($agent);

        return array_merge($this->base($tool->name(), 'agent'), [
            'description' => (string)$tool->description(),
            'agent_slug' => AgentSlug::make($agent::class),
        ]);
    }

    /**
     * Detail row for a provider-native tool.
     *
     * @param \Crustum\Ai\Providers\Tools\ProviderTool $tool Tool under inspection.
     * @param string|null $provider Provider name used for provider tool options.
     * @return array<string, mixed>
     */
    protected function providerTool(ProviderTool $tool, ?string $provider): array
    {
        $options = [];

        if ($provider !== null) {
            try {
                $options = $tool->providerOptions($provider);
            } catch (Throwable) {
                $options = [];
            }
        }

        return array_merge($this->base(self::basename($tool::class), 'provider_tool'), [
            'provider_options' => $options,
        ]);
    }

    /**
     * Flatten a tool schema into displayable parameter rows.
     *
     * Schemas arrive as property-name to type-instance maps; the object wrapper
     * restores the required list, which per-property serialization strips.
     *
     * @param array<string, mixed> $schema Raw schema map.
     * @return array<int, array<string, mixed>>
     */
    protected function parameters(array $schema): array
    {
        if ($schema === []) {
            return [];
        }

        $properties = [];

        foreach ($schema as $name => $property) {
            if (!$property instanceof Type) {
                throw new InvalidArgumentException('Tool schema properties must be type instances.');
            }

            $properties[(string)$name] = $property;
        }

        $json = (new ObjectType($properties))->toArray();

        $required = $json['required'] ?? [];
        $props = $json['properties'] ?? [];

        if (!is_array($required) || !is_array($props)) {
            throw new InvalidArgumentException('Tool schema could not be flattened.');
        }

        $parameters = [];

        foreach ($props as $name => $property) {
            if (!is_array($property)) {
                continue;
            }

            $type = $property['type'] ?? null;

            $parameters[] = [
                'name' => (string)$name,
                'type' => is_array($type) ? implode(' | ', $type) : (string)($type ?? 'mixed'),
                'description' => $property['description'] ?? null,
                'required' => in_array((string)$name, $required, true),
            ];
        }

        return $parameters;
    }

    /**
     * Display name for a user-defined tool.
     *
     * @param \Crustum\Ai\Contracts\Tool $tool Tool under inspection.
     * @return string
     */
    protected function name(Tool $tool): string
    {
        if (method_exists($tool, 'name')) {
            return $tool->name();
        }

        return self::basename($tool::class);
    }

    /**
     * Blank detail row every kind starts from.
     *
     * @param string $name Tool display name.
     * @param string $type Tool kind label.
     * @return array<string, mixed>
     */
    protected function base(string $name, string $type): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'description' => null,
            'parameters' => [],
            'provider_options' => null,
            'agent_slug' => null,
            'schema_error' => null,
        ];
    }

    /**
     * Short class name without the namespace.
     *
     * @param string $class Class name.
     * @return string
     */
    protected static function basename(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}

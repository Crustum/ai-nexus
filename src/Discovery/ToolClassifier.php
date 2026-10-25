<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Providers\Tools\ProviderTool;
use Crustum\Ai\Tools\AgentTool;
use Crustum\Ai\Tools\McpServerTool;
use Crustum\Ai\Tools\McpTool;
use Crustum\Ai\Tools\ToolNameResolver;

/**
 * Classifies the entries returned by an agent's tools().
 *
 * The list is heterogeneous: user tools, provider-native tools, sub-agents,
 * and MCP references. The match below mirrors the SDK gateway's resolveTool
 * order, so description and schema are never touched on a provider tool.
 */
class ToolClassifier
{
    /**
     * Name and kind rows for every tool the agent exposes.
     *
     * @param object $agent Agent under inspection.
     * @return array<int, array{name: string, type: string}>
     */
    public function classify(object $agent): array
    {
        if (!$agent instanceof HasTools) {
            return [];
        }

        $tools = [];

        foreach ($agent->tools() as $tool) {
            $tools[] = $this->classifyTool($tool);
        }

        return $tools;
    }

    /**
     * Classify a single heterogeneous tool entry.
     *
     * @param mixed $tool Tool value from the agent.
     * @return array{name: string, type: string}
     */
    protected function classifyTool(mixed $tool): array
    {
        return match (true) {
            $tool instanceof Agent => ['name' => (new AgentTool($tool))->name(), 'type' => 'agent'],
            $tool instanceof Tool => ['name' => ToolNameResolver::resolve($tool), 'type' => 'tool'],
            McpTool::supports($tool) => ['name' => (new McpTool($tool))->name(), 'type' => 'mcp'],
            McpServerTool::supports($tool) => ['name' => (new McpServerTool($tool))->name(), 'type' => 'mcp'],
            $tool instanceof ProviderTool => ['name' => self::basename($tool::class), 'type' => 'provider_tool'],
            is_object($tool) => ['name' => self::basename($tool::class), 'type' => 'unknown'],
            default => ['name' => 'Unknown', 'type' => 'unknown'],
        };
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

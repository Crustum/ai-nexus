<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Cake\Core\Configure;
use Crustum\Ai\Ai;
use Throwable;

/**
 * The models the playground offers for a given agent.
 *
 * The agent's own model is always first and always the default: an override is
 * a temporary experiment, and the agent's configuration stays the thing the
 * panel and the composer agree on.
 */
class ModelOptions
{
    /**
     * Option rows for the playground model selector.
     *
     * @param \Crustum\AiNexus\Discovery\DiscoveredAgent $agent Agent under inspection.
     * @return array<int, array{id: string, label: string, tier: string}>
     */
    public function for(DiscoveredAgent $agent): array
    {
        $tiers = $this->providerTiers($agent);
        $options = [];

        $default = $agent->model ?? ($tiers[$agent->modelTier] ?? $tiers['default'] ?? null);

        if ($default !== null) {
            $options[] = $this->option($default, 'agent');
        }

        foreach ($tiers as $tier => $model) {
            if ($tier !== 'default') {
                $options[] = $this->option($model, $tier);
            }
        }

        $configured = Configure::read('AiNexus.playground.models', []);

        if (is_array($configured)) {
            foreach ($configured as $model) {
                if (is_string($model) && $model !== '') {
                    $options[] = $this->option($model, 'configured');
                }
            }
        }

        $unique = [];

        foreach ($options as $option) {
            $unique[$option['id']] ??= $option;
        }

        return array_values($unique);
    }

    /**
     * The provider's cheapest and smartest text models.
     *
     * Resolving a provider can throw for a driver the host app has not
     * configured. The composer degrading to the agent's own model is a far
     * better outcome than the playground refusing to render.
     *
     * @param \Crustum\AiNexus\Discovery\DiscoveredAgent $agent Agent under inspection.
     * @return array<string, string>
     */
    protected function providerTiers(DiscoveredAgent $agent): array
    {
        try {
            $provider = Ai::manager()->textProvider($agent->provider);

            return [
                'cheapest' => $provider->cheapestTextModel(),
                'smartest' => $provider->smartestTextModel(),
                'default' => $provider->defaultTextModel(),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * A single selector option row.
     *
     * @param string $model Model identifier.
     * @param string $tier Tier label.
     * @return array{id: string, label: string, tier: string}
     */
    protected function option(string $model, string $tier): array
    {
        return ['id' => $model, 'label' => $model, 'tier' => $tier];
    }
}

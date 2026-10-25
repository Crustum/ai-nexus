<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Crustum\AiNexus\Discovery\DiscoveredAgent;

/**
 * Discovered agents: list and Info-panel detail.
 */
class AgentsController extends AiNexusController
{
    /**
     * List every discovered agent with its card metadata.
     *
     * The payload is a bare JSON array, matching the dashboard client
     * contract — not wrapped in a `data` envelope.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $agents = [];

        foreach ($this->discovery()->all() as $agent) {
            $agents[] = $agent->toArray();
        }

        $this->autoRender = false;
        $this->set('agents', $agents);

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode($agents));
    }

    /**
     * Full detail for one agent: config, prompt, tools, middleware.
     *
     * @param string $agent Agent slug.
     * @return \Cake\Http\Response
     */
    public function show(string $agent): Response
    {
        $discovered = $this->discovery()->find($agent);

        if (!$discovered instanceof DiscoveredAgent) {
            throw new NotFoundException('Agent not found.');
        }

        return $this->json($this->agentDetail()->for($discovered));
    }
}

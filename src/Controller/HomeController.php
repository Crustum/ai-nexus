<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Controller;

use Cake\Http\Response;
use Crustum\AiNexus\AiNexus;

/**
 * Dashboard shell: the frontend owns every route below it.
 */
class HomeController extends AiNexusController
{
    /**
     * Serve the single-page application shell.
     *
     * @return \Cake\Http\Response|null
     */
    public function index(): ?Response
    {
        $this->set('aiNexusVariables', AiNexus::scriptVariables());
        $this->viewBuilder()->setLayout('ai_nexus');

        return null;
    }
}

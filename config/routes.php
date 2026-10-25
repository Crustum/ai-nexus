<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Routing\RouteBuilder;

/**
 * AiNexus plugin routes.
 *
 * The dashboard path follows `AiNexus.ui.path` (default `ai_nexus`): the JSON
 * API lives under `/api`, and every other GET path serves the SPA shell.
 * Access control belongs to the host application, which applies its own
 * middleware (for example the cakedc/users authentication and
 * cakephp/authorization checks) through the `AiNexus.ui.middleware`
 * configuration key. There is deliberately no clear-all route: the clear
 * command does that, and the dashboard has no control for it.
 *
 * @var \Cake\Routing\RouteBuilder $routes
 */
$uiPath = Configure::read('AiNexus.ui.path', 'ai_nexus');
$uiPath = is_string($uiPath) && trim($uiPath, '/') !== '' ? trim($uiPath, '/') : 'ai_nexus';

$dashboardMiddleware = Configure::read('AiNexus.ui.middleware', []);
$dashboardMiddleware = is_array($dashboardMiddleware) ? array_values($dashboardMiddleware) : [];

$routes->plugin(
    'Crustum/AiNexus',
    ['path' => '/' . $uiPath],
    function (RouteBuilder $builder) use ($dashboardMiddleware): void {
        foreach ($dashboardMiddleware as $index => $middleware) {
            $builder->registerMiddleware('aiNexusHost' . $index, $middleware);
            $builder->applyMiddleware('aiNexusHost' . $index);
        }

        $builder->scope('/api', function (RouteBuilder $api): void {
            $api->get('/agents', ['controller' => 'Agents', 'action' => 'index']);
            $api->get('/agents/{agent}', ['controller' => 'Agents', 'action' => 'show'])
                ->setPass(['agent']);
            $api->post('/chat/{agent}/send', ['controller' => 'Chat', 'action' => 'send'])
                ->setPass(['agent']);
            $api->get('/attachments/{message}/{index}', ['controller' => 'Attachments', 'action' => 'show'])
                ->setPass(['message', 'index'])
                ->setPatterns(['index' => '\d+']);
            $api->get('/conversations', ['controller' => 'Conversations', 'action' => 'index']);
            $api->get('/conversations/{id}', ['controller' => 'Conversations', 'action' => 'show'])
                ->setPass(['id']);
            $api->patch('/conversations/{id}', ['controller' => 'Conversations', 'action' => 'update'])
                ->setPass(['id']);
            $api->delete('/conversations/{id}', ['controller' => 'Conversations', 'action' => 'destroy'])
                ->setPass(['id']);
        });

        $builder->get('/', ['controller' => 'Home', 'action' => 'index']);
        $builder->get('/*', ['controller' => 'Home', 'action' => 'index']);
    },
);

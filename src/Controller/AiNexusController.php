<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Crustum\Ai\Ai;
use Crustum\Ai\Filesystem\FilesystemRegistry;
use Crustum\AiNexus\Chat\AgentInvoker;
use Crustum\AiNexus\Chat\AttachmentStore;
use Crustum\AiNexus\Chat\ConversationWriter;
use Crustum\AiNexus\Chat\MessageHistory;
use Crustum\AiNexus\Discovery\AgentDetail;
use Crustum\AiNexus\Discovery\AgentDiscovery;
use Crustum\AiNexus\Discovery\AgentMetadata;
use Crustum\AiNexus\Discovery\GenerationOptions;
use Crustum\AiNexus\Discovery\InstantiationDiagnostic;
use Crustum\AiNexus\Discovery\ModelOptions;
use Crustum\AiNexus\Discovery\ToolClassifier;
use Crustum\AiNexus\Discovery\ToolDetail;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use Crustum\AiNexus\Repository\ConversationQuery;
use Crustum\AiNexus\Repository\ConversationRepository;
use Psr\Container\ContainerInterface;

/**
 * Dashboard controllers share one wiring point.
 *
 * Actions compose their services here from the table locator, plugin config,
 * and the host application container. Agents resolve through the shared
 * container the Ai plugin binds on Application.buildContainer, so provider
 * service definitions stay intact.
 */
abstract class AiNexusController extends Controller
{
    /**
     * The three dashboard tables.
     *
     * @return array{\Crustum\AiNexus\Model\Table\AiNexusConversationsTable, \Crustum\AiNexus\Model\Table\AiNexusMessagesTable, \Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable}
     */
    protected function tables(): array
    {
        $locator = $this->getTableLocator();

        return [
            $locator->get('AiNexusConversations', ['className' => AiNexusConversationsTable::class]),
            $locator->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]),
            $locator->get('AiNexusToolInvocations', ['className' => AiNexusToolInvocationsTable::class]),
        ];
    }

    /**
     * Container used to build agents.
     *
     * The host application owns the container: the Ai plugin binds it on
     * Application.buildContainer, with every service provider attached. No
     * container is built here; this accessor returns the shared instance.
     *
     * @return \Psr\Container\ContainerInterface
     */
    protected function container(): ContainerInterface
    {
        return Ai::container();
    }

    /**
     * Agent discovery over the configured paths.
     *
     * @return \Crustum\AiNexus\Discovery\AgentDiscovery
     */
    protected function discovery(): AgentDiscovery
    {
        $container = $this->container();

        return new AgentDiscovery(
            new AgentMetadata(
                new ToolClassifier(),
                new InstantiationDiagnostic($container),
                $container,
            ),
            $container,
        );
    }

    /**
     * Info-panel detail for one discovered agent.
     *
     * @return \Crustum\AiNexus\Discovery\AgentDetail
     */
    protected function agentDetail(): AgentDetail
    {
        return new AgentDetail(
            new GenerationOptions(),
            new ToolDetail(),
            new ModelOptions(),
            $this->container(),
        );
    }

    /**
     * History list queries.
     *
     * @return \Crustum\AiNexus\Repository\ConversationQuery
     */
    protected function conversationQuery(): ConversationQuery
    {
        [$conversations, $messages, $invocations] = $this->tables();

        return new ConversationQuery($conversations, $messages, $invocations, $this->discovery());
    }

    /**
     * Cascade deletion with real attachment file removal.
     *
     * @return \Crustum\AiNexus\Repository\ConversationRepository
     */
    protected function conversationRepository(): ConversationRepository
    {
        [$conversations, $messages, $invocations] = $this->tables();

        return new ConversationRepository(
            $conversations,
            $messages,
            $invocations,
            static function (string $disk, string $path): void {
                $operator = FilesystemRegistry::get($disk);

                if ($operator->fileExists($path)) {
                    $operator->delete($path);
                }
            },
        );
    }

    /**
     * One chat turn runner.
     *
     * @return \Crustum\AiNexus\Chat\AgentInvoker
     */
    protected function invoker(): AgentInvoker
    {
        [$conversations, $messages, $invocations] = $this->tables();

        $attachments = new AttachmentStore();
        $writer = new ConversationWriter($conversations, $messages);

        return new AgentInvoker(
            $writer,
            new MessageHistory($messages, $attachments),
            $attachments,
            EventManager::instance(),
            $this->container(),
            $invocations,
        );
    }

    /**
     * Render a JSON payload.
     *
     * The payload goes on the wire exactly as given — no envelope — matching
     * the dashboard client contract. It is also exposed as the `data` view
     * variable for tests.
     *
     * @param array<string, mixed>|array<int, mixed> $payload Response payload.
     * @param int $status Response status.
     * @return \Cake\Http\Response
     */
    protected function json(array $payload, int $status = 200): Response
    {
        $this->autoRender = false;
        $this->set('data', $payload);

        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody((string)json_encode($payload));
    }

    /**
     * Render a validation failure.
     *
     * @param array<string, mixed> $errors Validation errors.
     * @return \Cake\Http\Response
     */
    protected function invalid(array $errors): Response
    {
        return $this->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
    }

    /**
     * Dashboard base path without slashes.
     *
     * @return string
     */
    protected function basePath(): string
    {
        $path = Configure::read('AiNexus.ui.path', 'ai_nexus');

        return trim(is_string($path) && $path !== '' ? $path : 'ai_nexus', '/');
    }
}

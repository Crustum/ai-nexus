<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Command;

use Cake\Command\Command;
use Crustum\Ai\Ai;
use Crustum\Ai\Filesystem\FilesystemRegistry;
use Crustum\AiNexus\Discovery\AgentDiscovery;
use Crustum\AiNexus\Discovery\AgentMetadata;
use Crustum\AiNexus\Discovery\InstantiationDiagnostic;
use Crustum\AiNexus\Discovery\ToolClassifier;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use Crustum\AiNexus\Repository\ConversationRepository;
use Psr\Container\ContainerInterface;

/**
 * Dashboard commands share one wiring point.
 */
abstract class AiNexusCommand extends Command
{
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
     * Cascade deletion with real attachment file removal.
     *
     * @return \Crustum\AiNexus\Repository\ConversationRepository
     */
    protected function repository(): ConversationRepository
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
}

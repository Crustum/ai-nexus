<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Repository;

use Cake\Database\Expression\QueryExpression;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Crustum\AiNexus\Discovery\AgentDiscovery;
use Crustum\AiNexus\Discovery\AgentSlug;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Model\Entity\AiNexusConversation;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;

/**
 * Builds the History list: filters, sort, pagination, and the aggregates each
 * row shows.
 */
class ConversationQuery
{
    /**
     * Conversations per history page.
     *
     * @var int
     */
    public const int PER_PAGE = 25;

    /**
     * Wire the query with its tables and agent discovery.
     *
     * @param \Crustum\AiNexus\Model\Table\AiNexusConversationsTable $conversations Conversations table.
     * @param \Crustum\AiNexus\Model\Table\AiNexusMessagesTable $messages Messages table.
     * @param \Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable $toolInvocations Tool invocations table.
     * @param \Crustum\AiNexus\Discovery\AgentDiscovery $discovery Agent discovery.
     */
    public function __construct(
        protected AiNexusConversationsTable $conversations,
        protected AiNexusMessagesTable $messages,
        protected AiNexusToolInvocationsTable $toolInvocations,
        protected AgentDiscovery $discovery,
    ) {
    }

    /**
     * Paginated conversation summaries with list metadata.
     *
     * @param array<string, mixed> $filters List filters.
     * @return array{data: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function paginate(array $filters = []): array
    {
        $perPage = (int)($filters['per_page'] ?? self::PER_PAGE);
        $perPage = $perPage > 0 ? $perPage : self::PER_PAGE;

        $page = max(1, (int)($filters['page'] ?? 1));
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        $alias = $this->conversations->getAlias();

        $query = $this->conversations->find();
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy([$alias . '.updated_at' => $direction, $alias . '.id' => $direction])
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->all();

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = (string)$row->get('id');
        }

        $aggregates = $this->aggregates($ids);

        $data = [];

        foreach ($rows as $row) {
            $data[] = $this->summary($row, $aggregates[(string)$row->get('id')] ?? []);
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int)max(1, ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * The values the filter menus offer.
     *
     * Agents come from discovery. Tool names come from the invocation rows,
     * including tools since deleted from the codebase.
     *
     * @return array{agents: array<int, array{slug: string, name: string}>, tools: array<int, string>}
     */
    public function filterOptions(): array
    {
        return [
            'agents' => array_map(
                static fn(DiscoveredAgent $agent): array => ['slug' => $agent->slug, 'name' => $agent->name],
                $this->discovery->all(),
            ),
            'tools' => $this->toolNames(),
        ];
    }

    /**
     * The list payload for one conversation, with its aggregates loaded.
     *
     * @param string $conversationId Conversation id.
     * @return array<string, mixed>
     */
    public function summaryFor(string $conversationId): array
    {
        $conversation = $this->conversations->find()->where(['id' => $conversationId])->firstOrFail();
        $aggregates = $this->aggregates([$conversationId]);

        return $this->summary($conversation, $aggregates[$conversationId] ?? []);
    }

    /**
     * The list payload for one conversation.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation entity.
     * @param array{prompt_tokens?: int, completion_tokens?: int, tool_calls?: int, has_error?: bool} $aggregates Preloaded aggregates.
     * @return array<string, mixed>
     */
    public function summary(AiNexusConversation $conversation, array $aggregates = []): array
    {
        $agentClass = (string)$conversation->get('agent_class');
        $slug = AgentSlug::make($agentClass);
        $prompt = (int)($aggregates['prompt_tokens'] ?? 0);
        $completion = (int)($aggregates['completion_tokens'] ?? 0);
        $created = $conversation->get('created_at');
        $updated = $conversation->get('updated_at');

        return [
            'id' => (string)$conversation->get('id'),
            'agent_class' => $agentClass,
            'agent_slug' => $slug,
            'agent_name' => self::basename($agentClass),
            'agent_available' => $this->discovery->find($slug) instanceof DiscoveredAgent,
            'title' => $conversation->get('title'),
            'status' => $aggregates['has_error'] ?? false ? 'error' : 'success',
            'tool_calls' => (int)($aggregates['tool_calls'] ?? 0),
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'created_at' => $created instanceof DateTime ? $created->toIso8601String() : null,
            'updated_at' => $updated instanceof DateTime ? $updated->toIso8601String() : null,
        ];
    }

    /**
     * Token sums, tool counts, and error flags keyed by conversation id.
     *
     * @param array<int, string> $ids Conversation ids.
     * @return array<string, array{prompt_tokens: int, completion_tokens: int, tool_calls: int, has_error: bool}>
     */
    protected function aggregates(array $ids): array
    {
        $aggregates = [];

        foreach ($ids as $id) {
            $aggregates[$id] = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'tool_calls' => 0,
                'has_error' => false,
            ];
        }

        if ($ids === []) {
            return $aggregates;
        }

        $messageAlias = $this->messages->getAlias();

        $sums = $this->messages->find()
            ->select([
                'conversation_id',
                'prompt' => $this->messages->find()->func()->sum($messageAlias . '.prompt_tokens'),
                'completion' => $this->messages->find()->func()->sum($messageAlias . '.completion_tokens'),
            ])
            ->where([$messageAlias . '.conversation_id IN' => $ids])
            ->groupBy('conversation_id')
            ->all();

        foreach ($sums as $sum) {
            $id = (string)$sum->get('conversation_id');

            if (isset($aggregates[$id])) {
                $aggregates[$id]['prompt_tokens'] = (int)$sum->get('prompt');
                $aggregates[$id]['completion_tokens'] = (int)$sum->get('completion');
            }
        }

        $errors = $this->messages->find()
            ->select(['conversation_id'])
            ->distinct()
            ->where([$messageAlias . '.conversation_id IN' => $ids, $messageAlias . '.role' => 'error'])
            ->all();

        foreach ($errors as $error) {
            $id = (string)$error->get('conversation_id');

            if (isset($aggregates[$id])) {
                $aggregates[$id]['has_error'] = true;
            }
        }

        $invocationAlias = $this->toolInvocations->getAlias();

        $counts = $this->toolInvocations->find()
            ->select(['conversation_id', 'total' => $this->toolInvocations->find()->func()->count('*')])
            ->where([$invocationAlias . '.conversation_id IN' => $ids])
            ->groupBy('conversation_id')
            ->all();

        foreach ($counts as $count) {
            $id = (string)$count->get('conversation_id');

            if (isset($aggregates[$id])) {
                $aggregates[$id]['tool_calls'] = (int)$count->get('total');
            }
        }

        return $aggregates;
    }

    /**
     * Distinct tool names that have run, ordered by name.
     *
     * @return array<int, string>
     */
    protected function toolNames(): array
    {
        $alias = $this->toolInvocations->getAlias();
        $names = [];

        $rows = $this->toolInvocations->find()
            ->select(['name'])
            ->distinct()
            ->orderBy([$alias . '.name' => 'asc'])
            ->all();

        foreach ($rows as $row) {
            $names[] = (string)$row->get('name');
        }

        return $names;
    }

    /**
     * Apply the list filters to the given query.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\AiNexus\Model\Entity\AiNexusConversation> $query Conversations query.
     * @param array<string, mixed> $filters List filters.
     * @return void
     */
    protected function applyFilters(SelectQuery $query, array $filters): void
    {
        $alias = $this->conversations->getAlias();

        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $like = '%' . $search . '%';

            $query->where(fn(QueryExpression $exp): QueryExpression => $exp->or([
                $this->conversations->getAlias() . '.title LIKE' => $like,
                $exp->exists($this->messagesLike($like)),
            ]));
        }

        $agents = $filters['agents'] ?? null;

        if (!in_array($agents, [null, [], ''], true)) {
            $query->where([$alias . '.agent_class IN' => $this->classesFor((array)$agents)]);
        }

        $status = $filters['status'] ?? null;

        if ($status === 'error') {
            $query->where(fn(QueryExpression $exp): QueryExpression => $exp->exists($this->errorMessages()));
        } elseif ($status === 'success') {
            $query->where(fn(QueryExpression $exp): QueryExpression => $exp->notExists($this->errorMessages()));
        }

        $tools = $filters['tools'] ?? null;

        if (!in_array($tools, [null, [], ''], true)) {
            $invocationAlias = $this->toolInvocations->getAlias();

            $exists = $this->toolInvocations->find()
                ->select([$invocationAlias . '.conversation_id'])
                ->where([
                    $invocationAlias . '.conversation_id = ' . $alias . '.id',
                    $invocationAlias . '.name IN' => (array)$tools,
                ]);

            $query->where(fn(QueryExpression $exp): QueryExpression => $exp->exists($exists));
        }

        $from = $filters['from'] ?? null;

        if (is_string($from) && $from !== '') {
            $query->where([$alias . '.updated_at >=' => $this->dayStart($from)]);
        }

        $to = $filters['to'] ?? null;

        if (is_string($to) && $to !== '') {
            $query->where([$alias . '.updated_at <=' => $this->dayEnd($to)]);
        }
    }

    /**
     * Messages in the conversation whose content matches.
     *
     * @param string $like Like pattern.
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\AiNexus\Model\Entity\AiNexusMessage>
     */
    protected function messagesLike(string $like): SelectQuery
    {
        $alias = $this->messages->getAlias();

        return $this->messages->find()
            ->select([$alias . '.conversation_id'])
            ->where([
                $alias . '.conversation_id = ' . $this->conversations->getAlias() . '.id',
                $alias . '.content LIKE' => $like,
            ]);
    }

    /**
     * Error messages in the conversation.
     *
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\AiNexus\Model\Entity\AiNexusMessage>
     */
    protected function errorMessages(): SelectQuery
    {
        $alias = $this->messages->getAlias();

        return $this->messages->find()
            ->select([$alias . '.conversation_id'])
            ->where([
                $alias . '.conversation_id = ' . $this->conversations->getAlias() . '.id',
                $alias . '.role' => 'error',
            ]);
    }

    /**
     * Resolve agent slugs to class names through discovery.
     *
     * Slugs resolve by lookup only, so a crafted filter cannot name an
     * arbitrary class. An unmatched filter excludes everything.
     *
     * @param array<int, mixed> $slugs Agent slugs.
     * @return array<int, string>
     */
    protected function classesFor(array $slugs): array
    {
        $classes = [];

        foreach ($slugs as $slug) {
            if (!is_string($slug)) {
                continue;
            }

            $agent = $this->discovery->find($slug);

            if ($agent instanceof DiscoveredAgent) {
                $classes[] = $agent->class;
            }
        }

        return $classes === [] ? [''] : $classes;
    }

    /**
     * Day start for a date filter value.
     *
     * @param string $value Date filter value.
     * @return string
     */
    protected function dayStart(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value . ' 00:00:00' : $value;
    }

    /**
     * Day end for a date filter value.
     *
     * @param string $value Date filter value.
     * @return string
     */
    protected function dayEnd(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value . ' 23:59:59' : $value;
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

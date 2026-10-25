<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use Crustum\AiNexus\Discovery\AgentSlug;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Model\Entity\AiNexusConversation;
use Crustum\AiNexus\Model\Entity\AiNexusMessage;
use Crustum\AiNexus\Model\Entity\AiNexusToolInvocation;
use DateTimeInterface;

/**
 * Conversation history: list, replay, rename, delete.
 */
class ConversationsController extends AiNexusController
{
    /**
     * The History list: filtered, sorted, paginated.
     *
     * Filter options ship with the list so the page needs one request, not three.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $query = $this->conversationQuery();
        $filters = $this->request->getQueryParams();

        $errors = $this->indexValidator()->validate($filters);

        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $result = $query->paginate($filters);

        return $this->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
            'filters' => $query->filterOptions(),
        ]);
    }

    /**
     * Replay one conversation: every turn, in order, with its token counts.
     *
     * @param string $id Conversation id.
     * @return \Cake\Http\Response
     */
    public function show(string $id): Response
    {
        [, $messages, $invocations] = $this->tables();

        $conversation = $this->findConversation($id);
        $slug = AgentSlug::make((string)$conversation->get('agent_class'));

        $rows = $messages->find()
            ->where(['conversation_id' => $id])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->toList();

        $promptTokens = 0;
        $completionTokens = 0;
        $payloadMessages = [];

        foreach ($rows as $row) {
            $promptTokens += (int)$row->get('prompt_tokens');
            $completionTokens += (int)$row->get('completion_tokens');
            $payloadMessages[] = $this->messagePayload($row);
        }

        $toolRows = $invocations->find()
            ->where(['conversation_id' => $id])
            ->orderBy(['started_at' => 'ASC', 'id' => 'ASC'])
            ->all();

        $payloadTools = [];

        foreach ($toolRows as $toolRow) {
            $payloadTools[] = $this->toolInvocationPayload($toolRow);
        }

        return $this->json([
            'id' => $conversation->get('id'),
            'agent_class' => $conversation->get('agent_class'),
            'agent_slug' => $slug,
            'agent_available' => $this->discovery()->find($slug) instanceof DiscoveredAgent,
            'title' => $conversation->get('title'),
            'created_at' => $this->iso($conversation->get('created_at')),
            'totals' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ],
            'tool_invocations' => $payloadTools,
            'messages' => $payloadMessages,
        ]);
    }

    /**
     * Rename a conversation.
     *
     * Titles are never model-generated — not on creation and not here. Spending
     * a provider call on cosmetics is not something a debugging tool should do
     * behind your back.
     *
     * @param string $id Conversation id.
     * @return \Cake\Http\Response
     */
    public function update(string $id): Response
    {
        $data = $this->request->getData();

        $errors = $this->updateValidator()->validate($data);

        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $conversation = $this->findConversation($id);
        $conversation->set('title', (string)$data['title']);

        [$conversations] = $this->tables();
        $conversations->saveOrFail($conversation);

        return $this->json($this->conversationQuery()->summaryFor($id));
    }

    /**
     * Delete a conversation and everything hanging off it.
     *
     * @param string $id Conversation id.
     * @return \Cake\Http\Response
     */
    public function destroy(string $id): Response
    {
        $this->conversationRepository()->deleteConversations([$id]);

        return $this->response->withStatus(204);
    }

    /**
     * History list filter rules.
     *
     * @return \Cake\Validation\Validator
     */
    protected function indexValidator(): Validator
    {
        $validator = new Validator();
        $validator
            ->allowEmptyString('search')
            ->add('agents', 'isArray', ['rule' => is_array(...)])
            ->allowEmptyArray('agents')
            ->allowEmptyString('status')
            ->inList('status', ['success', 'error'])
            ->add('tools', 'isArray', ['rule' => is_array(...)])
            ->allowEmptyArray('tools')
            ->allowEmptyString('from')
            ->date('from')
            ->allowEmptyString('to')
            ->date('to')
            ->allowEmptyString('sort')
            ->inList('sort', ['newest', 'oldest'])
            ->allowEmptyString('page')
            ->integer('page')
            ->greaterThanOrEqual('page', 1)
            ->allowEmptyString('per_page')
            ->integer('per_page')
            ->greaterThanOrEqual('per_page', 1)
            ->lessThanOrEqual('per_page', 100);

        return $validator;
    }

    /**
     * Rename payload rules.
     *
     * @return \Cake\Validation\Validator
     */
    protected function updateValidator(): Validator
    {
        $validator = new Validator();
        $validator
            ->requirePresence('title')
            ->scalar('title')
            ->notEmptyString('title')
            ->maxLength('title', 255);

        return $validator;
    }

    /**
     * Find one conversation or fail the request.
     *
     * @param string $id Conversation id.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusConversation
     */
    protected function findConversation(string $id): AiNexusConversation
    {
        [$conversations] = $this->tables();

        $conversation = $conversations->find()->where(['id' => $id])->first();

        if (!$conversation instanceof AiNexusConversation) {
            throw new NotFoundException('Conversation not found.');
        }

        return $conversation;
    }

    /**
     * One replayed message for the thread payload.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusMessage $message Message row.
     * @return array<string, mixed>
     */
    protected function messagePayload(AiNexusMessage $message): array
    {
        $usage = $message->get('usage');
        $meta = $message->get('meta');
        $metadata = $message->get('metadata');

        return [
            'id' => $message->get('id'),
            'role' => $message->get('role'),
            'content' => $message->get('content'),
            'attachments' => $this->messageAttachments($message),
            'usage' => $usage !== [] ? $usage : null,
            'duration_ms' => $message->get('duration_ms'),
            'meta' => $meta !== [] ? $meta : null,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => $this->iso($message->get('created_at')),
        ];
    }

    /**
     * A message's attachments, as things the browser can fetch.
     *
     * Paths and disks stay on the server: the UI needs to show and download a
     * file, not to know where it lives on the filesystem.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusMessage $message Message row.
     * @return array<int, array<string, mixed>>
     */
    protected function messageAttachments(AiNexusMessage $message): array
    {
        $stored = $message->get('attachments');

        if (!is_array($stored)) {
            return [];
        }

        $base = $this->basePath();
        $payload = [];
        $index = 0;

        foreach (array_values($stored) as $attachment) {
            if (!is_array($attachment)) {
                $index++;

                continue;
            }

            $payload[] = [
                'type' => $attachment['type'] ?? 'stored-document',
                'name' => $attachment['name'] ?? 'attachment',
                'url' => Router::url("/{$base}/api/attachments/{$message->get('id')}/{$index}", true),
            ];
            $index++;
        }

        return $payload;
    }

    /**
     * One tool invocation for the replay payload, oldest first.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation $invocation Tool invocation row.
     * @return array<string, mixed>
     */
    protected function toolInvocationPayload(AiNexusToolInvocation $invocation): array
    {
        return [
            'id' => $invocation->get('id'),
            'message_id' => $invocation->get('message_id'),
            'tool_call_id' => $invocation->get('tool_invocation_id'),
            'type' => $invocation->get('type'),
            'name' => $invocation->get('name'),
            'arguments' => $invocation->get('arguments'),
            'result' => $invocation->get('result'),
            'status' => $invocation->get('status'),
            'provider_status' => $invocation->get('provider_status'),
            'error' => $invocation->get('error'),
            'duration_ms' => $invocation->get('duration_ms'),
            'started_at' => $this->iso($invocation->get('started_at')),
            'finished_at' => $this->iso($invocation->get('finished_at')),
        ];
    }

    /**
     * ISO-8601 form of a stored moment, if one is set.
     *
     * @param mixed $value Stored value.
     * @return string|null
     */
    protected function iso(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return null;
    }
}

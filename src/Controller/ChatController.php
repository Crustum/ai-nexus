<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Validation\Validator;
use Crustum\AiNexus\Chat\AttachmentStore;
use Crustum\AiNexus\Chat\StreamEmitter;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Http\ChatStreamResponse;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Chat playground: send a message and stream the answer back.
 */
class ChatController extends AiNexusController
{
    /**
     * Send a message to an agent and stream the answer back.
     *
     * Uploads are moved to the disk before the stream opens: once the response
     * is being sent there is no way to report a storage failure other than as
     * an error part. The turn itself runs when the stream is read, after the
     * streaming headers are on the wire — the invoker never lets an exception
     * escape, so a failure arrives as an error part, not a status code.
     *
     * @param string $agent Agent slug.
     * @return \Cake\Http\Response
     */
    public function send(string $agent): Response
    {
        $data = $this->request->getData();

        $errors = $this->validator()->validate($data);

        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $uploads = $this->uploads($data['attachments'] ?? null);

        if ($uploads === null) {
            return $this->invalid(['attachments' => ['Attachments must be uploaded files.']]);
        }

        $discovered = $this->discovery()->find($agent);

        if (!$discovered instanceof DiscoveredAgent) {
            throw new NotFoundException('Agent not found.');
        }

        $store = new AttachmentStore();
        $attachments = $store->store($uploads);
        $invoker = $this->invoker();
        $message = (string)$data['message'];
        $conversationId = isset($data['conversation_id']) && $data['conversation_id'] !== ''
            ? (string)$data['conversation_id']
            : null;
        $model = isset($data['model']) && $data['model'] !== '' ? (string)$data['model'] : null;

        return new ChatStreamResponse(
            function (StreamEmitter $emitter) use ($invoker, $discovered, $message, $conversationId, $attachments, $model): void {
                ignore_user_abort(true);

                $invoker->handle($discovered, $message, $conversationId, $emitter, $attachments, $model);
            },
        );
    }

    /**
     * Send payload rules.
     *
     * The model override stays deliberately unconstrained: trying a model the
     * dashboard never heard of should surface the provider's rejection rather
     * than a guess made here.
     *
     * @return \Cake\Validation\Validator
     */
    protected function validator(): Validator
    {
        $validator = new Validator();
        $validator
            ->requirePresence('message')
            ->scalar('message')
            ->notEmptyString('message')
            ->allowEmptyString('conversation_id')
            ->allowEmptyString('model');

        return $validator;
    }

    /**
     * Uploaded files from the send payload, or null when it is malformed.
     *
     * No MIME allowlist: an unsupported type should reach the provider and come
     * back as an error card, exactly as it would in production.
     *
     * @param mixed $attachments Raw attachments value.
     * @return array<int, \Psr\Http\Message\UploadedFileInterface>|null
     */
    protected function uploads(mixed $attachments): ?array
    {
        if ($attachments === null) {
            return [];
        }

        if (!is_array($attachments)) {
            return null;
        }

        $uploads = [];

        foreach ($attachments as $upload) {
            if (
                !$upload instanceof UploadedFileInterface
                || $upload->getError() !== UPLOAD_ERR_OK
            ) {
                return null;
            }

            $uploads[] = $upload;
        }

        return $uploads;
    }
}

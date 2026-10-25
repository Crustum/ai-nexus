<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Http;

use Cake\Http\Response\AbstractStreamResponse;
use Closure;
use Crustum\AiNexus\Chat\StreamEmitter;

/**
 * Server-sent event stream for one chat turn.
 *
 * The turn runs when the response body is read — after the streaming headers
 * are on the wire — so a failure can always arrive as an error part rather
 * than truncating the stream. The runner echoes parts as the turn produces
 * them; nothing is buffered for the whole run.
 */
class ChatStreamResponse extends AbstractStreamResponse
{
    /**
     * Turn runner, receiving a capturing emitter.
     *
     * @var \Closure(\Crustum\AiNexus\Chat\StreamEmitter): void
     */
    protected Closure $run;

    /**
     * @param \Closure(\Crustum\AiNexus\Chat\StreamEmitter): void $run Turn runner.
     */
    public function __construct(Closure $run)
    {
        $this->run = $run;

        parent::__construct([]);
    }

    /**
     * The SSE MIME type without the charset suffix.
     *
     * @return string
     */
    protected function contentType(): string
    {
        return 'text/event-stream';
    }

    /**
     * SSE headers: no caching, no proxy buffering, protocol version.
     *
     * @return void
     */
    protected function applyStreamingHeaders(): void
    {
        parent::applyStreamingHeaders();

        $this->_setHeader('Cache-Control', 'no-cache, no-transform');
        $this->_setHeader('Connection', 'keep-alive');
        $this->_setHeader('x-vercel-ai-ui-message-stream', 'v1');
    }

    /**
     * Run the turn, echoing each part as it is produced.
     *
     * @return void
     */
    protected function streamData(): void
    {
        ($this->run)(new StreamEmitter());
    }
}

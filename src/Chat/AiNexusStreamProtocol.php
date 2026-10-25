<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Streaming\Event\StreamEvent;
use Crustum\Ai\Streaming\Protocols\VercelDataProtocol;

/**
 * The SDK's Vercel data stream protocol with its event mapping opened up.
 *
 * The mapping (`mapEvent()`) and the finish part (`finishPart()`) are
 * `protected` on the SDK's protocol — they only run inside its own response
 * generator, which AiNexus cannot use because dashboard-specific `data-*`
 * parts interleave the model parts on one stream. This subclass exposes exactly
 * those two, so the emitter renders every model part through the SDK's own
 * mapping instead of maintaining a parallel copy of it.
 */
class AiNexusStreamProtocol extends VercelDataProtocol
{
    /**
     * The protocol part for one stream event, if the protocol renders it.
     *
     * @param \Crustum\Ai\Streaming\Event\StreamEvent $event Stream event.
     * @return array<string, mixed>|null
     */
    public function map(StreamEvent $event): ?array
    {
        return $this->mapEvent($event);
    }

    /**
     * The protocol part closing the stream for the given finish reason.
     *
     * @param string $reason Finish reason.
     * @param \Crustum\Ai\Responses\Data\TextUsage $usage Combined usage.
     * @return array<string, mixed>
     */
    public function finishFor(string $reason, TextUsage $usage): array
    {
        return $this->finishPart($reason, $usage);
    }
}

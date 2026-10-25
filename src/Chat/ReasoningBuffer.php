<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

/**
 * Collects a run's reasoning as it streams past.
 *
 * Reasoning has no home in the streamed response text, so without gathering
 * the deltas while they go by a replayed conversation would lose the thinking
 * the developer watched.
 */
class ReasoningBuffer
{
    /**
     * Accumulated reasoning text.
     *
     * @var string
     */
    protected string $text = '';

    /**
     * Append a reasoning delta.
     *
     * @param string $delta Reasoning delta.
     * @return void
     */
    public function append(string $delta): void
    {
        $this->text .= $delta;
    }

    /**
     * The reasoning so far, or null when the model did none.
     *
     * @return string|null
     */
    public function text(): ?string
    {
        return $this->text === '' ? null : $this->text;
    }
}

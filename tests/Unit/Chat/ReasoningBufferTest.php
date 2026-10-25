<?php
declare(strict_types=1);

use Crustum\AiNexus\Chat\ReasoningBuffer;

it('reports no reasoning before any delta', function (): void {
    expect((new ReasoningBuffer())->text())->toBeNull();
});

it('accumulates reasoning deltas in order', function (): void {
    $buffer = new ReasoningBuffer();
    $buffer->append('Let me think');
    $buffer->append(' this through.');

    expect($buffer->text())->toBe('Let me think this through.');
});

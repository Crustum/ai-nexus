<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Crustum\Ai\Attributes\Strict;

/**
 * The `#[Strict]` flavour of the history decorator.
 *
 * `Strict::isAppliedTo()` reflects the attribute off the agent instance and has
 * no method fallback, so it is the one setting the decorator cannot forward.
 * A second annotated class is the only way to carry it across the wrap;
 * `AiNexusConversationalAgent::for()` picks between the two.
 */
#[Strict]
final class StrictAiNexusConversationalAgent extends AiNexusConversationalAgent
{
}

<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Crustum\Ai\Attributes\Strict;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasMiddleware;
use Crustum\Ai\Contracts\HasProviderOptions;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Contracts\Providers\TextProvider;
use Crustum\Ai\Enums\Lab;
use Crustum\Ai\Gateway\TextGenerationOptions;
use Crustum\Ai\Providers\Provider as AbstractProvider;
use Crustum\Ai\Support\ToolChoice;
use Crustum\Ai\Trait\PromptableTrait;
use ReflectionMethod;
use Stringable;

/**
 * Wraps a Conversational agent so the SDK receives AiNexus's own thread history
 * instead of the agent's ConversationStore.
 *
 * Only the `messages()` behaviour may differ from the wrapped agent — everything
 * else has to run exactly as it would unwrapped. That is harder than it looks:
 * the SDK resolves an agent's provider, model, timeout, model tier and every
 * generation option by reflecting on the *instance*, so each of those call sites
 * would otherwise see this class instead of the real agent and silently fall
 * back to framework defaults. Each is forwarded below.
 *
 * Two interfaces are deliberately NOT implemented:
 *
 * - `HasStructuredOutput` — the SDK refuses to stream any agent that implements
 *   it, so carrying it here would break every conversational stream.
 *   Structured agents are invoked undecorated via `prompt()` instead.
 * - `RemembersConversations` — the SDK's middleware gathering looks for the
 *   trait. Leaving it off is what keeps the SDK's `RememberConversation`
 *   middleware and the developer's own store out of the picture, which is the
 *   whole point of supplying history ourselves.
 */
class AiNexusConversationalAgent implements Agent, Conversational, HasMiddleware, HasProviderOptions, HasTools
{
    /**
     * The aliases keep the trait's own implementations reachable after the
     * overrides below, as a fallback for the rare agent that satisfies the Agent
     * contract without using PromptableTrait.
     */
    use PromptableTrait {
        getProvidersAndModels as protected promptableProvidersAndModels;
        getTimeout as protected promptableTimeout;
        getDefaultModelFor as protected promptableDefaultModelFor;
    }

    /**
     * The wrapped agent's own generation options, resolved once.
     */
    protected TextGenerationOptions $options;

    /**
     * Wrap an agent, supplying AiNexus thread history as its messages.
     *
     * @param \Crustum\Ai\Contracts\Agent $agent Wrapped agent.
     * @param array<int, \Crustum\Ai\Messages\Message> $history AiNexus thread history.
     */
    public function __construct(
        protected Agent $agent,
        protected array $history = [],
    ) {
        $this->options = TextGenerationOptions::forAgent($agent);
    }

    /**
     * Wrap the given agent, preserving the `#[Strict]` attribute when present.
     *
     * `Strict::isAppliedTo()` reads the attribute straight off the instance with
     * no method fallback, so it is the one setting a forwarding method cannot
     * carry — it needs an annotated subclass instead.
     *
     * @param \Crustum\Ai\Contracts\Agent $agent Wrapped agent.
     * @param array<int, \Crustum\Ai\Messages\Message> $history AiNexus thread history.
     * @return self
     */
    public static function for(Agent $agent, array $history = []): self
    {
        return Strict::isAppliedTo($agent)
            ? new StrictAiNexusConversationalAgent($agent, $history)
            : new self($agent, $history);
    }

    /**
     * The agent being wrapped.
     *
     * @return \Crustum\Ai\Contracts\Agent
     */
    public function wrapped(): Agent
    {
        return $this->agent;
    }

    /**
     * The wrapped agent's instructions.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return $this->agent->instructions();
    }

    /**
     * AiNexus thread history in place of the agent's own messages.
     *
     * @return iterable<\Crustum\Ai\Messages\Message>
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * The wrapped agent's tools, or nothing when it declares none.
     *
     * @return iterable<\Crustum\Ai\Contracts\Agent|\Crustum\Ai\Contracts\Tool|\Crustum\Ai\Providers\Tools\ProviderTool>
     */
    public function tools(): iterable
    {
        return $this->agent instanceof HasTools ? $this->agent->tools() : [];
    }

    /**
     * The wrapped agent's middleware, or nothing when it declares none.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return $this->agent instanceof HasMiddleware ? $this->agent->middleware() : [];
    }

    /**
     * The wrapped agent's provider options for the given provider.
     *
     * @param \Crustum\Ai\Enums\Lab|string $provider Provider name.
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $this->agent instanceof HasProviderOptions
            ? $this->agent->providerOptions($provider)
            : [];
    }

    /**
     * Generation options. TextGenerationOptions::forAgent() checks for a method
     * before falling back to the attribute, so forwarding the resolved values as
     * methods is enough — this class never needs the attributes themselves.
     */

    /**
     * The wrapped agent's maximum step count.
     *
     * @return int|null
     */
    public function maxSteps(): ?int
    {
        return $this->options->maxSteps;
    }

    /**
     * The wrapped agent's maximum token count.
     *
     * @return int|null
     */
    public function maxTokens(): ?int
    {
        return $this->options->maxTokens;
    }

    /**
     * The wrapped agent's temperature.
     *
     * @return float|null
     */
    public function temperature(): ?float
    {
        return $this->options->temperature;
    }

    /**
     * The wrapped agent's top-p value.
     *
     * @return float|null
     */
    public function topP(): ?float
    {
        return $this->options->topP;
    }

    /**
     * `ToolChoice::from()` accepts an instance, so the resolved object passes
     * straight through.
     *
     * @return \Crustum\Ai\Support\ToolChoice|null
     */
    public function toolChoice(): ?ToolChoice
    {
        return $this->options->toolChoice;
    }

    /**
     * Provider / model / timeout / tier. These live on the PromptableTrait the
     * wrapped agent also uses, so rather than reimplementing the SDK's precedence
     * rules we run its own methods against the real agent.
     */

    /**
     * Resolve providers and models against the wrapped agent.
     *
     * @param \Crustum\Ai\Enums\Lab|\Crustum\Ai\Providers\Provider|array<string, string|null>|string|null $provider Initial provider value.
     * @param string|null $model Initial model value.
     * @return array<string, string|null>
     */
    protected function getProvidersAndModels(Lab|array|string|AbstractProvider|null $provider, ?string $model): array
    {
        /** @var array<string, string|null> */
        return $this->callOnAgent('getProvidersAndModels', [$provider, $model])
            ?? $this->promptableProvidersAndModels($provider, $model);
    }

    /**
     * Resolve the timeout against the wrapped agent.
     *
     * @param int|null $timeout Request timeout in seconds.
     * @return int
     */
    protected function getTimeout(?int $timeout): int
    {
        return (int)($this->callOnAgent('getTimeout', [$timeout])
            ?? $this->promptableTimeout($timeout));
    }

    /**
     * Resolve the default model against the wrapped agent.
     *
     * @param \Crustum\Ai\Contracts\Providers\TextProvider $provider Text provider.
     * @return string
     */
    protected function getDefaultModelFor(TextProvider $provider): string
    {
        return (string)($this->callOnAgent('getDefaultModelFor', [$provider])
            ?? $this->promptableDefaultModelFor($provider));
    }

    /**
     * Invoke one of the wrapped agent's protected resolution methods against the
     * wrapped agent itself, or null when it does not define one.
     *
     * @param string $method Resolution method name.
     * @param array<int, mixed> $arguments Method arguments.
     * @return mixed
     */
    protected function callOnAgent(string $method, array $arguments): mixed
    {
        if (!method_exists($this->agent, $method)) {
            return null;
        }

        return (new ReflectionMethod($this->agent, $method))
            ->invoke($this->agent, ...$arguments);
    }
}

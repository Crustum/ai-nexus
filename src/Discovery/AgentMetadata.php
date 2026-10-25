<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Cake\Core\Configure;
use Crustum\Ai\Attributes\Model as ModelAttribute;
use Crustum\Ai\Attributes\Provider as ProviderAttribute;
use Crustum\Ai\Attributes\UseCheapestModel;
use Crustum\Ai\Attributes\UseSmartestModel;
use Crustum\Ai\Contracts\CanActAsTool;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasMiddleware;
use Crustum\Ai\Contracts\HasStructuredOutput;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Contracts\RemembersConversations;
use Crustum\Ai\Enums\Lab;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use Throwable;
use TypeError;

/**
 * Reads the card-level metadata for one agent class.
 *
 * Resolution order mirrors the SDK's provider and model resolution: provider
 * and model methods take precedence over the corresponding attributes.
 */
class AgentMetadata
{
    /**
     * Wire metadata with its classifier, diagnostic, and agent container.
     *
     * @param \Crustum\AiNexus\Discovery\ToolClassifier $tools Tool classifier.
     * @param \Crustum\AiNexus\Discovery\InstantiationDiagnostic $diagnostic Instantiation diagnostic.
     * @param \Psr\Container\ContainerInterface $container Container used to build agents.
     */
    public function __construct(
        protected ToolClassifier $tools,
        protected InstantiationDiagnostic $diagnostic,
        protected ContainerInterface $container,
    ) {
    }

    /**
     * Build the dashboard card for the given agent class.
     *
     * Reflection-based containers report an unresolvable constructor as a
     * type error, so those join the container wiring failures.
     *
     * @param class-string $class Agent FQCN.
     * @return \Crustum\AiNexus\Discovery\DiscoveredAgent
     */
    public function for(string $class): DiscoveredAgent
    {
        try {
            $agent = $this->container->get($class);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface | TypeError $e) {
            return DiscoveredAgent::unavailable(
                $class,
                $e->getMessage(),
                'binding',
                $this->diagnostic->unresolvable($class),
            );
        } catch (Throwable $e) {
            return DiscoveredAgent::unavailable($class, $e->getMessage(), 'exception');
        }

        try {
            $reflection = new ReflectionClass($agent);
            $model = $this->model($agent, $reflection);

            return new DiscoveredAgent(
                slug: AgentSlug::make($class),
                name: self::basename($class),
                class: $class,
                provider: $this->provider($agent, $reflection) ?? $this->defaultProvider(),
                model: $model,
                modelTier: $model === null ? $this->modelTier($reflection) : 'default',
                tools: $this->tools->classify($agent),
                capabilities: $this->capabilities($agent),
            );
        } catch (Throwable $throwable) {
            return DiscoveredAgent::unavailable($class, $throwable->getMessage(), 'exception');
        }
    }

    /**
     * Resolve the provider name: public method, then attribute.
     *
     * Protected declarations are skipped: they are SDK declaration helpers,
     * not callable metadata endpoints.
     *
     * @param object $agent Agent under inspection.
     * @param \ReflectionClass<object> $reflection Agent reflection.
     * @return string|null
     */
    protected function provider(object $agent, ReflectionClass $reflection): ?string
    {
        if ($reflection->hasMethod('provider') && $reflection->getMethod('provider')->isPublic()) {
            $provider = $reflection->getMethod('provider')->invoke($agent);

            return $this->normalizeProvider($provider);
        }

        $attributes = $reflection->getAttributes(ProviderAttribute::class);

        return $attributes === []
            ? null
            : $this->normalizeProvider($attributes[0]->newInstance()->value);
    }

    /**
     * Reduce a provider declaration to the primary provider name.
     *
     * @param mixed $provider Provider declaration.
     * @return string|null
     */
    protected function normalizeProvider(mixed $provider): ?string
    {
        if (is_array($provider)) {
            $provider = $provider === [] ? null : reset($provider);
        }

        return match (true) {
            $provider instanceof Lab => $provider->value,
            is_string($provider) => $provider,
            default => null,
        };
    }

    /**
     * Resolve the model name: public method, then attribute.
     *
     * Protected declarations are skipped: they are SDK declaration helpers,
     * not callable metadata endpoints.
     *
     * @param object $agent Agent under inspection.
     * @param \ReflectionClass<object> $reflection Agent reflection.
     * @return string|null
     */
    protected function model(object $agent, ReflectionClass $reflection): ?string
    {
        if ($reflection->hasMethod('model') && $reflection->getMethod('model')->isPublic()) {
            $model = $reflection->getMethod('model')->invoke($agent);

            return is_string($model) ? $model : null;
        }

        $attributes = $reflection->getAttributes(ModelAttribute::class);

        return $attributes === [] ? null : $attributes[0]->newInstance()->value;
    }

    /**
     * The tier the SDK falls back to when no model is declared.
     *
     * @param \ReflectionClass<object> $reflection Agent reflection.
     * @return string
     */
    protected function modelTier(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->getAttributes(UseSmartestModel::class) !== [] => 'smartest',
            $reflection->getAttributes(UseCheapestModel::class) !== [] => 'cheapest',
            default => 'default',
        };
    }

    /**
     * The configured default provider name, if any.
     *
     * @return string|null
     */
    protected function defaultProvider(): ?string
    {
        $default = Configure::read('Ai.default');

        return is_string($default) ? $default : null;
    }

    /**
     * Capability flags for the given agent.
     *
     * @param object $agent Agent under inspection.
     * @return array<string, bool>
     */
    protected function capabilities(object $agent): array
    {
        return [
            'conversational' => $agent instanceof Conversational,
            'remembers_conversations' => $agent instanceof RemembersConversations,
            'has_tools' => $agent instanceof HasTools,
            'has_structured_output' => $agent instanceof HasStructuredOutput,
            'has_middleware' => $agent instanceof HasMiddleware,
            'can_act_as_tool' => $agent instanceof CanActAsTool,
        ];
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

<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Explains why the container could not build an agent.
 *
 * The constructor is inspected directly instead of parsing the container
 * exception message, yielding structured reasons the dashboard can render.
 */
class InstantiationDiagnostic
{
    /**
     * Wire the diagnostic with the container used to test explicit bindings.
     *
     * @param \Psr\Container\ContainerInterface|null $container Container used to test explicit bindings.
     */
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    /**
     * Required constructor parameters the container cannot resolve.
     *
     * An empty list means the failure came from somewhere else, so the raw
     * exception message is all there is.
     *
     * @param class-string $class Agent FQCN.
     * @return array<int, array{parameter: string, type: string|null, reason: string}>
     */
    public function unresolvable(string $class): array
    {
        try {
            $constructor = (new ReflectionClass($class))->getConstructor();
        } catch (Throwable) {
            return [];
        }

        if ($constructor === null) {
            return [];
        }

        $unresolvable = [];

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                continue;
            }

            $reason = $this->reasonFor($parameter);

            if ($reason !== null) {
                $type = $parameter->getType();

                $unresolvable[] = [
                    'parameter' => $parameter->getName(),
                    'type' => $type instanceof ReflectionNamedType ? $type->getName() : null,
                    'reason' => $reason,
                ];
            }
        }

        return $unresolvable;
    }

    /**
     * Why this parameter cannot be autowired, or null when it can.
     *
     * @param \ReflectionParameter $parameter Constructor parameter.
     * @return string|null
     */
    protected function reasonFor(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $type === null ? 'untyped' : 'unsupported_type';
        }

        if ($type->isBuiltin()) {
            return 'primitive';
        }

        $name = $type->getName();

        if (!class_exists($name) && !interface_exists($name)) {
            return 'missing_class';
        }

        if ($this->container instanceof ContainerInterface && $this->container->has($name)) {
            return null;
        }

        if (interface_exists($name)) {
            return 'unbound_interface';
        }

        return (new ReflectionClass($name))->isAbstract() ? 'unbound_abstract' : null;
    }
}

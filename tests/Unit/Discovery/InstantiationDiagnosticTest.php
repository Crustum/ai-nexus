<?php
declare(strict_types=1);

use Crustum\AiNexus\Discovery\InstantiationDiagnostic;
use Crustum\AiNexus\Test\Fixtures\FakeNeedyAgent;
use Crustum\AiNexus\Test\Fixtures\FakeSubAgent;
use Crustum\AiNexus\Test\Fixtures\FakeUnboundInterface;
use Psr\Container\ContainerInterface;

it('identifies an unbound interface in the constructor', function (): void {
    $unresolvable = (new InstantiationDiagnostic())->unresolvable(FakeNeedyAgent::class);

    expect($unresolvable)->toBe([
        [
            'parameter' => 'dependency',
            'type' => FakeUnboundInterface::class,
            'reason' => 'unbound_interface',
        ],
    ]);
});

it('reports nothing for an agent with no constructor', function (): void {
    expect((new InstantiationDiagnostic())->unresolvable(FakeSubAgent::class))->toBe([]);
});

it('reports nothing for a missing class', function (): void {
    expect((new InstantiationDiagnostic())->unresolvable('No\\Such\\Agent'))->toBe([]);
});

it('treats a bound interface as resolvable', function (): void {
    $container = new class implements ContainerInterface {
        public function get(string $id): mixed
        {
            throw new RuntimeException('Not implemented in tests.');
        }

        public function has(string $id): bool
        {
            return $id === FakeUnboundInterface::class;
        }
    };

    expect((new InstantiationDiagnostic($container))->unresolvable(FakeNeedyAgent::class))->toBe([]);
});

it('flags a primitive constructor parameter', function (): void {
    $agent = new class ('secret') extends FakeSubAgent {
        public function __construct(protected string $apiKey)
        {
        }
    };

    expect((new InstantiationDiagnostic())->unresolvable($agent::class))->toBe([
        [
            'parameter' => 'apiKey',
            'type' => 'string',
            'reason' => 'primitive',
        ],
    ]);
});

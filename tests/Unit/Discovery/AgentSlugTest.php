<?php
declare(strict_types=1);

use Crustum\AiNexus\Discovery\AgentSlug;

it('slugifies each namespace segment with dots', function (): void {
    expect(AgentSlug::make('App\\Ai\\Agents\\SupportAgent'))->toBe('app.ai.agents.support-agent');
});

it('matches the dashboard slug format for a single class name', function (): void {
    expect(AgentSlug::make('WeatherAgent'))->toBe('weather-agent');
});

it('slugifies fixture-style namespaces segment by segment', function (): void {
    expect(AgentSlug::make('Workbench\\App\\Agents\\WeatherAgent'))->toBe('workbench.app.agents.weather-agent');
});

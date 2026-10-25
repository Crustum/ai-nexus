<?php
declare(strict_types=1);

use Cake\Http\Client\Exception\ClientException;
use Crustum\AiNexus\Chat\ProviderErrorDetail;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('returns null without a throwable', function (): void {
    expect(ProviderErrorDetail::for(null))->toBeNull();
});

it('returns null when the chain holds no response detail', function (): void {
    expect(ProviderErrorDetail::for(new RuntimeException('Boom')))->toBeNull();
});

it('reads the status from a client exception code', function (): void {
    $detail = ProviderErrorDetail::for(new ClientException('HTTP request returned status code 400', 400));

    expect($detail)->toBe(['status' => 400, 'body' => null]);
});

it('walks the chain past wrapper exceptions', function (): void {
    $previous = new ClientException('HTTP request returned status code 429', 429);
    $error = new RuntimeException('Agent run failed.', 0, $previous);

    expect(ProviderErrorDetail::for($error))->toBe(['status' => 429, 'body' => null]);
});

it('reads the status and body from a response-holding exception', function (): void {
    $error = new RequestException(
        'HTTP request returned status code 400',
        new Request('POST', 'https://api.example.test/v1/chat'),
        new Response(400, [], '{"error":"Unsupported parameter"}'),
    );

    expect(ProviderErrorDetail::for($error))->toBe([
        'status' => 400,
        'body' => '{"error":"Unsupported parameter"}',
    ]);
});

it('truncates oversized response bodies', function (): void {
    $error = new RequestException(
        'HTTP request returned status code 400',
        new Request('POST', 'https://api.example.test/v1/chat'),
        new Response(400, [], str_repeat('x', 9000)),
    );

    $detail = ProviderErrorDetail::for($error);

    expect($detail['status'])->toBe(400)
        ->and(mb_strlen((string)$detail['body']))->toBe(8000);
});

it('returns the cached detail for a repeated throwable', function (): void {
    $error = new ClientException('HTTP request returned status code 400', 400);

    expect(ProviderErrorDetail::for($error))->toBe(ProviderErrorDetail::for($error));
});

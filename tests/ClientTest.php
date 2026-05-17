<?php

declare(strict_types=1);

namespace FetchHive\Sdk\Tests;

use FetchHive\Sdk\FetchHive;
use FetchHive\Sdk\Exception\ApiException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;

/**
 * Client test matrix: C1–C5, A1–A2, P1–P3, W1–W3, AG1–AG3, S1, S2, E1, E2
 */
final class ClientTest extends TestCase
{
    private const BASE_URL = 'https://api.fetchhive.com/v1';

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Build a FetchHive instance backed by a Guzzle MockHandler. */
    private function makeClient(array $responses, array &$history = [], string $apiKey = 'fhk_test', string $baseUrl = self::BASE_URL): FetchHive
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        return new FetchHive([
            'api_key'        => $apiKey,
            'base_url'       => $baseUrl,
            'client_options' => ['handler' => $handler],
        ]);
    }

    private function jsonResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function sseResponse(string $body): Response
    {
        return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    private function requestBody(array &$history, int $index = 0): array
    {
        $bodyStr = (string) $history[$index]['request']->getBody();
        return json_decode($bodyStr, true);
    }

    // ── Construction ─────────────────────────────────────────────────────────

    // C1 — Missing API key with no env var raises with a clear message
    public function testC1_missingApiKeyRaises(): void
    {
        $prev = getenv('FETCH_HIVE_API_KEY');
        putenv('FETCH_HIVE_API_KEY=');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/api_key is required/');
            new FetchHive(['api_key' => null]);
        } finally {
            $prev !== false ? putenv("FETCH_HIVE_API_KEY=$prev") : putenv('FETCH_HIVE_API_KEY');
        }
    }

    // C2 — FETCH_HIVE_API_KEY env var used as fallback
    public function testC2_envVarFallback(): void
    {
        $prev = getenv('FETCH_HIVE_API_KEY');
        putenv('FETCH_HIVE_API_KEY=fhk_from_env');

        try {
            $history = [];
            $mock    = new MockHandler([$this->jsonResponse(['response' => 'ok'])]);
            $handler = HandlerStack::create($mock);
            $handler->push(Middleware::history($history));

            $client = new FetchHive(['client_options' => ['handler' => $handler]]);
            $result = $client->invokePrompt(['deployment' => 'dep']);
            $this->assertSame('ok', $result['response']);
            $this->assertStringContainsString('fhk_from_env', $history[0]['request']->getHeaderLine('Authorization'));
        } finally {
            $prev !== false ? putenv("FETCH_HIVE_API_KEY=$prev") : putenv('FETCH_HIVE_API_KEY');
        }
    }

    // C3 — Custom base_url is used for requests
    public function testC3_customBaseUrl(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history, 'fhk_test', 'https://custom.example.com/v1');
        $client->invokePrompt(['deployment' => 'dep']);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringContainsString('custom.example.com', $uri);
    }

    // C4 — Trailing slash on base_url is stripped
    public function testC4_trailingSlashStripped(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history, 'fhk_test', 'https://api.fetchhive.com/v1/');
        $client->invokePrompt(['deployment' => 'dep']);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringNotContainsString('//', str_replace('https://', '', $uri));
    }

    // C5 — Default base URL
    public function testC5_defaultBaseUrl(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history);
        $client->invokePrompt(['deployment' => 'dep']);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringStartsWith('https://api.fetchhive.com/v1', $uri);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    // A1 — Authorization: Bearer header sent on every request
    public function testA1_authorizationHeader(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history);
        $client->invokePrompt(['deployment' => 'dep']);

        $this->assertSame('Bearer fhk_test', $history[0]['request']->getHeaderLine('Authorization'));
    }

    // A2 — Content-Type: application/json sent
    public function testA2_contentTypeHeader(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history);
        $client->invokePrompt(['deployment' => 'dep']);

        $this->assertStringContainsString('application/json', $history[0]['request']->getHeaderLine('Content-Type'));
    }

    // ── Prompt ────────────────────────────────────────────────────────────────

    // P1 — invokePrompt POSTs to /invoke
    public function testP1_invokePromptEndpoint(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history);
        $client->invokePrompt(['deployment' => 'dep']);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringEndsWith('/invoke', $uri);
        $this->assertSame('POST', $history[0]['request']->getMethod());
    }

    // P2 — invokePrompt returns parsed JSON body
    public function testP2_invokePromptReturnsParsedJson(): void
    {
        $client = $this->makeClient([$this->jsonResponse(['response' => 'Hello', 'request_id' => 'r1'])]);
        $result = $client->invokePrompt(['deployment' => 'dep']);

        $this->assertSame('Hello', $result['response']);
        $this->assertSame('r1', $result['request_id']);
    }

    // P3 — streaming: false injected; optional fields only when provided
    public function testP3_promptBodyStreamingFalseInjected(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse([])], $history);
        $client->invokePrompt(['deployment' => 'dep']);

        $body = $this->requestBody($history);
        $this->assertFalse($body['streaming']);
        $this->assertArrayNotHasKey('variant', $body);
        $this->assertArrayNotHasKey('inputs', $body);
        $this->assertArrayNotHasKey('user', $body);
    }

    public function testP3_promptBodyIncludesOptionalFieldsWhenProvided(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse([])], $history);
        $client->invokePrompt(['deployment' => 'dep', 'variant' => 'v2', 'inputs' => ['k' => 'v'], 'user' => 'u1']);

        $body = $this->requestBody($history);
        $this->assertSame('v2', $body['variant']);
        $this->assertSame(['k' => 'v'], $body['inputs']);
        $this->assertSame('u1', $body['user']);
    }

    // ── Workflow ──────────────────────────────────────────────────────────────

    // W1 — invokeWorkflow POSTs to /workflow/invoke
    public function testW1_invokeWorkflowEndpoint(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['status' => 'completed'])], $history);
        $client->invokeWorkflow(['deployment' => 'wf']);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringEndsWith('/workflow/invoke', $uri);
        $this->assertSame('POST', $history[0]['request']->getMethod());
    }

    // W2 — invokeWorkflow returns parsed JSON body
    public function testW2_invokeWorkflowReturnsParsedJson(): void
    {
        $client = $this->makeClient([$this->jsonResponse(['status' => 'completed', 'run_id' => 'run1'])]);
        $result = $client->invokeWorkflow(['deployment' => 'wf']);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('run1', $result['run_id']);
    }

    // W3 — Async mode + callback URL
    public function testW3_asyncModeBodyShape(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['status' => 'queued'])], $history);
        $client->invokeWorkflow([
            'deployment'   => 'wf',
            'async_mode'   => true,
            'callback_url' => 'https://cb.example.com',
        ]);

        $body = $this->requestBody($history);
        $this->assertTrue($body['async']['enabled']);
        $this->assertSame('https://cb.example.com', $body['async']['callback_url']);
    }

    public function testW3_asyncBlockAbsentWhenSyncMode(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse([])], $history);
        $client->invokeWorkflow(['deployment' => 'wf']);

        $body = $this->requestBody($history);
        $this->assertArrayNotHasKey('async', $body);
    }

    // ── Agent ─────────────────────────────────────────────────────────────────

    // AG1 — invokeAgent POSTs to /agent/invoke with streaming: false
    public function testAG1_invokeAgentEndpointAndStreamingFalse(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse(['response' => 'ok'])], $history);
        $client->invokeAgent(['agent' => 'ag', 'message' => 'hi']);

        $uri  = (string) $history[0]['request']->getUri();
        $body = $this->requestBody($history);
        $this->assertStringEndsWith('/agent/invoke', $uri);
        $this->assertFalse($body['streaming']);
    }

    // AG2 — invokeAgent returns parsed JSON body
    public function testAG2_invokeAgentReturnsParsedJson(): void
    {
        $client = $this->makeClient([$this->jsonResponse(['response' => 'hello', 'thread_id' => 't1'])]);
        $result = $client->invokeAgent(['agent' => 'ag', 'message' => 'hi']);

        $this->assertSame('hello', $result['response']);
        $this->assertSame('t1', $result['thread_id']);
    }

    // AG3 — Optional fields included only when provided
    public function testAG3_optionalFieldsIncludedWhenProvided(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse([])], $history);
        $client->invokeAgent([
            'agent'      => 'ag',
            'message'    => 'hi',
            'thread_id'  => 't1',
            'user'       => 'u1',
            'messages'   => [['role' => 'user', 'content' => 'hi']],
            'image_urls' => ['https://img.example.com/a.png'],
        ]);

        $body = $this->requestBody($history);
        $this->assertSame('t1', $body['thread_id']);
        $this->assertSame('u1', $body['user']);
        $this->assertSame([['role' => 'user', 'content' => 'hi']], $body['messages']);
        $this->assertSame(['https://img.example.com/a.png'], $body['image_urls']);
    }

    public function testAG3_optionalFieldsAbsentWhenNotProvided(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->jsonResponse([])], $history);
        $client->invokeAgent(['agent' => 'ag', 'message' => 'hi']);

        $body = $this->requestBody($history);
        $this->assertArrayNotHasKey('thread_id', $body);
        $this->assertArrayNotHasKey('user', $body);
        $this->assertArrayNotHasKey('messages', $body);
        $this->assertArrayNotHasKey('image_urls', $body);
    }

    // ── Streaming ─────────────────────────────────────────────────────────────

    private function ssebody(): string
    {
        return "data: {\"type\":\"response\",\"response\":\"Hello\"}\n" .
               "data: {\"type\":\"usage\",\"request_id\":\"r1\",\"stop_reason\":\"completed\"}\n" .
               "data: [DONE]\n";
    }

    // S1 — invokePromptStream sends streaming: true and yields parsed events
    public function testS1_invokePromptStream(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->sseResponse($this->ssebody())], $history);
        $events  = iterator_to_array($client->invokePromptStream(['deployment' => 'dep']), false);

        $body = $this->requestBody($history);
        $this->assertTrue($body['streaming']);
        $this->assertCount(2, $events);
        $this->assertSame('response', $events[0]['type']);
        $this->assertSame('usage', $events[1]['type']);
    }

    // S2 — invokeAgentStream sends streaming: true and yields parsed events
    public function testS2_invokeAgentStream(): void
    {
        $history = [];
        $client  = $this->makeClient([$this->sseResponse($this->ssebody())], $history);
        $events  = iterator_to_array($client->invokeAgentStream(['agent' => 'ag', 'message' => 'hi']), false);

        $body = $this->requestBody($history);
        $this->assertTrue($body['streaming']);
        $this->assertCount(2, $events);
        $this->assertSame('response', $events[0]['type']);
    }

    // ── Errors ────────────────────────────────────────────────────────────────

    // E1 — Non-2xx on non-streaming endpoint raises with status code
    public function testE1_non2xxNonStreamingRaises(): void
    {
        $mock    = new MockHandler([new Response(422, [], '{"error":"invalid"}')]);
        $handler = HandlerStack::create($mock);
        $client  = new FetchHive(['api_key' => 'fhk_test', 'client_options' => ['handler' => $handler]]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/422/');
        $client->invokePrompt(['deployment' => 'dep']);
    }

    // E2 — Non-2xx on streaming endpoint raises before any events
    public function testE2_non2xxStreamingRaisesBeforeEvents(): void
    {
        $mock    = new MockHandler([new Response(401, [], 'Unauthorized')]);
        $handler = HandlerStack::create($mock);
        $client  = new FetchHive(['api_key' => 'fhk_test', 'client_options' => ['handler' => $handler]]);

        $events = [];
        $threw  = false;
        try {
            foreach ($client->invokeAgentStream(['agent' => 'ag', 'message' => 'hi']) as $event) {
                $events[] = $event;
            }
        } catch (ApiException $e) {
            $threw = true;
            $this->assertStringContainsString('401', $e->getMessage());
        }

        $this->assertTrue($threw, 'Expected ApiException to be thrown');
        $this->assertEmpty($events, 'No events should have been yielded before the exception');
    }
}

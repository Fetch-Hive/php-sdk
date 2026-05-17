<?php

declare(strict_types=1);

namespace FetchHive\Sdk;

use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use FetchHive\Sdk\Exception\ApiException;

/**
 * Idiomatic facade over the OpenAPI-generated code.
 *
 * Usage:
 *
 *   $client = new FetchHive(['api_key' => getenv('FETCH_HIVE_API_KEY')]);
 *
 *   // Non-streaming prompt
 *   $result = $client->invokePrompt(['deployment' => 'my-prompt', 'inputs' => ['name' => 'Alice']]);
 *   echo $result['response'];
 *
 *   // Streaming agent
 *   foreach ($client->invokeAgentStream(['agent' => 'my-agent', 'message' => 'Hello']) as $chunk) {
 *       match ($chunk['type']) {
 *           'response' => print($chunk['response'] ?? ''),
 *           'tool'     => print("\nCalling tool: " . ($chunk['tool'] ?? '')),
 *           'usage'    => print("\nUsage: " . json_encode($chunk['usage'])),
 *           default    => null,
 *       };
 *   }
 */
final class FetchHive
{
    private const DEFAULT_BASE_URL = 'https://api.fetchhive.com/v1';

    private string $apiKey;
    private string $baseUrl;
    private float $timeout;
    private GuzzleClient $httpClient;

    /**
     * @param array{
     *   api_key?: string|null,
     *   base_url?: string,
     *   timeout?: float,
     *   client_options?: array<string,mixed>
     * } $options
     */
    public function __construct(array $options = [])
    {
        $apiKey = $options['api_key'] ?? getenv('FETCH_HIVE_API_KEY') ?: null;
        if ($apiKey === null || $apiKey === '') {
            throw new \InvalidArgumentException(
                'api_key is required. Pass it explicitly or set FETCH_HIVE_API_KEY.'
            );
        }

        $this->apiKey   = $apiKey;
        $this->baseUrl  = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout  = (float) ($options['timeout'] ?? 120.0);

        $clientOptions = array_merge(
            ['timeout' => $this->timeout],
            $options['client_options'] ?? []
        );
        $this->httpClient = new GuzzleClient($clientOptions);
    }

    // ── Prompt ─────────────────────────────────────────────────────────────────

    /**
     * Invoke a prompt deployment and return the full response as an associative array.
     *
     * @param array{deployment: string, variant?: string, inputs?: array<string,mixed>, user?: string} $params
     * @return array<string,mixed>
     */
    public function invokePrompt(array $params): array
    {
        $body = ['deployment' => $params['deployment'], 'streaming' => false];
        if (isset($params['variant'])) {
            $body['variant'] = $params['variant'];
        }
        if (isset($params['inputs'])) {
            $body['inputs'] = $params['inputs'];
        }
        if (isset($params['user'])) {
            $body['user'] = $params['user'];
        }
        return $this->post('/invoke', $body);
    }

    /**
     * Invoke a prompt deployment and stream SSE events.
     *
     * @param array{deployment: string, variant?: string, inputs?: array<string,mixed>, user?: string} $params
     * @return Generator<array<string,mixed>>
     */
    public function invokePromptStream(array $params): Generator
    {
        $body = ['deployment' => $params['deployment'], 'streaming' => true];
        if (isset($params['variant'])) {
            $body['variant'] = $params['variant'];
        }
        if (isset($params['inputs'])) {
            $body['inputs'] = $params['inputs'];
        }
        if (isset($params['user'])) {
            $body['user'] = $params['user'];
        }
        yield from $this->postStream('/invoke', $body);
    }

    // ── Workflow ────────────────────────────────────────────────────────────────

    /**
     * Invoke a workflow deployment (sync or async).
     *
     * @param array{
     *   deployment: string,
     *   variant?: string,
     *   inputs?: array<string,mixed>,
     *   async_mode?: bool,
     *   callback_url?: string,
     *   user?: string
     * } $params
     * @return array<string,mixed>
     */
    public function invokeWorkflow(array $params): array
    {
        $body = ['deployment' => $params['deployment']];
        if (isset($params['variant'])) {
            $body['variant'] = $params['variant'];
        }
        if (isset($params['inputs'])) {
            $body['inputs'] = $params['inputs'];
        }
        if (isset($params['user'])) {
            $body['user'] = $params['user'];
        }
        if (!empty($params['async_mode'])) {
            $body['async'] = ['enabled' => true];
            if (isset($params['callback_url'])) {
                $body['async']['callback_url'] = $params['callback_url'];
            }
        }
        return $this->post('/workflow/invoke', $body);
    }

    // ── Agent ───────────────────────────────────────────────────────────────────

    /**
     * Send a message to an agent and return the full response.
     *
     * @param array{
     *   agent: string,
     *   message: string,
     *   thread_id?: string,
     *   user?: string,
     *   messages?: array<int,array<string,mixed>>,
     *   image_urls?: string[]
     * } $params
     * @return array<string,mixed>
     */
    public function invokeAgent(array $params): array
    {
        $body = [
            'agent'     => $params['agent'],
            'message'   => $params['message'],
            'streaming' => false,
        ];
        if (isset($params['thread_id'])) {
            $body['thread_id'] = $params['thread_id'];
        }
        if (isset($params['user'])) {
            $body['user'] = $params['user'];
        }
        if (isset($params['messages'])) {
            $body['messages'] = $params['messages'];
        }
        if (isset($params['image_urls'])) {
            $body['image_urls'] = $params['image_urls'];
        }
        return $this->post('/agent/invoke', $body);
    }

    /**
     * Send a message to an agent and stream SSE events.
     *
     * @param array{
     *   agent: string,
     *   message: string,
     *   thread_id?: string,
     *   user?: string,
     *   messages?: array<int,array<string,mixed>>,
     *   image_urls?: string[]
     * } $params
     * @return Generator<array<string,mixed>>
     */
    public function invokeAgentStream(array $params): Generator
    {
        $body = [
            'agent'     => $params['agent'],
            'message'   => $params['message'],
            'streaming' => true,
        ];
        if (isset($params['thread_id'])) {
            $body['thread_id'] = $params['thread_id'];
        }
        if (isset($params['user'])) {
            $body['user'] = $params['user'];
        }
        if (isset($params['messages'])) {
            $body['messages'] = $params['messages'];
        }
        if (isset($params['image_urls'])) {
            $body['image_urls'] = $params['image_urls'];
        }
        yield from $this->postStream('/agent/invoke', $body);
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = $this->httpClient->post($this->baseUrl . $path, [
                'headers' => $this->defaultHeaders(),
                'json'    => $body,
            ]);
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            $bodyStr = (string) $e->getResponse()->getBody();
            throw new ApiException($status, $bodyStr);
        }

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @param array<string,mixed> $body
     * @return Generator<array<string,mixed>>
     */
    private function postStream(string $path, array $body): Generator
    {
        try {
            $response = $this->httpClient->post($this->baseUrl . $path, [
                'headers' => $this->defaultHeaders(),
                'json'    => $body,
                'stream'  => true,
            ]);
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            $bodyStr = (string) $e->getResponse()->getBody();
            throw new ApiException($status, $bodyStr);
        }

        yield from Streaming::parseSse($response->getBody());
    }

    /** @return array<string,string> */
    private function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }
}

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
     * @param array{deployment: string, variant?: string, inputs?: array<string,mixed>, user?: string, metadata?: array<string,string|int|float|bool|null>} $params
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
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        return $this->post('/prompt/invoke', $body);
    }

    /**
     * Invoke a prompt deployment and stream SSE events.
     *
     * @param array{deployment: string, variant?: string, inputs?: array<string,mixed>, user?: string, metadata?: array<string,string|int|float|bool|null>} $params
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
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        yield from $this->postStream('/prompt/invoke', $body);
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
     *   user?: string,
     *   metadata?: array<string,string|int|float|bool|null>
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
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
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
     *   metadata?: array<string,string|int|float|bool|null>,
     *   messages?: array<int,array<string,mixed>>,
     *   image_urls?: string[],
     *   attachments?: array<int,string|array<string,mixed>>,
     *   known_artifact_refs?: string[],
     *   artifact_refs?: string[]
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
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        if (isset($params['messages'])) {
            $body['messages'] = $params['messages'];
        }
        if (isset($params['attachments']) || isset($params['image_urls'])) {
            $body['attachments'] = $params['attachments'] ?? $params['image_urls'];
        }
        if (isset($params['known_artifact_refs'])) {
            $body['known_artifact_refs'] = $params['known_artifact_refs'];
        }
        if (isset($params['artifact_refs'])) {
            $body['artifact_refs'] = $params['artifact_refs'];
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
     *   metadata?: array<string,string|int|float|bool|null>,
     *   messages?: array<int,array<string,mixed>>,
     *   image_urls?: string[],
     *   attachments?: array<int,string|array<string,mixed>>,
     *   known_artifact_refs?: string[],
     *   artifact_refs?: string[]
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
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        if (isset($params['messages'])) {
            $body['messages'] = $params['messages'];
        }
        if (isset($params['attachments']) || isset($params['image_urls'])) {
            $body['attachments'] = $params['attachments'] ?? $params['image_urls'];
        }
        if (isset($params['known_artifact_refs'])) {
            $body['known_artifact_refs'] = $params['known_artifact_refs'];
        }
        if (isset($params['artifact_refs'])) {
            $body['artifact_refs'] = $params['artifact_refs'];
        }
        yield from $this->postStream('/agent/invoke', $body);
    }

    // ── Hive Agent ──────────────────────────────────────────────────────────────

    /**
     * Start a Hive Agent run asynchronously. Requires a callback URL.
     *
     * @param array{
     *   hive_agent: string,
     *   objective: string,
     *   callback_url: string,
     *   sources?: array<string,mixed>,
     *   metadata?: array<string,string|int|float|bool|null>
     * } $params
     * @return array<string,mixed>
     */
    public function invokeHiveAgent(array $params): array
    {
        $callbackUrl = $params['callback_url'] ?? '';
        if ($callbackUrl === '') {
            throw new \InvalidArgumentException('callback_url is required for Hive Agent invocation');
        }
        $body = [
            'hive_agent' => $params['hive_agent'],
            'objective'  => $params['objective'],
            'async'      => ['enabled' => true, 'callback_url' => $callbackUrl],
        ];
        if (isset($params['sources'])) {
            $body['sources'] = $params['sources'];
        }
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        return $this->post('/hive-agent/invoke', $body);
    }

    // ── Public resources ────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function getRequest(string $id): array
    {
        return $this->request('GET', '/public/requests/' . $id);
    }

    /** @return array<string,mixed> */
    public function listKnowledgeBases(string $workspaceId): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/knowledge_bases');
    }

    /** @return array<string,mixed> */
    public function getKnowledgeBase(string $workspaceId, string $id): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $id);
    }

    /**
     * @param array<string,mixed> $knowledgeBase
     * @return array<string,mixed>
     */
    public function createKnowledgeBase(string $workspaceId, array $knowledgeBase): array
    {
        return $this->post('/public/workspaces/' . $workspaceId . '/knowledge_bases', ['knowledge_base' => $knowledgeBase]);
    }

    /**
     * @param array<string,mixed> $knowledgeBase
     * @return array<string,mixed>
     */
    public function updateKnowledgeBase(string $workspaceId, string $id, array $knowledgeBase): array
    {
        return $this->request('PATCH', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $id, ['knowledge_base' => $knowledgeBase]);
    }

    /** @return array<string,mixed> */
    public function deleteKnowledgeBase(string $workspaceId, string $id): array
    {
        return $this->request('DELETE', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $id);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function searchKnowledgeBase(string $workspaceId, string $id, array $params): array
    {
        return $this->post('/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $id . '/search', $params);
    }

    /** @return array<string,mixed> */
    public function listKnowledgeBaseItems(string $workspaceId, string $knowledgeBaseId): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $knowledgeBaseId . '/items');
    }

    /** @return array<string,mixed> */
    public function getKnowledgeBaseItem(string $workspaceId, string $knowledgeBaseId, string $id): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $knowledgeBaseId . '/items/' . $id);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function createKnowledgeBaseItem(string $workspaceId, string $knowledgeBaseId, array $item): array
    {
        return $this->post('/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $knowledgeBaseId . '/items', ['knowledge_base_item' => $item]);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function updateKnowledgeBaseItem(string $workspaceId, string $knowledgeBaseId, string $id, array $item): array
    {
        return $this->request('PATCH', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $knowledgeBaseId . '/items/' . $id, ['knowledge_base_item' => $item]);
    }

    /** @return array<string,mixed> */
    public function deleteKnowledgeBaseItem(string $workspaceId, string $knowledgeBaseId, string $id): array
    {
        return $this->request('DELETE', '/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $knowledgeBaseId . '/items/' . $id);
    }

    /** @return array<string,mixed> */
    public function regenerateKnowledgeBaseItem(string $workspaceId, string $knowledgeBaseId, string $id): array
    {
        return $this->post('/public/workspaces/' . $workspaceId . '/knowledge_bases/' . $knowledgeBaseId . '/items/' . $id . '/regenerate', []);
    }

    /** @return array<string,mixed> */
    public function listAgents(string $workspaceId): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/agents');
    }

    /** @return array<string,mixed> */
    public function getAgent(string $workspaceId, string $id): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/agents/' . $id);
    }

    /**
     * @param array<string,mixed> $agent
     * @return array<string,mixed>
     */
    public function createAgent(string $workspaceId, array $agent): array
    {
        return $this->post('/public/workspaces/' . $workspaceId . '/agents', ['agent' => $agent]);
    }

    /**
     * @param array<string,mixed> $agent
     * @return array<string,mixed>
     */
    public function updateAgent(string $workspaceId, string $id, array $agent): array
    {
        return $this->request('PATCH', '/public/workspaces/' . $workspaceId . '/agents/' . $id, ['agent' => $agent]);
    }

    /** @return array<string,mixed> */
    public function deleteAgent(string $workspaceId, string $id): array
    {
        return $this->request('DELETE', '/public/workspaces/' . $workspaceId . '/agents/' . $id);
    }

    /** @return array<string,mixed> */
    public function getAgentChat(string $workspaceId, string $agentId, string $chatId): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/agents/' . $agentId . '/chats/' . $chatId);
    }

    /**
     * @param array<string,mixed> $chat
     * @return array<string,mixed>
     */
    public function createAgentChat(string $workspaceId, string $agentId, array $chat): array
    {
        return $this->post('/public/workspaces/' . $workspaceId . '/agents/' . $agentId . '/chats', ['chat' => $chat]);
    }

    /**
     * @param array<string,mixed> $chat
     * @return array<string,mixed>
     */
    public function updateAgentChat(string $workspaceId, string $agentId, string $chatId, array $chat): array
    {
        return $this->request('PATCH', '/public/workspaces/' . $workspaceId . '/agents/' . $agentId . '/chats/' . $chatId, ['chat' => $chat]);
    }

    /** @return array<string,mixed> */
    public function deleteAgentChat(string $workspaceId, string $agentId, string $chatId): array
    {
        return $this->request('DELETE', '/public/workspaces/' . $workspaceId . '/agents/' . $agentId . '/chats/' . $chatId);
    }

    /** @return array<string,mixed> */
    public function clearAgentChatMessages(string $workspaceId, string $agentId, string $chatId): array
    {
        return $this->request('PATCH', '/public/workspaces/' . $workspaceId . '/agents/' . $agentId . '/chats/' . $chatId . '/clear_messages', []);
    }

    /** @return array<string,mixed> */
    public function listAgentChatMessages(string $workspaceId, string $agentId, string $chatId): array
    {
        return $this->request('GET', '/public/workspaces/' . $workspaceId . '/agents/' . $agentId . '/chats/' . $chatId . '/messages');
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $options = ['headers' => $this->defaultHeaders()];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            $bodyStr = (string) $e->getResponse()->getBody();
            throw new ApiException($status, $bodyStr);
        }

        $decoded = json_decode((string) $response->getBody(), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
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

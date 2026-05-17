<?php

declare(strict_types=1);

/**
 * Example: Invoke an agent with streaming.
 *
 * Run:
 *   FETCH_HIVE_API_KEY=fhk_... php examples/invoke_agent_stream.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FetchHive\Sdk\FetchHive;

$client = new FetchHive(['api_key' => getenv('FETCH_HIVE_API_KEY')]);

echo "Streaming agent response:\n\n";

foreach ($client->invokeAgentStream([
    'agent'   => 'my-agent',
    'message' => 'Tell me a short story about a robot learning PHP',
]) as $chunk) {
    match ($chunk['type']) {
        'response' => print($chunk['response'] ?? ''),
        'tool'     => print("\n[Calling tool: " . ($chunk['tool'] ?? '') . "]\n"),
        'usage'    => print("\n\n[Done — request_id: " . ($chunk['request_id'] ?? '') . "]\n"),
        default    => null,
    };
    ob_flush();
    flush();
}

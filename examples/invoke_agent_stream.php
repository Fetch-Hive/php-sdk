<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FetchHive\Sdk\FetchHive;

$client = new FetchHive(['api_key' => getenv('FETCH_HIVE_API_KEY')]);

foreach ($client->invokeAgentStream([
    'agent'   => 'my-agent',
    'message' => 'Tell me a short story about a robot learning PHP',
]) as $chunk) {
    match ($chunk['type']) {
        'delta' => (function () use ($chunk) {
            echo $chunk['content'];
            flush();
        })(),
        'done'  => printf("\n\n[Done — request_id: %s]\n", $chunk['request_id'] ?? ''),
        default => null,
    };
}

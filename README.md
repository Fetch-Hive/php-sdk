# Fetch Hive PHP SDK

Official PHP SDK for the [Fetch Hive](https://fetchhive.com) API — invoke prompts, workflows, and agents with a clean, idiomatic interface.

**Version:** 0.2.2

## Requirements

- PHP >= 8.1
- Composer

## Installation

```bash
composer require fetch-hive/sdk
```

## Quick start

```php
<?php

require 'vendor/autoload.php';

use FetchHive\Sdk\FetchHive;

$client = new FetchHive(['api_key' => getenv('FETCH_HIVE_API_KEY')]);

// Invoke a prompt
$result = $client->invokePrompt(['deployment' => 'my-prompt', 'inputs' => ['name' => 'Alice']]);
echo $result['response'];

// Invoke a workflow
$run = $client->invokeWorkflow(['deployment' => 'my-workflow', 'inputs' => ['topic' => 'AI']]);
echo $run['output'];

// Send a message to an agent (non-streaming)
$reply = $client->invokeAgent(['agent' => 'my-agent', 'message' => 'Hello!']);
echo $reply['response'];

// Stream an agent response
foreach ($client->invokeAgentStream(['agent' => 'my-agent', 'message' => 'Tell me a story']) as $chunk) {
    if ($chunk['type'] === 'delta') {
        echo $chunk['content'];
        flush();
    }
}
```

## Configuration

| Option | Default | Description |
|---|---|---|
| `api_key` | `FETCH_HIVE_API_KEY` env var | Bearer token from the Fetch Hive dashboard |
| `base_url` | `https://api.fetchhive.com/v1` | Override the API base URL |
| `timeout` | `120` | Request timeout in seconds |

## Links

- [Fetch Hive dashboard](https://app.fetchhive.com)
- [API documentation](https://docs.fetchhive.com)
- [GitHub](https://github.com/Fetch-Hive/php-sdk)

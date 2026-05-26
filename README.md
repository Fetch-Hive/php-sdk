# fetch-hive/sdk

Official PHP SDK for [Fetch Hive](https://fetchhive.com) — invoke AI prompts, workflows, and agents from your application.

[![Packagist Version](https://img.shields.io/packagist/v/fetch-hive/sdk.svg)](https://packagist.org/packages/fetch-hive/sdk)

## Installation

**Requirements:** PHP 8.1+ and [Composer](https://getcomposer.org/).

```bash
composer require fetch-hive/sdk
```

## Quick start

```php
<?php
require_once 'vendor/autoload.php';

use FetchHive\Sdk\FetchHive;

$client = new FetchHive(apiKey: getenv('FETCH_HIVE_API_KEY'));
```

Get your API key from the [Fetch Hive dashboard](https://app.fetchhive.com).

## Invoke a prompt

```php
$result = $client->invokePrompt(
    deployment: 'my-prompt',
    inputs: ['name' => 'Alice', 'topic' => 'machine learning'],
);
echo $result['response'];
```

## Invoke a prompt (streaming)

```php
foreach ($client->invokePromptStream(
    deployment: 'my-prompt',
    inputs: ['name' => 'Alice'],
) as $chunk) {
    match ($chunk['type']) {
        'response' => print($chunk['response'] ?? ''),
        'usage'    => print("\nUsage: " . json_encode($chunk['usage'])),
        default    => null,
    };
}
```

## Invoke a workflow

```php
$run = $client->invokeWorkflow(
    deployment: 'my-workflow',
    inputs: ['customer_id' => '42'],
);
echo $run['status'] . PHP_EOL;
print_r($run['output']);
```

## Invoke a workflow (async)

```php
$run = $client->invokeWorkflow(
    deployment: 'my-workflow',
    inputs: ['customer_id' => '42'],
    async_mode: true,
    callback_url: 'https://example.com/webhook',
);
echo 'Queued: ' . $run['run_id'];
```

## Invoke an agent

```php
$reply = $client->invokeAgent(
    agent: 'my-agent',
    message: 'What is the weather in London?',
);
echo $reply['response'];
```

## Invoke an agent (streaming)

```php
foreach ($client->invokeAgentStream(
    agent: 'my-agent',
    message: 'What is the weather in London?',
    thread_id: 'session-abc123',  // optional — persist conversation history
) as $chunk) {
    match ($chunk['type']) {
        'response' => print($chunk['response'] ?? ''),
        'tool'     => print("\nCalling tool: " . $chunk['tool']),
        'usage'    => print("\nUsage: " . json_encode($chunk['usage'])),
        default    => null,
    };
}
```

## Multimodal (image) inputs

```php
$result = $client->invokeAgent(
    agent: 'vision-agent',
    message: 'Describe this image',
    image_urls: ['https://example.com/photo.jpg'],
);
echo $result['response'];
```

## Authentication

Pass the API key to the constructor or set the environment variable:

```bash
export FETCH_HIVE_API_KEY=fhk_...
```

```php
$client = new FetchHive();  // picks up FETCH_HIVE_API_KEY automatically
```

## Configuration

| Option | Default | Description |
|---|---|---|
| `apiKey` | `FETCH_HIVE_API_KEY` env var | Bearer token from the Fetch Hive dashboard |
| `baseUrl` | `https://api.fetchhive.com/v1` | Override the API base URL |
| `timeout` | `120` | Request timeout in seconds |

## Links

- [Fetch Hive dashboard](https://app.fetchhive.com)
- [API documentation](https://docs.fetchhive.com)
- [GitHub](https://github.com/Fetch-Hive/php-sdk)

## Version

0.2.5

## License

MIT — see [LICENSE](LICENSE).

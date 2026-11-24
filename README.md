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
$result = $client->invokePrompt([
    'deployment' => 'my-prompt',
    'inputs'     => ['name' => 'Alice', 'topic' => 'machine learning'],
    'metadata' => [],
]);
echo $result['response'];
```

## Invoke a prompt (streaming)

```php
foreach ($client->invokePromptStream([
    'deployment' => 'my-prompt',
    'inputs'     => ['name' => 'Alice'],
]) as $chunk) {
    match ($chunk['type']) {
        'response' => print($chunk['response'] ?? ''),
        'usage'    => print("\nUsage: " . json_encode($chunk['usage'])),
        default    => null,
    };
}
```

## Invoke a workflow

```php
$run = $client->invokeWorkflow([
    'deployment' => 'my-workflow',
    'inputs'     => ['customer_id' => '42'],
    'metadata' => [],
]);
echo $run['status'] . PHP_EOL;
print_r($run['output']);
```

## Invoke a workflow (async)

```php
$run = $client->invokeWorkflow([
    'deployment'   => 'my-workflow',
    'inputs'       => ['customer_id' => '42'],
    'async_mode'   => true,
    'callback_url' => 'https://example.com/webhook',
]);
echo 'Queued: ' . $run['run_id'];
```

## Run a pipeline

A pipeline runs several workflows in a row and can stop at checkpoints for a person, so a run is always asynchronous: the call returns at once and the result is sent to your callback URL.

```php
$run = $client->invokePipeline([
    'deployment'   => 'seo-article',
    'variant'      => 'production',
    'inputs'       => ['keyword' => 'running shoes'],
    'callback_url' => 'https://example.com/hooks/pipeline',
])['pipeline_run'];

$status = $client->getPipelineRun($run['id']);
// At a checkpoint: read what it asks, then send the person's answer.
$checkpoint = $client->getPipelineCheckpoint($run['id']);
$client->answerPipelineCheckpoint($run['id'], ['answer' => ['approved' => true]]);
```

Building an app on a pipeline template? Set it up in your workspace, read its start inputs and estimated cost, and follow runs by polling, with checkpoint emails off because your app shows them:

```php
$client->installPipelineTemplate('TEMPLATE_ID'); // same pipeline back on later calls
$definition = $client->getPipelineDeployment('seo-article');
$run = $client->invokePipeline([
    'deployment'         => 'seo-article',
    'inputs'             => ['keyword' => 'running shoes'],
    'poll'               => true,
    'notify_checkpoints' => false,
])['pipeline_run'];
$history = $client->listPipelineRuns(['deployment' => 'seo-article', 'per_page' => 10]);
```

## Invoke an agent

```php
$reply = $client->invokeAgent([
    'agent'    => 'my-agent',
    'message'  => 'What is the weather in London?',
    'metadata' => [],
]);
echo $reply['response'];
```

## Metadata

Pass optional `metadata` on prompt, workflow, or agent invokes to attach flat audit fields for log display and filtering. Metadata values must be strings, numbers, booleans, or `null`.

## Invoke an agent (streaming)

```php
foreach ($client->invokeAgentStream([
    'agent'     => 'my-agent',
    'message'   => 'What is the weather in London?',
    'thread_id' => 'session-abc123',  // optional — persist conversation history
]) as $chunk) {
    match ($chunk['type']) {
        'response' => print($chunk['response'] ?? ''),
        'tool'     => print("\nCalling tool: " . $chunk['tool']),
        'usage'    => print("\nUsage: " . json_encode($chunk['usage'])),
        default    => null,
    };
}
```

## Tool approvals

`tool_approval` is optional on `invokeAgent`, `invokeAgentStream`, and `invokeWorkflow`. Omit it, or send `"policy"`, to follow saved rules. `"auto_approve"` applies to this call only, and only when the API key is allowed to auto-approve actions.

When a response has `status` `"awaiting_approval"`, decide with the API key. The run resumes on the server. Do not send `decided_tool_approvals`.

```php
$client->decideToolActionApproval('WORKSPACE_ID', 'APPROVAL_ID', 'allow_once');

$pending = $client->listToolActionApprovals('WORKSPACE_ID', ['status' => 'pending']);
$one = $client->getToolActionApproval('WORKSPACE_ID', 'APPROVAL_ID');

$reply = $client->invokeAgent([
    'agent'         => 'my-agent',
    'message'       => 'Send the follow-up',
    'tool_approval' => 'auto_approve',
]);
```

`decision` is `"allow_once"` or `"deny"`. `always_allow` is only available in the dashboard.

## Multimodal (image) inputs

```php
$result = $client->invokeAgent([
    'agent'      => 'vision-agent',
    'message'    => 'Describe this image',
    'image_urls' => ['https://example.com/photo.jpg'],
]);
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

0.6.0

## License

MIT — see [LICENSE](LICENSE).

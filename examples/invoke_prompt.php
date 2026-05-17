<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FetchHive\Sdk\FetchHive;

$client = new FetchHive(['api_key' => getenv('FETCH_HIVE_API_KEY')]);

$result = $client->invokePrompt([
    'deployment' => 'my-prompt',
    'inputs'     => ['name' => 'Alice', 'topic' => 'PHP'],
]);

echo $result['response'] . PHP_EOL;

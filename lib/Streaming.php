<?php

declare(strict_types=1);

namespace FetchHive\Sdk;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Lightweight Server-Sent Events (SSE) parser for streaming Fetch Hive responses.
 *
 * Example:
 *
 *   $response = $guzzle->request('POST', '/agent/invoke', ['stream' => true, ...]);
 *   foreach (Streaming::parseSse($response->getBody()) as $event) {
 *       match ($event['type']) {
 *           'response' => print($event['response'] ?? ''),
 *           'usage'    => print("\nUsage: " . json_encode($event['usage'])),
 *           default    => null,
 *       };
 *   }
 */
final class Streaming
{
    /**
     * Parses an SSE stream and yields each decoded JSON event as an associative array.
     *
     * Stops when it encounters "data: [DONE]" or the stream is exhausted.
     * Non-data lines, blank lines, and malformed JSON are silently skipped.
     *
     * @param StreamInterface $stream  PSR-7 stream (Guzzle response body)
     * @return Generator<array<string,mixed>>
     */
    public static function parseSse(StreamInterface $stream): Generator
    {
        $buf = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(4096);
            if ($chunk === '') {
                continue;
            }
            $buf .= $chunk;

            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos), "\r");
                $buf  = substr($buf, $pos + 1);

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $payload = substr($line, 6);
                if (trim($payload) === '[DONE]') {
                    return;
                }

                $decoded = json_decode($payload, true);
                if (is_array($decoded)) {
                    yield $decoded;
                }
                // silently skip malformed JSON
            }
        }

        // Process any remaining content in the buffer
        foreach (explode("\n", $buf) as $line) {
            $line = rtrim($line, "\r");
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $payload = substr($line, 6);
            if (trim($payload) === '[DONE]') {
                return;
            }

            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                yield $decoded;
            }
        }
    }
}

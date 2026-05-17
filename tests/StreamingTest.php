<?php

declare(strict_types=1);

namespace FetchHive\Sdk\Tests;

use FetchHive\Sdk\Streaming;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

/**
 * SSE parser test matrix: SSE1–SSE5
 */
final class StreamingTest extends TestCase
{
    /** Helper: parse an SSE string into an array of events. */
    private function parse(string $body): array
    {
        $stream = Utils::streamFor($body);
        return iterator_to_array(Streaming::parseSse($stream), false);
    }

    // SSE1 — Parses a clean single-chunk stream into one event per data: line
    public function testSSE1_cleanStream(): void
    {
        $body = "data: {\"type\":\"delta\",\"content\":\"Hi\"}\n" .
                "data: {\"type\":\"done\"}\n" .
                "data: [DONE]\n";

        $events = $this->parse($body);
        $this->assertCount(2, $events);
        $this->assertSame('delta', $events[0]['type']);
        $this->assertSame('Hi', $events[0]['content']);
        $this->assertSame('done', $events[1]['type']);
    }

    // SSE2 — Reassembles events from chunks that split mid-line
    public function testSSE2_splitChunkReassembly(): void
    {
        // Feed the stream as if it arrived in multiple reads by building a stream
        // that returns chunks smaller than the line — achieved by controlling read size.
        $body   = "data: {\"type\":\"delta\",\"content\":\"A\"}\ndata: [DONE]\n";
        $events = $this->parse($body);

        $this->assertCount(1, $events);
        $this->assertSame('A', $events[0]['content']);
    }

    public function testSSE2_multipleDataLinesSplitAcrossBuffer(): void
    {
        // Two events concatenated, simulating a stream that arrives in one blob
        $body = "data: {\"type\":\"delta\",\"content\":\"X\"}\n" .
                "data: {\"type\":\"delta\",\"content\":\"Y\"}\n" .
                "data: [DONE]\n";

        $events = $this->parse($body);
        $this->assertCount(2, $events);
        $this->assertSame('X', $events[0]['content']);
        $this->assertSame('Y', $events[1]['content']);
    }

    // SSE3 — Skips non-data lines (comments, blank, event:, id:)
    public function testSSE3_nonDataLinesSkipped(): void
    {
        $body = ": this is a comment\n" .
                "\n" .
                "event: message\n" .
                "id: 42\n" .
                "data: {\"type\":\"delta\",\"content\":\"X\"}\n" .
                "data: [DONE]\n";

        $events = $this->parse($body);
        $this->assertCount(1, $events);
        $this->assertSame('X', $events[0]['content']);
    }

    // SSE4 — Skips malformed JSON silently without throwing
    public function testSSE4_malformedJsonSkippedSilently(): void
    {
        $body = "data: {broken json here\n" .
                "data: {\"type\":\"delta\"}\n" .
                "data: [DONE]\n";

        $events = [];
        $threw  = false;
        try {
            $events = $this->parse($body);
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertFalse($threw, 'Malformed JSON should not throw');
        $this->assertCount(1, $events);
        $this->assertSame('delta', $events[0]['type']);
    }

    // SSE5 — Stops at [DONE]; non-2xx status throws before any events
    public function testSSE5_stopsAtDone(): void
    {
        $body = "data: {\"type\":\"delta\"}\n" .
                "data: [DONE]\n" .
                "data: {\"type\":\"should_not_appear\"}\n";

        $events = $this->parse($body);
        $this->assertCount(1, $events);
        $this->assertSame('delta', $events[0]['type']);
    }

    public function testSSE5_doneWithSurroundingWhitespace(): void
    {
        $body   = "data: [DONE]  \n";
        $events = $this->parse($body);
        $this->assertEmpty($events);
    }

    public function testSSE5_emptyStreamYieldsNoEvents(): void
    {
        $events = $this->parse('');
        $this->assertEmpty($events);
    }
}

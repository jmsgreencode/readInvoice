<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Service;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Builds SSE responses in Datastar format.
 *
 * Datastar v1 expects Server-Sent Events with:
 *   event: datastar-merge-fragments
 *   data: fragments <html content>
 *
 * Each SSE message ends with a double newline.
 */
class DatastarResponseBuilder
{
    /**
     * Send a merge-fragments SSE response that replaces content in a target selector.
     */
    public function mergeFragments(Response $response, string $selector, string $html, string $mergeMode = 'morph'): Response
    {
        $ssePayload = $this->buildMergeFragmentEvent($selector, $html, $mergeMode);

        $response->getBody()->write($ssePayload);

        return $response
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no');
    }

    /**
     * Send multiple fragment merges in a single SSE response.
     *
     * @param array<array{selector: string, html: string, mergeMode?: string}> $fragments
     */
    public function mergeMultipleFragments(Response $response, array $fragments): Response
    {
        $payload = '';
        foreach ($fragments as $fragment) {
            $mode = $fragment['mergeMode'] ?? 'morph';
            $payload .= $this->buildMergeFragmentEvent($fragment['selector'], $fragment['html'], $mode);
        }

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no');
    }

    /**
     * Send a signal update via SSE (updates Datastar store values).
     *
     * @param array<string, mixed> $signals
     */
    public function mergeSignals(Response $response, array $signals): Response
    {
        $json = json_encode($signals, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $payload = "event: datastar-merge-signals\n";
        $payload .= "data: signals {$json}\n\n";

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no');
    }

    /**
     * Build a single merge-fragments SSE event.
     */
    private function buildMergeFragmentEvent(string $selector, string $html, string $mergeMode = 'morph'): string
    {
        // Wrap the HTML in a container with the target selector as id
        // Datastar uses the id attribute of the root element to determine the target
        $selectorId = ltrim($selector, '#');

        // Normalize the HTML: ensure whitespace doesn't break SSE data lines
        $lines = explode("\n", trim($html));
        $dataLines = [];

        // First data line: selector and merge mode
        $dataLines[] = "data: selector {$selector}";
        $dataLines[] = "data: merge {$mergeMode}";

        // Fragment data lines
        foreach ($lines as $line) {
            $dataLines[] = "data: fragments " . $line;
        }

        $event = "event: datastar-merge-fragments\n";
        $event .= implode("\n", $dataLines) . "\n\n";

        return $event;
    }
}

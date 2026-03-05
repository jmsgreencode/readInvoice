<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReadInvoice\Frontend\Service\DatastarResponseBuilder;
use Slim\Psr7\Factory\ResponseFactory;

class DatastarResponseBuilderTest extends TestCase
{
    private DatastarResponseBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DatastarResponseBuilder();
    }

    public function testMergeFragmentsReturnsSseContentType(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#test', '<div>Hello</div>');

        $this->assertSame('text/event-stream', $result->getHeaderLine('Content-Type'));
    }

    public function testMergeFragmentsContainsEventType(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#vendor-list', '<ul><li>Test</li></ul>');

        $body = (string) $result->getBody();
        $this->assertStringContainsString('event: datastar-merge-fragments', $body);
    }

    public function testMergeFragmentsContainsSelector(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#main-content', '<p>Content</p>');

        $body = (string) $result->getBody();
        $this->assertStringContainsString('data: selector #main-content', $body);
    }

    public function testMergeFragmentsContainsMergeMode(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#target', '<div>X</div>', 'inner');

        $body = (string) $result->getBody();
        $this->assertStringContainsString('data: merge inner', $body);
    }

    public function testMergeFragmentsContainsHtmlContent(): void
    {
        $html = '<div id="test"><span>Invoice Data</span></div>';
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#test', $html);

        $body = (string) $result->getBody();
        $this->assertStringContainsString('data: fragments', $body);
        $this->assertStringContainsString('Invoice Data', $body);
    }

    public function testMergeFragmentsEndsWithDoubleNewline(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#x', '<p>OK</p>');

        $body = (string) $result->getBody();
        $this->assertStringEndsWith("\n\n", $body);
    }

    public function testMergeMultipleFragments(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeMultipleFragments($response, [
            ['selector' => '#sidebar', 'html' => '<nav>Nav</nav>'],
            ['selector' => '#content', 'html' => '<main>Main</main>'],
        ]);

        $body = (string) $result->getBody();
        $this->assertSame(2, substr_count($body, 'event: datastar-merge-fragments'));
        $this->assertStringContainsString('#sidebar', $body);
        $this->assertStringContainsString('#content', $body);
    }

    public function testMergeSignals(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeSignals($response, ['loading' => false, 'count' => 5]);

        $body = (string) $result->getBody();
        $this->assertStringContainsString('event: datastar-merge-signals', $body);
        $this->assertStringContainsString('"loading":false', $body);
        $this->assertStringContainsString('"count":5', $body);
    }

    public function testNoCacheHeaderIsSet(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#x', '<p>Y</p>');

        $this->assertSame('no-cache', $result->getHeaderLine('Cache-Control'));
    }

    public function testDefaultMergeModeIsMorph(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $result = $this->builder->mergeFragments($response, '#x', '<p>Y</p>');

        $body = (string) $result->getBody();
        $this->assertStringContainsString('data: merge morph', $body);
    }
}

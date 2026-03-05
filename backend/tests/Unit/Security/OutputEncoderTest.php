<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Security\OutputEncoder;

class OutputEncoderTest extends TestCase
{
    public function testHtmlEncoding(): void
    {
        $input = '<script>alert("xss")</script>';
        $result = OutputEncoder::html($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testHtmlEncodesQuotes(): void
    {
        $result = OutputEncoder::html('"hello" & \'world\'');
        $this->assertStringNotContainsString('"hello"', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    public function testJsonEncoding(): void
    {
        $result = OutputEncoder::json(['key' => '<value>']);
        $this->assertStringNotContainsString('<value>', $result);
    }

    public function testUrlEncoding(): void
    {
        $result = OutputEncoder::url('hello world&foo=bar');
        $this->assertEquals('hello%20world%26foo%3Dbar', $result);
    }

    public function testSanitizeForDisplay(): void
    {
        $input = '<b>Hello</b> <script>alert("xss")</script>';
        $result = OutputEncoder::sanitizeForDisplay($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }
}

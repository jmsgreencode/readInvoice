<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Security\ChecksumService;
use App\Logging\StructuredLogger;
use Mockery;

class ChecksumServiceTest extends TestCase
{
    private ChecksumService $service;

    protected function setUp(): void
    {
        $logger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();
        $this->service = new ChecksumService($logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGenerateFileChecksum(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        $hash = $this->service->generateFileChecksum($tmpFile);

        $this->assertEquals(64, strlen($hash));
        $this->assertEquals(hash('sha256', 'test content'), $hash);

        unlink($tmpFile);
    }

    public function testVerifyFileChecksumValid(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        $hash = hash('sha256', 'test content');
        $this->assertTrue($this->service->verifyFileChecksum($tmpFile, $hash));

        unlink($tmpFile);
    }

    public function testVerifyFileChecksumInvalid(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        $this->assertFalse($this->service->verifyFileChecksum($tmpFile, 'wrong_hash'));

        unlink($tmpFile);
    }

    public function testGenerateFileChecksumThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->generateFileChecksum('/nonexistent/file.pdf');
    }

    public function testGenerateStringChecksum(): void
    {
        $hash = $this->service->generateStringChecksum('hello');
        $this->assertEquals(hash('sha256', 'hello'), $hash);
    }
}

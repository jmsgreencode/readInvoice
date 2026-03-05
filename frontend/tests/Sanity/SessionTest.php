<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Tests\Sanity;

use PHPUnit\Framework\TestCase;
use ReadInvoice\Frontend\Service\SessionService;

/**
 * Sanity tests for SessionService.
 *
 * Note: These tests require session support. In CLI mode, session functions
 * may behave differently. These tests verify the logic paths.
 */
class SessionTest extends TestCase
{
    private SessionService $session;

    protected function setUp(): void
    {
        // Ensure no active session carries over
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Configure session for CLI testing
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.cache_limiter', '');

        $this->session = new SessionService();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testCanStartSession(): void
    {
        $this->session->start();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testSetAndGetValue(): void
    {
        $this->session->start();
        $this->session->set('test_key', 'test_value');

        $this->assertSame('test_value', $this->session->get('test_key'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->session->start();

        $this->assertNull($this->session->get('nonexistent'));
        $this->assertSame('default', $this->session->get('nonexistent', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->session->start();
        $this->session->set('exists', true);

        $this->assertTrue($this->session->has('exists'));
        $this->assertFalse($this->session->has('does_not_exist'));
    }

    public function testRemoveDeletesKey(): void
    {
        $this->session->start();
        $this->session->set('to_remove', 'value');
        $this->session->remove('to_remove');

        $this->assertNull($this->session->get('to_remove'));
        $this->assertFalse($this->session->has('to_remove'));
    }

    public function testCsrfTokenIsGenerated(): void
    {
        $this->session->start();
        $token = $this->session->getCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testCsrfTokenIsPersistent(): void
    {
        $this->session->start();
        $token1 = $this->session->getCsrfToken();
        $token2 = $this->session->getCsrfToken();

        $this->assertSame($token1, $token2);
    }

    public function testCsrfValidationSucceeds(): void
    {
        $this->session->start();
        $token = $this->session->getCsrfToken();

        $this->assertTrue($this->session->validateCsrfToken($token));
    }

    public function testCsrfValidationFailsWithWrongToken(): void
    {
        $this->session->start();
        $this->session->getCsrfToken();

        $this->assertFalse($this->session->validateCsrfToken('invalid-token'));
    }

    public function testCsrfValidationFailsWithEmptyToken(): void
    {
        $this->session->start();

        $this->assertFalse($this->session->validateCsrfToken(''));
    }

    public function testFlashMessageSetAndRetrieve(): void
    {
        $this->session->start();
        $this->session->setFlash('msg', 'Hello World');

        $this->assertSame('Hello World', $this->session->getFlash('msg'));
    }

    public function testFlashMessageIsRemovedAfterRetrieval(): void
    {
        $this->session->start();
        $this->session->setFlash('msg', 'Temporary');

        $this->session->getFlash('msg');
        $this->assertNull($this->session->getFlash('msg'));
    }

    public function testGetFlashReturnsNullForMissingKey(): void
    {
        $this->session->start();

        $this->assertNull($this->session->getFlash('nonexistent'));
    }
}

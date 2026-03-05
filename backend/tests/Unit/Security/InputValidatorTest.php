<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use App\Security\InputValidator;
use App\Exception\ValidationException;

class InputValidatorTest extends TestCase
{
    public function testValidateEmailValid(): void
    {
        $result = InputValidator::validateEmail('test@example.com');
        $this->assertEquals('test@example.com', $result);
    }

    public function testValidateEmailInvalid(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateEmail('not-an-email');
    }

    public function testValidateStringValid(): void
    {
        $result = InputValidator::validateString('hello world', 'field', 1, 50);
        $this->assertEquals('hello world', $result);
    }

    public function testValidateStringTooShort(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateString('', 'field', 1, 50);
    }

    public function testValidateStringTooLong(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateString(str_repeat('a', 51), 'field', 1, 50);
    }

    public function testValidateIdValid(): void
    {
        $this->assertEquals(42, InputValidator::validateId('42'));
        $this->assertEquals(1, InputValidator::validateId(1));
    }

    public function testValidateIdInvalid(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateId('0');
    }

    public function testValidateIdNegative(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateId('-5');
    }

    public function testValidateDateValid(): void
    {
        $result = InputValidator::validateDate('2024-01-15', 'date');
        $this->assertEquals('2024-01-15', $result);
    }

    public function testValidateDateInvalid(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateDate('not-a-date', 'date');
    }

    public function testValidateDateInvalidDay(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::validateDate('2024-02-30', 'date');
    }

    public function testSanitizeFilename(): void
    {
        $this->assertEquals('invoice.pdf', InputValidator::sanitizeFilename('invoice.pdf'));
        $this->assertEquals('my_file_name.pdf', InputValidator::sanitizeFilename('my file name.pdf'));
        $this->assertEquals('invoice.pdf', InputValidator::sanitizeFilename('../../../etc/passwd/invoice.pdf'));
    }

    public function testValidatePagination(): void
    {
        $result = InputValidator::validatePagination(['page' => '2', 'per_page' => '50']);
        $this->assertEquals(2, $result['page']);
        $this->assertEquals(50, $result['per_page']);
        $this->assertEquals(50, $result['offset']);
    }

    public function testValidatePaginationDefaults(): void
    {
        $result = InputValidator::validatePagination([]);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(25, $result['per_page']);
        $this->assertEquals(0, $result['offset']);
    }

    public function testValidatePaginationClampsValues(): void
    {
        $result = InputValidator::validatePagination(['page' => '-1', 'per_page' => '999']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(100, $result['per_page']);
    }
}

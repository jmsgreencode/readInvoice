<?php

declare(strict_types=1);

namespace App\Security;

class OutputEncoder
{
    public static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    public static function url(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * Strip potentially dangerous content from text displayed to users.
     */
    public static function sanitizeForDisplay(string $text): string
    {
        $text = strip_tags($text);
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }
}

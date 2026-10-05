<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Raised when an AI request cannot be fulfilled. The message is safe to show to
 * the user (it never contains the API key or raw provider payloads).
 */
class AiException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('AI is not switched on yet. Add your GROQ_API_KEY to the server .env file to enable it.');
    }

    public static function requestFailed(int $status, ?string $detail = null): self
    {
        $detail = $detail ? ' ('.trim($detail).')' : '';

        return new self("The AI service returned an error [HTTP {$status}]{$detail}. Please try again.");
    }

    public static function unreachable(string $reason): self
    {
        return new self('Could not reach the AI service: '.$reason.'. Please try again.');
    }

    public static function emptyResponse(): self
    {
        return new self('The AI service returned an empty response. Please try again.');
    }
}

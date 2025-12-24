<?php

declare(strict_types=1);

namespace App\Exceptions\Url;

use Exception;

final class InvalidUrlException extends Exception
{
    public static function invalid(string $url): self
    {
        return new self("The URL '{$url}' is not valid.");
    }

    public static function invalidScheme(string $url): self
    {
        return new self("The URL '{$url}' has an invalid scheme. Only HTTP and HTTPS are allowed.");
    }

    public static function empty(): self
    {
        return new self('The URL cannot be empty.');
    }

    public static function missingScheme(string $url): self
    {
        return new self("The URL '{$url}' must have http or https scheme.");
    }

    public static function malformed(string $url): self
    {
        return new self("The URL '{$url}' is not valid.");
    }
}

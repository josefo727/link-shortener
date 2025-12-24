<?php

declare(strict_types=1);

namespace App\Exceptions\Url;

use InvalidArgumentException;

final class InvalidUrlException extends InvalidArgumentException
{
    public static function malformed(string $url): self
    {
        return new self("The URL '{$url}' is not valid.");
    }

    public static function missingScheme(string $url): self
    {
        return new self("The URL '{$url}' must have http or https scheme.");
    }

    public static function empty(): self
    {
        return new self('The URL cannot be empty.');
    }
}

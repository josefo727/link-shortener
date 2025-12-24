<?php

declare(strict_types=1);

namespace App\Exceptions\Url;

use Exception;

final class UrlNotFoundException extends Exception
{
    public static function forCode(string $code): self
    {
        return new self("No URL found for code '{$code}'.");
    }
}

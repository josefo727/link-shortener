<?php

declare(strict_types=1);

namespace App\Exceptions\Url;

use Exception;

final class CodeAlreadyExistsException extends Exception
{
    public static function forCode(string $code): self
    {
        return new self("The code '{$code}' already exists.");
    }
}

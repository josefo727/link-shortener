<?php

declare(strict_types=1);

namespace App\Contracts;

interface CodeGeneratorInterface
{
    /**
     * Generate a unique short code.
     */
    public function generate(): string;
}

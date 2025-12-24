<?php

declare(strict_types=1);

namespace App\Contracts;

interface UrlValidatorInterface
{
    /**
     * Validate if a URL is valid.
     */
    public function isValid(string $url): bool;

    /**
     * Sanitize and normalize a URL.
     */
    public function sanitize(string $url): string;

    /**
     * Validate and sanitize a URL, throwing an exception if invalid.
     *
     * @throws \App\Exceptions\Url\InvalidUrlException
     */
    public function validateAndSanitize(string $url): string;
}

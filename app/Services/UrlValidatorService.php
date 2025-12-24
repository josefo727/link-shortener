<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\UrlValidatorInterface;
use App\Exceptions\Url\InvalidUrlException;

use function trim;

final readonly class UrlValidatorService implements UrlValidatorInterface
{
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    public function isValid(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        return in_array(strtolower($parsed['scheme']), self::ALLOWED_SCHEMES, true);
    }

    public function sanitize(string $url): string
    {
        $url = trim($url);

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return $url;
        }

        $scheme = strtolower($parsed['scheme']);
        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

        // Remove trailing slash only from root (no path or just /)
        if ($path === '/') {
            $path = '';
        }

        return $scheme.'://'.$host.$port.$path.$query.$fragment;
    }

    public function validateAndSanitize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw InvalidUrlException::empty();
        }

        $parsed = parse_url($url);

        // Check for scheme first - if missing, it's likely a URL without protocol
        if ($parsed === false || ! isset($parsed['scheme'])) {
            // Could be a URL without scheme like "example.com"
            if (preg_match('/^[a-zA-Z0-9]/', $url) && ! str_contains($url, ' ')) {
                throw InvalidUrlException::missingScheme($url);
            }
            throw InvalidUrlException::malformed($url);
        }

        if (! in_array(strtolower($parsed['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw InvalidUrlException::missingScheme($url);
        }

        if (! isset($parsed['host'])) {
            throw InvalidUrlException::malformed($url);
        }

        return $this->sanitize($url);
    }
}

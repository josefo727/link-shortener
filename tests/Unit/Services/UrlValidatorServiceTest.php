<?php

declare(strict_types=1);

use App\Contracts\UrlValidatorInterface;
use App\Exceptions\Url\InvalidUrlException;
use App\Services\UrlValidatorService;

beforeEach(function (): void {
    $this->validator = new UrlValidatorService;
});

it('implements UrlValidatorInterface', function (): void {
    expect($this->validator)->toBeInstanceOf(UrlValidatorInterface::class);
});

describe('isValid', function (): void {
    it('returns true for valid http url', function (): void {
        expect($this->validator->isValid('http://example.com'))->toBeTrue();
    });

    it('returns true for valid https url', function (): void {
        expect($this->validator->isValid('https://example.com'))->toBeTrue();
    });

    it('returns true for url with path', function (): void {
        expect($this->validator->isValid('https://example.com/path/to/page'))->toBeTrue();
    });

    it('returns true for url with query string', function (): void {
        expect($this->validator->isValid('https://example.com?foo=bar&baz=qux'))->toBeTrue();
    });

    it('returns true for url with fragment', function (): void {
        expect($this->validator->isValid('https://example.com#section'))->toBeTrue();
    });

    it('returns true for url with port', function (): void {
        expect($this->validator->isValid('https://example.com:8080'))->toBeTrue();
    });

    it('returns false for empty string', function (): void {
        expect($this->validator->isValid(''))->toBeFalse();
    });

    it('returns false for url without scheme', function (): void {
        expect($this->validator->isValid('example.com'))->toBeFalse();
    });

    it('returns false for url with invalid scheme', function (): void {
        expect($this->validator->isValid('ftp://example.com'))->toBeFalse();
    });

    it('returns false for javascript url', function (): void {
        expect($this->validator->isValid('javascript:alert(1)'))->toBeFalse();
    });

    it('returns false for data url', function (): void {
        expect($this->validator->isValid('data:text/html,<script>alert(1)</script>'))->toBeFalse();
    });

    it('returns false for malformed url', function (): void {
        expect($this->validator->isValid('not a url'))->toBeFalse();
    });
});

describe('sanitize', function (): void {
    it('trims whitespace', function (): void {
        expect($this->validator->sanitize('  https://example.com  '))->toBe('https://example.com');
    });

    it('removes trailing slash from root', function (): void {
        expect($this->validator->sanitize('https://example.com/'))->toBe('https://example.com');
    });

    it('keeps trailing slash on paths', function (): void {
        expect($this->validator->sanitize('https://example.com/path/'))->toBe('https://example.com/path/');
    });

    it('lowercases the scheme', function (): void {
        expect($this->validator->sanitize('HTTPS://example.com'))->toBe('https://example.com');
    });

    it('lowercases the host', function (): void {
        expect($this->validator->sanitize('https://EXAMPLE.COM'))->toBe('https://example.com');
    });

    it('preserves path case', function (): void {
        expect($this->validator->sanitize('https://example.com/Path/To/Page'))->toBe('https://example.com/Path/To/Page');
    });

    it('preserves query string', function (): void {
        expect($this->validator->sanitize('https://example.com?Foo=Bar'))->toBe('https://example.com?Foo=Bar');
    });
});

describe('validateAndSanitize', function (): void {
    it('returns sanitized url for valid input', function (): void {
        $result = $this->validator->validateAndSanitize('  https://EXAMPLE.COM  ');

        expect($result)->toBe('https://example.com');
    });

    it('throws exception for empty url', function (): void {
        $this->validator->validateAndSanitize('');
    })->throws(InvalidUrlException::class, 'cannot be empty');

    it('throws exception for url without scheme', function (): void {
        $this->validator->validateAndSanitize('example.com');
    })->throws(InvalidUrlException::class, 'must have http or https');

    it('throws exception for malformed url', function (): void {
        $this->validator->validateAndSanitize('not a valid url');
    })->throws(InvalidUrlException::class, 'is not valid');

    it('throws exception for javascript url', function (): void {
        $this->validator->validateAndSanitize('javascript:alert(1)');
    })->throws(InvalidUrlException::class);
});

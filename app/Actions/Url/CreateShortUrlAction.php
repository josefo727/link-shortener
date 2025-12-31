<?php

declare(strict_types=1);

namespace App\Actions\Url;

use App\Contracts\CodeGeneratorInterface;
use App\Contracts\UrlValidatorInterface;
use App\DataTransferObjects\CreateUrlData;
use App\Exceptions\Url\InvalidUrlException;
use App\Models\ShortUrl;

final readonly class CreateShortUrlAction
{
    public function __construct(
        private CodeGeneratorInterface $codeGenerator,
        private UrlValidatorInterface $urlValidator
    ) {}

    /**
     * Create a new short URL.
     *
     * @throws InvalidUrlException
     */
    public function execute(CreateUrlData $data): ShortUrl
    {
        $url = $this->urlValidator->sanitize($data->originalUrl);

        if (! $this->urlValidator->isValid($url)) {
            throw InvalidUrlException::invalid($data->originalUrl);
        }

        $hash = ShortUrl::hashUrl($url);

        // Check if URL already exists
        $existing = ShortUrl::byUrlHash($hash)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Generate unique code
        $code = $this->generateUniqueCode();

        return ShortUrl::create([
            'code' => $code,
            'title' => $data->title ?? '',
            'original_url' => $url,
            'original_url_hash' => $hash,
            'status' => $data->status,
            'clicks' => 0,
            'expires_at' => $data->expiresAt,
        ]);
    }

    /**
     * Generate a unique code that doesn't exist in the database.
     */
    private function generateUniqueCode(): string
    {
        /** @var int $maxAttempts */
        $maxAttempts = config('shortener.code.max_attempts', 10);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = $this->codeGenerator->generate();

            if (! ShortUrl::byCode($code)->exists()) {
                return $code;
            }
        }

        // If we exhaust attempts, generate a longer code
        return $this->codeGenerator->generate().$this->codeGenerator->generate();
    }
}

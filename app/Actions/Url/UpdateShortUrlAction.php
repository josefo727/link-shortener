<?php

declare(strict_types=1);

namespace App\Actions\Url;

use App\Contracts\UrlValidatorInterface;
use App\DataTransferObjects\UpdateUrlData;
use App\Exceptions\Url\InvalidUrlException;
use App\Models\ShortUrl;

final readonly class UpdateShortUrlAction
{
    public function __construct(
        private UrlValidatorInterface $urlValidator
    ) {}

    /**
     * Update an existing short URL.
     *
     * @throws InvalidUrlException
     */
    public function execute(ShortUrl $shortUrl, UpdateUrlData $data): ShortUrl
    {
        $attributes = [];

        if ($data->originalUrl !== null) {
            $url = $this->urlValidator->sanitize($data->originalUrl);

            if (! $this->urlValidator->isValid($url)) {
                throw InvalidUrlException::invalid($data->originalUrl);
            }

            $attributes['original_url'] = $url;
            $attributes['original_url_hash'] = ShortUrl::hashUrl($url);
        }

        if ($data->status !== null) {
            $attributes['status'] = $data->status;
        }

        if ($data->expiresAtWasSet || $data->expiresAt !== null) {
            $attributes['expires_at'] = $data->expiresAt;
        }

        if ($attributes !== []) {
            $shortUrl->update($attributes);
        }

        return $shortUrl;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Url;

use App\Contracts\CacheServiceInterface;
use App\Exceptions\Url\UrlNotFoundException;
use App\Models\ShortUrl;

final readonly class ResolveShortUrlAction
{
    public function __construct(
        private CacheServiceInterface $cacheService
    ) {}

    /**
     * Resolve a short URL by its code.
     *
     * @throws UrlNotFoundException
     */
    public function execute(string $code): ShortUrl
    {
        $shortUrl = $this->cacheService->getByCode($code);

        if ($shortUrl === null) {
            throw UrlNotFoundException::forCode($code);
        }

        if (! $shortUrl->isAccessible()) {
            throw UrlNotFoundException::forCode($code);
        }

        $shortUrl->incrementClicks();

        return $shortUrl;
    }
}

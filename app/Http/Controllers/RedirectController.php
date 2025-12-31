<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Url\ResolveShortUrlAction;
use App\Exceptions\Url\UrlNotFoundException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class RedirectController
{
    public function __construct(
        private ResolveShortUrlAction $resolveAction
    ) {}

    public function __invoke(string $code): RedirectResponse
    {
        try {
            $shortUrl = $this->resolveAction->execute($code);

            return redirect()->away($shortUrl->original_url, 301);
        } catch (UrlNotFoundException) {
            throw new NotFoundHttpException;
        }
    }
}

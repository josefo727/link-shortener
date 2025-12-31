<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Url\CreateShortUrlAction;
use App\Actions\Url\UpdateShortUrlAction;
use App\DataTransferObjects\CreateUrlData;
use App\DataTransferObjects\UpdateUrlData;
use App\Exceptions\Url\UrlNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShortUrlRequest;
use App\Http\Requests\Api\UpdateShortUrlRequest;
use App\Http\Resources\ShortUrlResource;
use App\Models\ShortUrl;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class ShortUrlController extends Controller
{
    public function __construct(
        private readonly CreateShortUrlAction $createAction,
        private readonly UpdateShortUrlAction $updateAction,
    ) {}

    /**
     * Create a new short URL (returns existing if URL already exists).
     */
    public function store(StoreShortUrlRequest $request): JsonResponse
    {
        /** @var array{original_url: string, title?: string|null, expires_at?: string|null} $validated */
        $validated = $request->validated();

        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        $data = new CreateUrlData(
            originalUrl: $validated['original_url'],
            title: $validated['title'] ?? null,
            expiresAt: $expiresAt,
        );

        $shortUrl = $this->createAction->execute($data);

        $wasExisting = $shortUrl->wasRecentlyCreated === false;

        return (new ShortUrlResource($shortUrl))
            ->response()
            ->setStatusCode($wasExisting ? 200 : 201);
    }

    /**
     * Update an existing short URL.
     */
    public function update(UpdateShortUrlRequest $request, string $code): ShortUrlResource
    {
        $shortUrl = ShortUrl::byCode($code)->first();

        if ($shortUrl === null) {
            throw UrlNotFoundException::forCode($code);
        }

        /** @var array{original_url?: string|null, title?: string|null} $validated */
        $validated = $request->validated();

        $data = UpdateUrlData::fromArray($validated);

        $updatedUrl = $this->updateAction->execute($shortUrl, $data);

        return new ShortUrlResource($updatedUrl);
    }

    /**
     * Soft delete a short URL.
     */
    public function destroy(string $code): JsonResponse
    {
        $shortUrl = ShortUrl::byCode($code)->first();

        if ($shortUrl === null) {
            throw UrlNotFoundException::forCode($code);
        }

        $shortUrl->delete();

        return response()->json(null, 204);
    }
}

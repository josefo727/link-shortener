<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls\Pages;

use App\Actions\Url\CreateShortUrlAction;
use App\DataTransferObjects\CreateUrlData;
use App\Enums\UrlStatus;
use App\Filament\Resources\ShortUrls\ShortUrlResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateShortUrl extends CreateRecord
{
    protected static string $resource = ShortUrlResource::class;

    protected function getCreatedNotificationTitle(): string
    {
        return 'Enlace corto creado exitosamente';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var CreateShortUrlAction $action */
        $action = app(CreateShortUrlAction::class);

        $expiresAt = null;
        if (isset($data['expires_at']) && is_string($data['expires_at'])) {
            $expiresAt = Carbon::parse($data['expires_at']);
        }

        $status = UrlStatus::Active;
        if (isset($data['status']) && is_string($data['status'])) {
            $status = UrlStatus::from($data['status']);
        }

        /** @var string $originalUrl */
        $originalUrl = $data['original_url'];

        /** @var string|null $title */
        $title = $data['title'] ?? null;

        $dto = new CreateUrlData(
            originalUrl: $originalUrl,
            title: $title,
            status: $status,
            expiresAt: $expiresAt
        );

        return $action->execute($dto);
    }
}

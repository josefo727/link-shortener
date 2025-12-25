<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls\Pages;

use App\Actions\Url\UpdateShortUrlAction;
use App\DataTransferObjects\UpdateUrlData;
use App\Enums\UrlStatus;
use App\Filament\Resources\ShortUrls\ShortUrlResource;
use App\Models\ShortUrl;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditShortUrl extends EditRecord
{
    protected static string $resource = ShortUrlResource::class;

    protected function getSavedNotificationTitle(): string
    {
        return 'Enlace corto actualizado';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar'),
            ForceDeleteAction::make()
                ->label('Eliminar permanentemente'),
            RestoreAction::make()
                ->label('Restaurar'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ShortUrl $record */
        /** @var UpdateShortUrlAction $action */
        $action = app(UpdateShortUrlAction::class);

        $expiresAt = null;
        if (isset($data['expires_at']) && is_string($data['expires_at'])) {
            $expiresAt = Carbon::parse($data['expires_at']);
        }

        $status = null;
        if (isset($data['status']) && is_string($data['status'])) {
            $status = UrlStatus::from($data['status']);
        }

        /** @var string|null $originalUrl */
        $originalUrl = $data['original_url'] ?? null;

        $dto = new UpdateUrlData(
            originalUrl: $originalUrl,
            status: $status,
            expiresAt: $expiresAt,
            expiresAtWasSet: array_key_exists('expires_at', $data)
        );

        return $action->execute($record, $dto);
    }
}

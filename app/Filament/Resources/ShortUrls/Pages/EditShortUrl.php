<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls\Pages;

use App\Actions\Url\UpdateShortUrlAction;
use App\Contracts\QrCodeGeneratorInterface;
use App\DataTransferObjects\UpdateUrlData;
use App\Enums\UrlStatus;
use App\Filament\Resources\ShortUrls\ShortUrlResource;
use App\Models\ShortUrl;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EditShortUrl extends EditRecord
{
    protected static string $resource = ShortUrlResource::class;

    protected function getSavedNotificationTitle(): string
    {
        return 'Enlace corto actualizado';
    }

    protected function getHeaderActions(): array
    {
        /** @var ShortUrl $record */
        $record = $this->record;

        return [
            Action::make('download_qr_png')
                ->label('QR PNG')
                ->icon('heroicon-o-qr-code')
                ->action(function () use ($record): StreamedResponse {
                    /** @var QrCodeGeneratorInterface $generator */
                    $generator = app(QrCodeGeneratorInterface::class);
                    $content = (string) url($record->code);

                    return response()->streamDownload(
                        function () use ($generator, $content): void {
                            echo $generator->generatePng($content);
                        },
                        "qr-{$record->code}.png",
                        ['Content-Type' => 'image/png'],
                    );
                }),
            Action::make('download_qr_svg')
                ->label('QR SVG')
                ->icon('heroicon-o-qr-code')
                ->action(function () use ($record): StreamedResponse {
                    /** @var QrCodeGeneratorInterface $generator */
                    $generator = app(QrCodeGeneratorInterface::class);
                    $content = (string) url($record->code);

                    return response()->streamDownload(
                        function () use ($generator, $content): void {
                            echo $generator->generateSvg($content);
                        },
                        "qr-{$record->code}.svg",
                        ['Content-Type' => 'image/svg+xml'],
                    );
                }),
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

        /** @var string|null $title */
        $title = $data['title'] ?? null;

        $dto = new UpdateUrlData(
            originalUrl: $originalUrl,
            title: $title,
            status: $status,
            expiresAt: $expiresAt,
            expiresAtWasSet: array_key_exists('expires_at', $data),
            titleWasSet: array_key_exists('title', $data)
        );

        return $action->execute($record, $dto);
    }
}

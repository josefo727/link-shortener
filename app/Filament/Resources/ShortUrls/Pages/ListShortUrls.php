<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls\Pages;

use App\Filament\Resources\ShortUrls\ShortUrlResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListShortUrls extends ListRecords
{
    protected static string $resource = ShortUrlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Enlace'),
        ];
    }
}

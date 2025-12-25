<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls\Schemas;

use App\Enums\UrlStatus;
use App\Models\ShortUrl;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ShortUrlForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('original_url')
                    ->label('URL Original')
                    ->helperText('Ingresa la URL que deseas acortar')
                    ->required()
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull(),

                Placeholder::make('code_display')
                    ->label('Codigo')
                    ->content(fn (?ShortUrl $record): string => $record !== null ? $record->code : 'Se generara automaticamente')
                    ->visibleOn('edit'),

                Placeholder::make('short_url_display')
                    ->label('URL Corta')
                    ->content(fn (?ShortUrl $record): string => $record !== null ? (string) url($record->code) : '-')
                    ->visibleOn('edit'),

                Select::make('status')
                    ->label('Estado')
                    ->options([
                        UrlStatus::Active->value => 'Activo',
                        UrlStatus::Inactive->value => 'Inactivo',
                        UrlStatus::Expired->value => 'Expirado',
                    ])
                    ->default(UrlStatus::Active->value)
                    ->required(),

                DateTimePicker::make('expires_at')
                    ->label('Fecha de expiracion')
                    ->helperText('Dejar vacio para que no expire')
                    ->nullable(),

                Placeholder::make('clicks_display')
                    ->label('Clics')
                    ->content(fn (?ShortUrl $record): string => (string) ($record !== null ? $record->clicks : 0))
                    ->visibleOn('edit'),
            ]);
    }
}

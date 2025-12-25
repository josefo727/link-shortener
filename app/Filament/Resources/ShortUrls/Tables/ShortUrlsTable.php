<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls\Tables;

use App\Enums\UrlStatus;
use App\Models\ShortUrl;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class ShortUrlsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn (ShortUrl $record): string => $record->title),

                TextColumn::make('short_url')
                    ->label('URL Corta')
                    ->state(fn (ShortUrl $record): string => (string) url($record->code))
                    ->copyable()
                    ->copyMessage('URL copiada'),

                TextColumn::make('code')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Codigo copiado')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('original_url')
                    ->label('URL Original')
                    ->limit(50)
                    ->tooltip(fn (ShortUrl $record): string => $record->original_url)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (UrlStatus $state): string => $state->label())
                    ->color(fn (UrlStatus $state): string => match ($state) {
                        UrlStatus::Active => 'success',
                        UrlStatus::Inactive => 'warning',
                        UrlStatus::Expired => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('clicks')
                    ->label('Clics')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Nunca')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        UrlStatus::Active->value => 'Activo',
                        UrlStatus::Inactive->value => 'Inactivo',
                        UrlStatus::Expired->value => 'Expirado',
                    ]),
                TrashedFilter::make()
                    ->label('Eliminados'),
            ])
            ->recordActions([
                Action::make('copy')
                    ->label('Copiar')
                    ->icon('heroicon-o-clipboard')
                    ->action(function (ShortUrl $record): void {
                        Notification::make()
                            ->title('URL copiada')
                            ->body((string) url($record->code))
                            ->success()
                            ->send();
                    }),
                Action::make('visit')
                    ->label('Visitar')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ShortUrl $record): string => $record->original_url)
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar'),
                    ForceDeleteBulkAction::make()
                        ->label('Eliminar permanentemente'),
                    RestoreBulkAction::make()
                        ->label('Restaurar'),
                ]),
            ]);
    }
}

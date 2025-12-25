<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShortUrls;

use App\Filament\Resources\ShortUrls\Pages\CreateShortUrl;
use App\Filament\Resources\ShortUrls\Pages\EditShortUrl;
use App\Filament\Resources\ShortUrls\Pages\ListShortUrls;
use App\Filament\Resources\ShortUrls\Schemas\ShortUrlForm;
use App\Filament\Resources\ShortUrls\Tables\ShortUrlsTable;
use App\Models\ShortUrl;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

final class ShortUrlResource extends Resource
{
    protected static ?string $model = ShortUrl::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Enlaces Cortos';

    protected static ?string $modelLabel = 'Enlace Corto';

    protected static ?string $pluralModelLabel = 'Enlaces Cortos';

    protected static string|UnitEnum|null $navigationGroup = 'Gestion';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ShortUrlForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShortUrlsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShortUrls::route('/'),
            'create' => CreateShortUrl::route('/create'),
            'edit' => EditShortUrl::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<ShortUrl>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PosPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Point of Sale (POS)';

    protected static ?string $title = 'Kasir / Point of Sale (POS)';

    protected static string $view = 'filament.pages.pos-page';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return true; // Accessible by both owner and staff cashiers
    }
}

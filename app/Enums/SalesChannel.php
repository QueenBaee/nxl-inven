<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SalesChannel: string implements HasColor, HasLabel
{
    case Offline = 'offline';
    case Shopee = 'shopee';
    case Tokopedia = 'tokopedia';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Offline => 'Offline Store (POS)',
            self::Shopee => 'Shopee',
            self::Tokopedia => 'Tokopedia',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Offline => 'gray',
            self::Shopee => 'danger',
            self::Tokopedia => 'success',
        };
    }
}

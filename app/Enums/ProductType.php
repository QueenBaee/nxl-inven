<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasColor, HasLabel
{
    case Regular = 'regular';
    case Consignment = 'consignment';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Consignment => 'Consignment',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Regular => 'info',
            self::Consignment => 'warning',
        };
    }
}

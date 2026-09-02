<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::In => 'Inbound (Stock In)',
            self::Out => 'Outbound (Stock Out)',
            self::Adjustment => 'Opname Adjustment',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'danger',
            self::Adjustment => 'info',
        };
    }
}

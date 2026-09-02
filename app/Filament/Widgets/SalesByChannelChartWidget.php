<?php

namespace App\Filament\Widgets;

use App\Enums\SalesChannel;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesByChannelChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Sales by Sales Channel';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('owner') ?? false;
    }

    protected function getData(): array
    {
        $startDate = now()->subDays(30)->startOfDay();

        $channelData = Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->where('created_at', '>=', $startDate)
            ->select([
                'channel',
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(id) as total_count'),
            ])
            ->groupBy('channel')
            ->get();

        $labels = [];
        $values = [];
        $colors = [
            '#3b82f6', // blue
            '#10b981', // green
            '#f59e0b', // amber
            '#ef4444', // red
            '#8b5cf6', // purple
        ];

        foreach ($channelData as $item) {
            $enum = SalesChannel::tryFrom($item->channel);
            $labels[] = $enum ? $enum->getLabel() : ucfirst($item->channel);
            $values[] = (float) $item->total_sales;
        }

        if (empty($labels)) {
            $labels = ['Belum ada transaksi'];
            $values = [0];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gross Sales (Rp)',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

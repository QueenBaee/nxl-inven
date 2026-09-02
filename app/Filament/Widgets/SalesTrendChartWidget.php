<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesTrendChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Sales Trend (Gross Revenue)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    public ?string $filter = '30';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('owner') ?? false;
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 Days',
            '30' => 'Last 30 Days',
            '90' => 'Last 90 Days',
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? '30');
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Perform single grouped SQL query for daily sales aggregation
        $dailySales = Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(id) as transaction_count'),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total_sales', 'date')
            ->all();

        $labels = [];
        $salesData = [];

        // Build continuous date timeline
        $period = Carbon::parse($startDate)->toPeriod($endDate);
        foreach ($period as $date) {
            $formattedDate = $date->format('Y-m-d');
            $labels[] = $date->format('d M');
            $salesData[] = (float) ($dailySales[$formattedDate] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gross Sales (Rp)',
                    'data' => $salesData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

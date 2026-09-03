<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesByPaymentMethodChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Sales by Payment Method';

    protected static ?int $sort = 4;

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

        $paymentData = Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->where('created_at', '>=', $startDate)
            ->select([
                'payment_method',
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(id) as total_count'),
            ])
            ->groupBy('payment_method')
            ->get();

        $labels = [];
        $values = [];
        $colors = [
            '#10b981', // green (Cash)
            '#6366f1', // indigo (QRIS)
            '#0ea5e9', // sky (Transfer)
        ];

        foreach ($paymentData as $item) {
            $enum = $item->payment_method instanceof PaymentMethod
                ? $item->payment_method
                : PaymentMethod::tryFrom((string) $item->payment_method);

            $labels[] = $enum ? $enum->getLabel() : (is_string($item->payment_method) ? ucfirst($item->payment_method) : '-');
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
        return 'bar';
    }
}

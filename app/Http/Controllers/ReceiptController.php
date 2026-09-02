<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    /**
     * Display the thermal receipt for a completed transaction.
     */
    public function show(Request $request, Transaction $transaction): View
    {
        Gate::authorize('view', $transaction);

        $transaction->load(['items.product', 'creator']);

        return view('receipt', [
            'transaction' => $transaction,
        ]);
    }
}

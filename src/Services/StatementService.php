<?php

namespace Optima\DepotStock\Services;

use Optima\DepotStock\Models\{Client,Transaction,Invoice,Payment};
use Illuminate\Support\Carbon;

class StatementService
{
    public function buildClientStatement(int $clientId, ?string $from=null, ?string $to=null): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to   = $to   ?: now()->endOfMonth()->toDateString();

        $transactions = Transaction::where('client_id',$clientId)
            ->whereBetween('date', [$from,$to])
            ->orderBy('date')
            ->get(['date','type','delivered_20','allowance_20','notes']);

        $invoices = Invoice::where('client_id',$clientId)
            ->whereBetween('date',[$from,$to])
            ->get(['date','number','total','status']);

        $payments = Payment::where('client_id',$clientId)
            ->whereBetween('date',[$from,$to])
            ->get(['date','amount','mode']);

        return [
            'period'=>[$from,$to],
            'transactions'=>$transactions,
            'invoices'=>$invoices,
            'payments'=>$payments,
        ];
    }
}

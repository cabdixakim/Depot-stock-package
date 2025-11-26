<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Optima\DepotStock\Models\{Client,Depot,Tank,Product,Truck,Transaction};
use Optima\DepotStock\Services\VolumeCorrectionService;
use Optima\DepotStock\Services\AllowanceService;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::latest()->limit(50)->get();
        return view('depot-stock::transactions.index', compact('transactions'));
    }

    public function store(Request $request, VolumeCorrectionService $vcf, AllowanceService $allow)
    {
        $data = $request->validate([
            'type'=>'required|in:IN,OUT,ADJ',
            'client_id'=>'nullable|integer',
            'depot_id'=>'required|integer',
            'tank_id'=>'required|integer',
            'product_id'=>'required|integer',
            'truck_id'=>'nullable|integer',
            'observed_volume'=>'required|numeric|min:0',
            'temperature'=>'required|numeric',
            'density'=>'required|numeric',
            'date'=>'required|date',
            'notes'=>'nullable|string'
        ]);

        $del20 = $vcf->deliveredAt20($data['observed_volume'], $data['temperature'], $data['density']);
        $allow20 = $allow->allowanceAt20($del20, config('depot-stock.allowance_percent'));

        $data['delivered_20'] = $del20;
        $data['allowance_20'] = $data['type']==='IN' ? $allow20 : 0;

        $tx = Transaction::create($data);

        return redirect()->route('depot.transactions')->with('status','Transaction saved');
    }
}

<?php

namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Models\Load;
use Optima\DepotStock\Models\Adjustment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientExportController extends Controller
{
    public function exportOffloads(Request $req, Client $client): StreamedResponse
    {
        $rows = Offload::with(['tank.depot','product'])
            ->where('client_id', $client->id)
            ->orderByDesc('date')
            ->get();

        return $this->csv("{$client->code}_offloads.csv", [
            ['Date','Depot','Tank','Product','Delivered@20(L)','Observed(L)','Temp(°C)','Density(kg/L)','Shortfall@20','Allowance@20','Truck','Trailer','Ref'],
            ...$rows->map(function ($r) {
                return [
                    $r->date?->format('Y-m-d'),
                    $r->depot?->name,
                    optional($r->tank)->id,
                    $r->product?->name,
                    $r->delivered_20_l,
                    $r->delivered_observed_l,
                    $r->temperature_c,
                    $r->density_kg_l,
                    $r->shortfall_20_l,
                    $r->depot_allowance_20_l,
                    $r->truck_plate,
                    $r->trailer_plate,
                    $r->reference,
                ];
            })->all()
        ]);
    }

    public function exportLoads(Request $req, Client $client): StreamedResponse
    {
        $rows = Load::with(['tank.depot','product'])
            ->where('client_id', $client->id)
            ->orderByDesc('date')
            ->get();

        return $this->csv("{$client->code}_loads.csv", [
            ['Date','Depot','Tank','Product','Loaded@20(L)','Temp(°C)','Density(kg/L)','Truck','Trailer','Ref'],
            ...$rows->map(function ($r) {
                return [
                    $r->date?->format('Y-m-d'),
                    $r->depot?->name,
                    optional($r->tank)->id,
                    $r->product?->name,
                    $r->loaded_20_l,
                    $r->temperature_c,
                    $r->density_kg_l,
                    $r->truck_plate,
                    $r->trailer_plate,
                    $r->reference,
                ];
            })->all()
        ]);
    }

    public function exportAdjustments(Request $req, Client $client): StreamedResponse
    {
        $rows = Adjustment::with(['tank.depot','product'])
            ->where('client_id', $client->id)
            ->orderByDesc('date')
            ->get();

        return $this->csv("{$client->code}_adjustments.csv", [
            ['Date','Depot','Tank','Product','Volume@20(L)','Reason'],
            ...$rows->map(function ($r) {
                return [
                    $r->date?->format('Y-m-d'),
                    $r->depot?->name,
                    optional($r->tank)->id,
                    $r->product?->name,
                    $r->delivered_20,
                    $r->reference,
                ];
            })->all()
        ]);
    }

    public function paymentsIndex(Client $client)
    {
        // stub page (so the link works)
        return view('depot-stock::payments.index', compact('client'));
    }

    private function csv(string $filename, array $matrix): StreamedResponse
    {
        return response()->streamDownload(function () use ($matrix) {
            $out = fopen('php://output', 'w');
            foreach ($matrix as $row) fputcsv($out, $row);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
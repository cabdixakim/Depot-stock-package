<?php
namespace Optima\DepotStock\Http\Controllers;

use Illuminate\Routing\Controller;
use Optima\DepotStock\Models\Client;

class OperationsClientController extends Controller
{
    public function index()
    {
        $clients = Client::orderBy('name')->get();

        return view('depot-stock::operations.clients.index', [
            'clients' => $clients,
        ]);
    }
}
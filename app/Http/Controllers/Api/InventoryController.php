<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckStockRequest;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index()
    {
        return response()->json([
            'stock' => $this->inventory->index(),
        ]);
    }

    public function logs(Request $request)
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 20);

        return response()->json($this->inventory->logs($page, $perPage));
    }

    public function cleanup()
    {
        $released = $this->inventory->cleanup();

        return response()->json([
            'released' => $released,
            'message' => 'Cleanup complete',
        ]);
    }

    public function checkStock(CheckStockRequest $request)
    {
        $data = $request->validated();

        return response()->json([
            'stock' => $this->inventory->checkStock($data['charmIds']),
        ]);
    }
}

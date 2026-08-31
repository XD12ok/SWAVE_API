<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCharmRequest;
use App\Http\Requests\Api\UpdateCharmRequest;
use App\Services\CharmService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CharmController extends Controller
{
    public function __construct(protected CharmService $charms) {}

    public function index(Request $request)
    {
        $filters = [];

        if ($request->query('category')) {
            $filters['category'] = $request->query('category');
        }

        if ($request->has('active') && $request->query('active') !== null) {
            $filters['active'] = $request->query('active') === 'true' || $request->query('active') === '1';
        }

        return response()->json(['charms' => $this->charms->getCharms($filters)]);
    }

    public function store(StoreCharmRequest $request)
    {
        $data = $request->validated();

        $charm = $this->charms->createCharm($data);

        return response()->json(['charm' => $charm], Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        $charm = $this->charms->getCharmById($id);

        if (! $charm) {
            return response()->json(['message' => 'Charm not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['charm' => $charm]);
    }

    public function update(UpdateCharmRequest $request, string $id)
    {
        $data = $request->validated();

        $charm = $this->charms->updateCharm($id, $data);

        if (! $charm) {
            return response()->json(['message' => 'Charm not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['charm' => $charm]);
    }

    public function destroy(string $id)
    {
        $ok = $this->charms->deleteCharm($id);

        if (! $ok) {
            return response()->json(['message' => 'Charm not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Charm deleted']);
    }
}

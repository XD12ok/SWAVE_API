<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreKasirRequest;
use App\Services\KasirService;
use Symfony\Component\HttpFoundation\Response;

class KasirController extends Controller
{
    public function __construct(protected KasirService $kasir) {}

    public function store(StoreKasirRequest $request)
    {
        $data = $request->validated();

        $order = $this->kasir->store($data, $request->user()?->_id);

        return response()->json(['order' => $order], Response::HTTP_CREATED);
    }
}

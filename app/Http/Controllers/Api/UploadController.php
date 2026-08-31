<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadFileRequest;
use App\Services\UploadService;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function __construct(protected UploadService $upload) {}

    public function store(UploadFileRequest $request)
    {
        $data = $request->validated();

        try {
            $result = $this->upload->upload($data['file']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['image' => $result], Response::HTTP_CREATED);
    }
}

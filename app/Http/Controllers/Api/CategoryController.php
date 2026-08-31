<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCategoryRequest;
use App\Http\Requests\Api\UpdateCategoryRequest;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $categories) {}

    public function index()
    {
        return response()->json(['categories' => $this->categories->index()]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        $category = $this->categories->store($data);

        return response()->json(['category' => $category], Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        $category = $this->categories->show($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, string $id)
    {
        $data = $request->validated();

        $category = $this->categories->update($id, $data);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['category' => $category]);
    }

    public function destroy(string $id)
    {
        $ok = $this->categories->destroy($id);

        if (! $ok) {
            return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Category deleted']);
    }
}

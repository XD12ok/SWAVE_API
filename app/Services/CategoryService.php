<?php

namespace App\Services;

use App\Models\Category;

class CategoryService
{
    public function index()
    {
        return Category::orderBy('name', 'asc')->get();
    }

    public function store(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'image' => $data['image'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
    }

    public function show(string $id): ?Category
    {
        return Category::find($id);
    }

    public function update(string $id, array $data): ?Category
    {
        $category = Category::find($id);

        if (! $category) {
            return null;
        }

        $fillable = ['name', 'description', 'image', 'active'];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $category->{$field} = $data[$field];
            }
        }

        $category->updatedAt = now();
        $category->save();

        return $category->fresh();
    }

    public function destroy(string $id): bool
    {
        $category = Category::find($id);

        if (! $category) {
            return false;
        }

        return (bool) $category->delete();
    }
}

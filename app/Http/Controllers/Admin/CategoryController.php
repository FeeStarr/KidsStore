<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $parents = Category::orderBy('name')->get();
        $category = new Category();
        return view('admin.categories.form', compact('category', 'parents'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Category::create($data);
        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        $parents = Category::where('id', '!=', $category->id)
            ->whereNotIn('id', $category->descendantIds())
            ->orderBy('name')->get();
        return view('admin.categories.form', compact('category', 'parents'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $category->update($data);
        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            return back()->withErrors(['error' => 'Remove or reassign subcategories first.']);
        }
        if ($category->products()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: products are assigned to this category.']);
        }
        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $id = $category?->id;
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'slug'        => ['nullable', 'string', 'max:140', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        // Prevent self/descendant cycles
        if ($category && !empty($data['parent_id'])) {
            if ((int) $data['parent_id'] === $category->id ||
                in_array((int) $data['parent_id'], $category->descendantIds(), true)) {
                abort(422, 'A category cannot be its own parent or descendant.');
            }
        }

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        return $data;
    }
}

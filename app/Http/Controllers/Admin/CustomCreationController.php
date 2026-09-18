<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomCreation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomCreationController extends Controller
{
    public function index()
    {
        $creations = CustomCreation::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.custom-creations.index', compact('creations'));
    }

    public function create()
    {
        return view('admin.custom-creations.create', [
            'categories' => CustomCreation::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:5120|mimes:jpg,jpeg,png,webp',
            'price' => 'nullable|numeric|min:0',
            'is_price_from' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:' . implode(',', array_keys(CustomCreation::CATEGORIES)),
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $path = $request->file('image')->store('custom-creations', 'public');

        CustomCreation::create(array_merge($data, [
            'image_path' => $path,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]));

        return redirect()->route('admin.custom-creations.index')
            ->with('success', 'Custom creation added successfully.');
    }

    public function edit(CustomCreation $customCreation)
    {
        return view('admin.custom-creations.edit', [
            'creation' => $customCreation,
            'categories' => CustomCreation::CATEGORIES,
        ]);
    }

    public function update(Request $request, CustomCreation $customCreation)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:5120|mimes:jpg,jpeg,png,webp',
            'price' => 'nullable|numeric|min:0',
            'is_price_from' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:' . implode(',', array_keys(CustomCreation::CATEGORIES)),
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $oldPath = $customCreation->image_path;
            $path = $request->file('image')->store('custom-creations', 'public');
            $data['image_path'] = $path;
        }

        $data['is_active'] = $data['is_active'] ?? $customCreation->is_active;
        $data['sort_order'] = $data['sort_order'] ?? $customCreation->sort_order;

        $customCreation->update($data);

        if (isset($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('admin.custom-creations.index')
            ->with('success', 'Custom creation updated successfully.');
    }

    public function destroy(CustomCreation $customCreation)
    {
        $customCreation->deleteImage();
        $customCreation->delete();

        return redirect()->route('admin.custom-creations.index')
            ->with('success', 'Custom creation deleted successfully.');
    }

    public function toggleActive(CustomCreation $customCreation)
    {
        $customCreation->update(['is_active' => !$customCreation->is_active]);

        $status = $customCreation->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Custom creation {$status}.");
    }
}

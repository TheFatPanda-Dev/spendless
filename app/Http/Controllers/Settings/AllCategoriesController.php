<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Categories\CreateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCategoryRequest;
use App\Models\Category;
use App\Support\Categories\CategoryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AllCategoriesController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = $request->user()
            ->categories()
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/all-categories', [
            'incomeCategories' => $this->buildTree(
                $categories->where('type', 'income')->values(),
            ),
            'expenseCategories' => $this->buildTree(
                $categories->where('type', 'expense')->values(),
            ),
            'parentOptions' => [
                'income' => $this->buildParentOptions(
                    $categories->where('type', 'income')->values(),
                ),
                'expense' => $this->buildParentOptions(
                    $categories->where('type', 'expense')->values(),
                ),
            ],
            'categoryTypes' => CategoryOptions::types(),
            'categoryIcons' => CategoryOptions::icons(),
            'categoryColors' => CategoryOptions::colors(),
        ]);
    }

    public function store(
        StoreCategoryRequest $request,
        CreateCategory $createCategory,
    ): RedirectResponse {
        $this->authorize('create', Category::class);

        $createCategory($request->user(), $request->validated());

        return back()->with('success', 'Category created successfully.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $hasChildren = $category->children()->exists();

        $category->delete();

        return back()->with(
            'success',
            $hasChildren
                ? 'Category and subcategories deleted.'
                : 'Category deleted.',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $categories, ?int $parentId = null): array
    {
        return $categories
            ->filter(fn (Category $category): bool => $category->parent_id === $parentId)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'icon' => $category->icon,
                'color' => $category->color,
                'parent_id' => $category->parent_id,
                'children' => $this->buildTree($categories, $category->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildParentOptions(Collection $categories, ?int $parentId = null, int $depth = 0): array
    {
        return $categories
            ->filter(fn (Category $category): bool => $category->parent_id === $parentId)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->flatMap(function (Category $category) use ($categories, $depth): array {
                return [
                    [
                        'id' => $category->id,
                        'name' => $category->name,
                        'depth' => $depth,
                        'icon' => $category->icon,
                        'color' => $category->color,
                    ],
                    ...$this->buildParentOptions($categories, $category->id, $depth + 1),
                ];
            })
            ->values()
            ->all();
    }
}

<?php

namespace App\Livewire\Inventory;

use App\Models\ProductCategory;
use App\Services\ActivityLogger;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Product categories')]
class Categories extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $showForm = false;

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
        $this->reset('editingId', 'name', 'description');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('inventory.manage');
        $category = ProductCategory::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->showForm = true;
    }

    public function save(ActivityLogger $log): void
    {
        $this->authorize('inventory.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('product_categories', 'name')->where('company_id', app(CurrentCompany::class)->id())->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = $this->editingId ? ProductCategory::findOrFail($this->editingId) : new ProductCategory;
        $category->fill($data)->save();
        $log->log($this->editingId ? 'product_category.updated' : 'product_category.created', $category, ['new' => $data]);

        $this->notice = $this->editingId ? 'Category updated.' : 'Category created.';
        $this->closeModal();
    }

    public function delete(int $id, ActivityLogger $log): void
    {
        $this->authorize('inventory.manage');
        $category = ProductCategory::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            $this->notice = "“{$category->name}” has {$category->products_count} product(s). Move or archive them first.";

            return;
        }

        $category->delete();
        $log->log('product_category.deleted', $category);
        $this->notice = 'Category deleted.';
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        return view('livewire.inventory.categories', [
            'categories' => ProductCategory::withCount('products')->orderBy('name')->get(),
        ]);
    }
}

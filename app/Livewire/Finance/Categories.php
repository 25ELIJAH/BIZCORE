<?php

namespace App\Livewire\Finance;

use App\Exceptions\BusinessRuleException;
use App\Models\FinanceCategory;
use App\Services\FinanceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Finance categories')]
class Categories extends Component
{
    public string $notice = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $type = 'expense';

    public string $name = '';

    public bool $isActive = true;

    public function mount(): void
    {
        $this->authorize('finance.categories.manage');
    }

    public function create(): void
    {
        $this->authorize('finance.categories.manage');
        $this->resetValidation();
        $this->reset('editingId', 'name');
        $this->type = 'expense';
        $this->isActive = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('finance.categories.manage');
        $category = FinanceCategory::where('is_system', false)->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $category->id;
        $this->type = $category->type;
        $this->name = $category->name;
        $this->isActive = $category->is_active;
        $this->showForm = true;
    }

    public function save(FinanceService $finance): void
    {
        $this->authorize('finance.categories.manage');
        $this->validate(['type' => ['required', 'in:income,expense'], 'name' => ['required', 'string', 'max:100']]);

        try {
            $finance->saveCategory($this->type, $this->name, $this->editingId ? FinanceCategory::findOrFail($this->editingId) : null, $this->isActive);
        } catch (BusinessRuleException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->notice = $this->editingId ? 'Category updated.' : 'Category created.';
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        return view('livewire.finance.categories', [
            'categories' => FinanceCategory::withCount('entries')->orderBy('type')->orderBy('name')->get(),
        ]);
    }
}

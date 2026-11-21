<?php

namespace App\Livewire\Finance;

use App\Exceptions\BusinessRuleException;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Services\FinanceService;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Income and expenses')]
class Entries extends Component
{
    use WithPagination;

    #[Url]
    public string $type = 'all';

    #[Url]
    public ?int $category = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public bool $showVoided = false;

    public string $notice = '';

    // form
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $categoryId = null;

    public string $amount = '';

    public string $entryDate = '';

    public string $description = '';

    public string $reference = '';

    // void
    public ?int $voidId = null;

    public string $voidReason = '';

    public function mount(): void
    {
        $this->authorize('finance.view');
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['type', 'category', 'from', 'to', 'search', 'showVoided'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('finance.manage');
        $this->resetValidation();
        $this->reset('editingId', 'categoryId', 'amount', 'description', 'reference');
        $this->entryDate = today()->toDateString();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('finance.manage');
        $entry = FinanceEntry::active()->whereNull('source_type')->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $entry->id;
        $this->categoryId = $entry->finance_category_id;
        $this->amount = (string) $entry->amount;
        $this->entryDate = $entry->entry_date->toDateString();
        $this->description = $entry->description;
        $this->reference = (string) $entry->reference;
        $this->showForm = true;
    }

    public function save(FinanceService $finance): void
    {
        $this->authorize('finance.manage');
        $this->validate([
            'categoryId' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'entryDate' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], ['categoryId.required' => 'Choose a category.'], ['categoryId' => 'category', 'entryDate' => 'date']);

        try {
            $finance->recordManual([
                'category_id' => $this->categoryId, 'amount' => $this->amount, 'entry_date' => $this->entryDate,
                'description' => $this->description, 'reference' => $this->reference ?: null,
            ], $this->editingId ? FinanceEntry::findOrFail($this->editingId) : null);
        } catch (BusinessRuleException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->notice = $this->editingId ? 'Entry updated.' : 'Entry recorded.';
        $this->closeModals();
    }

    public function openVoid(int $id): void
    {
        $this->authorize('finance.manage');
        FinanceEntry::active()->whereNull('source_type')->findOrFail($id);
        $this->resetValidation();
        $this->voidReason = '';
        $this->voidId = $id;
    }

    public function void(FinanceService $finance): void
    {
        $this->authorize('finance.manage');
        $this->validate(['voidReason' => ['required', 'string', 'max:255']], [], ['voidReason' => 'reason']);

        try {
            $finance->voidManual(FinanceEntry::findOrFail($this->voidId), $this->voidReason);
        } catch (BusinessRuleException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        $this->notice = 'Entry voided.';
        $this->closeModals();
    }

    public function closeModals(): void
    {
        $this->showForm = false;
        $this->voidId = null;
    }

    public function render(FinanceService $finance)
    {
        $filtered = fn () => FinanceEntry::query()
            ->when(in_array($this->type, ['income', 'expense'], true), fn ($q) => $q->where('type', $this->type))
            ->when($this->category, fn ($q) => $q->where('finance_category_id', $this->category))
            ->when($this->from !== '', fn ($q) => $q->whereDate('entry_date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('entry_date', '<=', $this->to))
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)->orWhere('reference', 'like', $term));
            });

        return view('livewire.finance.entries', [
            'entries' => $filtered()->with('category')->when(! $this->showVoided, fn ($q) => $q->active())->latest('entry_date')->latest('id')->paginate(15),
            'summary' => $finance->summary($this->from ?: null, $this->to ?: null),
            'categories' => FinanceCategory::orderBy('type')->orderBy('name')->get(),
            'currency' => app(CurrentCompany::class)->get()->currency,
            'voiding' => $this->voidId ? FinanceEntry::find($this->voidId) : null,
        ]);
    }
}

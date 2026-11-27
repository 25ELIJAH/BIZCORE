<?php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Sales')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** all | completed | cancelled */
    #[Url]
    public string $status = 'all';

    /** all | unpaid | partial | paid | overdue */
    #[Url]
    public string $payment = 'all';

    #[Url]
    public ?int $customer = null;

    /** Filter by the person who recorded the sale (sales representative). */
    #[Url]
    public ?int $rep = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('sales.view');
    }

    public function updating(string $property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $sales = Sale::query()
            ->with(['customer', 'invoice', 'user:id,name'])
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->payment === 'overdue', fn ($q) => $q->overdue())
            ->when(in_array($this->payment, ['unpaid', 'partial', 'paid'], true), fn ($q) => $q->where('payment_status', $this->payment)->where('status', Sale::STATUS_COMPLETED))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->rep, fn ($q) => $q->where('user_id', $this->rep))
            ->when($this->from !== '', fn ($q) => $q->whereDate('sale_date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('sale_date', '<=', $this->to))
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->whereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', $term))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->latest('sale_date')->latest('id')
            ->paginate(15);

        return view('livewire.sales.index', [
            'sales' => $sales,
            'reps' => User::query()->whereIn('id', Sale::query()->select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::withTrashed()->orderBy('name')->get(['id', 'name']),
            'currency' => app(\App\Support\Tenancy\CurrentCompany::class)->get()->currency,
        ]);
    }
}

<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Customer profile')]
class Show extends Component
{
    use WithPagination;

    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->authorize('customers.view');
        $this->customer = $customer;
    }

    public function render()
    {
        $sales = $this->customer->sales()->with('invoice')->latest('sale_date')->latest('id')->paginate(10);

        return view('livewire.customers.show', [
            'sales' => $sales,
            'balance' => $this->customer->outstandingBalance(),
            'overdueCount' => $this->customer->sales()->overdue()->count(),
            'currency' => app(\App\Support\Tenancy\CurrentCompany::class)->get()->currency,
        ]);
    }
}

<?php

namespace App\Livewire\Customers;

use App\Livewire\PartyIndex;
use App\Models\Customer;

class Index extends PartyIndex
{
    protected function model(): string
    {
        return Customer::class;
    }

    protected function permission(): string
    {
        return 'customers';
    }

    protected function labels(): array
    {
        return ['customer', 'customers'];
    }

    protected function profileRoute(): ?string
    {
        return 'customers.show';
    }
}

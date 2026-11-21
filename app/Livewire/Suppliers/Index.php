<?php

namespace App\Livewire\Suppliers;

use App\Livewire\PartyIndex;
use App\Models\Supplier;

class Index extends PartyIndex
{
    protected function model(): string
    {
        return Supplier::class;
    }

    protected function profileRoute(): ?string
    {
        return 'suppliers.show';
    }

    protected function permission(): string
    {
        return 'suppliers';
    }

    protected function labels(): array
    {
        return ['supplier', 'suppliers'];
    }
}

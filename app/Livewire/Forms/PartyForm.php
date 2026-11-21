<?php

namespace App\Livewire\Forms;

use Illuminate\Database\Eloquent\Model;
use Livewire\Form;

/** Shared form for customers and suppliers (identical fields). */
class PartyForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $contact_person = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $tax_number = '';

    public string $notes = '';

    public bool $is_active = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function fillFrom(Model $party): void
    {
        $this->id = $party->id;
        foreach (['name', 'contact_person', 'email', 'phone', 'address', 'tax_number', 'notes'] as $field) {
            $this->{$field} = (string) $party->{$field};
        }
        $this->is_active = $party->is_active;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return collect($this->all())->except('id')
            ->map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value)
            ->put('name', trim($this->name))
            ->all();
    }
}

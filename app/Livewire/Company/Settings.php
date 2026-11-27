<?php

namespace App\Livewire\Company;

use App\Models\Company;
use App\Services\ActivityLogger;
use App\Support\Notifier;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Company administrators keep their own company profile and invoice details here. */
#[Layout('layouts.app')]
#[Title('Company')]
class Settings extends Component
{
    public string $name = '';

    public string $registration_number = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $website = '';

    public string $bank_name = '';

    public string $bank_account_name = '';

    public string $bank_account_number = '';

    public string $bank_branch = '';

    public string $invoice_note = '';

    public string $notice = '';

    /** @var array<string, bool> event key => send an email too (the bell always gets it) */
    public array $emails = [];

    private const SETTING_FIELDS = ['website', 'bank_name', 'bank_account_name', 'bank_account_number', 'bank_branch', 'invoice_note'];

    public function mount(): void
    {
        $this->authorize('manage-company');
        $company = $this->company();

        foreach (['name', 'registration_number', 'email', 'phone', 'address'] as $field) {
            $this->{$field} = (string) $company->{$field};
        }

        foreach (self::SETTING_FIELDS as $field) {
            $this->{$field} = (string) $company->setting($field, '');
        }

        foreach (array_keys(Notifier::EVENTS) as $event) {
            $this->emails[Notifier::key($event)] = Notifier::emailEnabled($company, $event);
        }
    }

    public function save(ActivityLogger $log): void
    {
        $this->authorize('manage-company');
        $this->notice = '';

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'invoice_note' => ['nullable', 'string', 'max:500'],
        ]);

        $company = $this->company();
        $settings = $company->settings ?? [];

        foreach (self::SETTING_FIELDS as $field) {
            $settings[$field] = $this->{$field} === '' ? null : $this->{$field};
        }

        foreach (array_keys(Notifier::EVENTS) as $event) {
            $settings['notifications_email'][Notifier::key($event)] = (bool) ($this->emails[Notifier::key($event)] ?? false);
        }

        $company->fill([
            'name' => $this->name,
            'registration_number' => $this->registration_number ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'settings' => $settings,
        ])->save();

        $log->log('company.updated', $company);
        $this->notice = 'Company details saved.';
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CurrentCompany::class)->id());
    }

    public function render()
    {
        return view('livewire.company.settings', [
            'welcome' => session('welcome', false),
            'hasTeam' => $this->company()->users()->count() > 1,
        ]);
    }
}

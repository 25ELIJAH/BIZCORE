<?php

namespace App\Livewire\Company;

use App\Models\Company;
use App\Services\ActivityLogger;
use App\Services\MpesaService;
use App\Support\Notifier;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rule;
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

    // KRA: the company PIN and whether it charges VAT (the rate is the one new sales start with).
    public string $kra_pin = '';

    public bool $vat_registered = false;

    public string $vat_rate = '16';

    /** @var array<string, bool> event key => send an email too (the bell always gets it) */
    public array $emails = [];

    // M-Pesa (STK push). The three secrets are write-only: they are never sent back to the page, and a blank box keeps what is saved.
    public string $mpesa_environment = 'sandbox';

    public string $mpesa_type = 'paybill';

    public string $mpesa_shortcode = '';

    public string $mpesa_consumer_key = '';

    public string $mpesa_consumer_secret = '';

    public string $mpesa_passkey = '';

    private const MPESA_SECRETS = ['consumer_key', 'consumer_secret', 'passkey'];

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

        $this->kra_pin = (string) $company->setting('kra_pin', '');
        $this->vat_registered = (bool) $company->setting('vat_registered', false);
        $this->vat_rate = (string) ($this->vat_registered ? $company->setting('tax_rate', 16) : 16);

        foreach (array_keys(Notifier::EVENTS) as $event) {
            $this->emails[Notifier::key($event)] = Notifier::emailEnabled($company, $event);
        }

        $this->mpesa_environment = (string) $company->setting('mpesa.environment', 'sandbox');
        $this->mpesa_type = (string) $company->setting('mpesa.type', 'paybill');
        $this->mpesa_shortcode = (string) $company->setting('mpesa.shortcode', '');
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
            'kra_pin' => ['nullable', 'regex:/^[AP]\d{9}[A-Z]$/i', Rule::requiredIf($this->vat_registered)],
            'vat_registered' => ['boolean'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'mpesa_environment' => ['required', 'in:sandbox,production'],
            'mpesa_type' => ['required', 'in:paybill,till'],
            'mpesa_shortcode' => ['nullable', 'digits_between:4,10'],
            'mpesa_consumer_key' => ['nullable', 'string', 'max:255'],
            'mpesa_consumer_secret' => ['nullable', 'string', 'max:255'],
            'mpesa_passkey' => ['nullable', 'string', 'max:255'],
        ]);

        $company = $this->company();
        $settings = $company->settings ?? [];

        foreach (self::SETTING_FIELDS as $field) {
            $settings[$field] = $this->{$field} === '' ? null : $this->{$field};
        }

        $settings['kra_pin'] = $this->kra_pin === '' ? null : strtoupper(trim($this->kra_pin));
        $settings['vat_registered'] = $this->vat_registered;
        $settings['tax_rate'] = $this->vat_registered ? $this->vat_rate : 0; // the rate new sales start with

        $mpesa = (array) ($settings['mpesa'] ?? []);
        $mpesa['environment'] = $this->mpesa_environment;
        $mpesa['type'] = $this->mpesa_type;
        $mpesa['shortcode'] = $this->mpesa_shortcode === '' ? null : $this->mpesa_shortcode;
        foreach (self::MPESA_SECRETS as $secret) {
            $typed = trim($this->{'mpesa_'.$secret});
            if ($typed !== '') {
                $mpesa[$secret] = MpesaService::conceal($typed); // stored encrypted, never shown again
            }
        }
        $settings['mpesa'] = $mpesa;
        $this->mpesa_consumer_key = $this->mpesa_consumer_secret = $this->mpesa_passkey = '';

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
            'mpesaSaved' => collect(self::MPESA_SECRETS)->mapWithKeys(fn ($k) => [$k => filled($this->company()->setting("mpesa.{$k}"))])->all(),
            'mpesaReady' => app(MpesaService::class)->isConfigured($this->company()),
        ]);
    }
}

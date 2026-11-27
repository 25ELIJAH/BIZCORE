<div>
    <div class="mb-6">
        <h1>Company</h1>
        <p class="mt-1 text-sm text-slate-500">Your company profile and the details printed on every invoice.</p>
    </div>

    @if ($welcome)
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Welcome to BIZCORE. Three steps to get going:</h2>
            <ol class="mt-3 space-y-2 text-sm text-slate-600">
                <li class="flex gap-2"><span class="font-semibold text-indigo-600">1.</span> Fill in your company and bank details below, so invoices look right.</li>
                <li class="flex gap-2"><span class="font-semibold text-indigo-600">2.</span> <span>Add your team under <a href="{{ route('company.team') }}" class="text-indigo-600 hover:underline">Team</a> and give each person a role.</span></li>
                <li class="flex gap-2"><span class="font-semibold text-indigo-600">3.</span> Add products, customers and suppliers from the menu, then make your first sale.</li>
            </ol>
        </section>
    @endif

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-2">
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Company profile</h2>
            @foreach ([['name', 'Company name', 'text'], ['registration_number', 'Registration number', 'text'], ['email', 'Email', 'email'], ['phone', 'Phone', 'tel'], ['address', 'Address', 'text']] as [$field, $label, $type])
                <div>
                    <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}" class="input">
                    @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </section>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Invoice details</h2>
            @foreach ([['website', 'Website'], ['bank_name', 'Bank'], ['bank_account_name', 'Account name'], ['bank_account_number', 'Account number'], ['bank_branch', 'Branch'], ['invoice_note', 'Note on invoices']] as [$field, $label])
                <div>
                    <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" type="text" wire:model="{{ $field }}" class="input">
                    @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold">Email notifications</h2>
            <p class="mt-1 text-sm text-slate-500">The bell in the header always tells the right people. Tick the events that should also be emailed to them. Who is told follows each person's role: approvers hear about leave and purchase orders, managers about sales and stock.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach (\App\Support\Notifier::EVENTS as $event => $meta)
                    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" wire:model="emails.{{ \App\Support\Notifier::key($event) }}" class="mt-0.5 rounded border-slate-300"> <span>{{ $meta['label'] }}</span></label>
                @endforeach
            </div>
        </section>

        <div class="lg:col-span-2"><button type="submit" class="btn-primary" wire:loading.attr="disabled">Save details</button></div>
    </form>
</div>

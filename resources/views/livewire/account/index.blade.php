<div>
    <div class="mb-6">
        <h1>My account</h1>
        <p class="mt-1 text-sm text-slate-500">Your sign-in details and password.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold">Signed in as</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $user->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $user->email }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-slate-500">To change your name or email, ask your administrator.</p>
            <p class="mt-3 text-sm"><span class="text-slate-500">Two-step verification:</span> <span class="font-medium {{ $user->two_factor_confirmed_at ? 'text-emerald-700' : '' }}">{{ $user->two_factor_confirmed_at ? 'On (authenticator app)' : 'Off' }}</span></p>
            @if ($user->two_factor_confirmed_at)<p class="mt-1 text-xs text-slate-500">Lost your phone? An administrator can reset it for you.</p>@endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold">Change password</h2>
            @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
            <form wire:submit="changePassword" class="space-y-4">
                <div>
                    <label for="currentPassword" class="mb-1 block text-sm font-medium">Current password</label>
                    <input id="currentPassword" type="password" wire:model="currentPassword" autocomplete="current-password" class="input">
                    @error('currentPassword')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="newPassword" class="mb-1 block text-sm font-medium">New password</label>
                    <input id="newPassword" type="password" wire:model="newPassword" autocomplete="new-password" class="input">
                    <p class="mt-1 text-xs text-slate-500">At least 8 characters, with letters and numbers.</p>
                    @error('newPassword')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="newPasswordConfirmation" class="mb-1 block text-sm font-medium">Confirm new password</label>
                    <input id="newPasswordConfirmation" type="password" wire:model="newPasswordConfirmation" autocomplete="new-password" class="input">
                    @error('newPasswordConfirmation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Change password</button>
            </form>
        </section>
    </div>
</div>

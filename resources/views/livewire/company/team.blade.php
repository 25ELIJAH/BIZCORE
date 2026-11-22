<div>
    <div class="mb-6">
        <h1>Team</h1>
        <p class="mt-1 text-sm text-slate-500">The people who can sign in to this company, and what each can do.</p>
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
    @error('team')<p class="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="px-4 py-3">Name</th><th class="px-4 py-3">Role</th><th class="px-4 py-3"></th></tr></thead>
                <tbody>
                @foreach ($members as $member)
                    <tr class="border-t border-slate-100" wire:key="member-{{ $member->id }}">
                        <td class="px-4 py-3"><div class="font-medium">{{ $member->name }}</div><div class="text-xs text-slate-500">{{ $member->email }}</div></td>
                        <td class="px-4 py-3">
                            @if ($member->is_super_admin)
                                <span class="text-slate-500">Platform administrator</span>
                            @else
                                <select class="input !py-1" wire:change="changeRole({{ $member->id }}, $event.target.value)" aria-label="Role for {{ $member->name }}">
                                    @foreach ($roles as $role)<option value="{{ $role->id }}" @selected($role->id === $member->pivot->role_id)>{{ $role->name }}</option>@endforeach
                                </select>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if (! $member->is_super_admin && $member->id !== auth()->id())
                                <button type="button" class="text-sm text-red-600 hover:underline" wire:click="remove({{ $member->id }})" wire:confirm="Remove {{ $member->name }} from this company?">Remove</button>
                            @elseif ($member->id === auth()->id())
                                <span class="text-xs text-slate-400">You</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold">Add a team member</h2>
            <form wire:submit="add" class="space-y-4">
                <div>
                    <label for="m-name" class="mb-1 block text-sm font-medium">Name</label>
                    <input id="m-name" type="text" wire:model="name" class="input">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="m-email" class="mb-1 block text-sm font-medium">Email</label>
                    <input id="m-email" type="email" wire:model="email" autocomplete="off" class="input">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="m-role" class="mb-1 block text-sm font-medium">Role</label>
                    <select id="m-role" wire:model="roleId" class="input">
                        <option value="">Choose a role</option>
                        @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                    @error('roleId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="m-password" class="mb-1 block text-sm font-medium">Starting password</label>
                    <input id="m-password" type="text" wire:model="password" autocomplete="off" class="input">
                    <p class="mt-1 text-xs text-slate-500">At least 8 characters, with letters and numbers. They can change it after signing in.</p>
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Add member</button>
            </form>
        </section>
    </div>
</div>

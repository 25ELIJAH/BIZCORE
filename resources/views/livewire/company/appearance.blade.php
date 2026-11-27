<div
    x-data="{
        busy: '',
        async pick(event, method, max, keepAlpha) {
            const file = event.target.files[0];
            event.target.value = '';
            if (! file) return;
            this.busy = method;
            try {
                const url = await this.shrink(file, max, keepAlpha && file.type === 'image/png' ? 'image/png' : 'image/jpeg');
                await $wire.call(method, url);
            } catch (e) {
                alert('That picture could not be read. Try a PNG, JPG or WebP.');
            }
            this.busy = '';
        },
        shrink(file, max, type) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                const src = URL.createObjectURL(file);
                img.onload = () => {
                    const r = Math.min(1, max / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width = Math.max(1, Math.round(img.width * r));
                    c.height = Math.max(1, Math.round(img.height * r));
                    const ctx = c.getContext('2d');
                    if (type === 'image/jpeg') { ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, c.width, c.height); }
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(src);
                    resolve(c.toDataURL(type, 0.85));
                };
                img.onerror = reject;
                img.src = src;
            });
        },
    }"
>
    <div class="mb-6">
        <h1>Settings</h1>
        <p class="mt-1 text-sm text-slate-500">Make BizCore look the way your business does. Changes save as you make them.</p>
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Logo --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Company logo</h2>
            <p class="mt-1 text-xs text-slate-500">Shown in the top bar and printed on your invoices. A square or wide picture on a clear or white background works best.</p>

            <div class="mt-4 flex items-center gap-4">
                <span class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    @if ($look['logoUrl'])
                        <img src="{{ $look['logoUrl'] }}" alt="Your company logo" class="h-full w-full object-contain p-1">
                    @else
                        <span class="text-2xl font-bold text-slate-300" aria-hidden="true">{{ mb_strtoupper(mb_substr(app(\App\Support\Tenancy\CurrentCompany::class)->get()->name, 0, 1)) }}</span>
                    @endif
                </span>
                <div class="flex flex-wrap gap-2">
                    <label class="btn-primary cursor-pointer">
                        <span x-show="busy !== 'uploadLogo'">{{ $look['hasLogo'] ? 'Change logo' : 'Upload logo' }}</span><span x-show="busy === 'uploadLogo'" x-cloak>Saving&hellip;</span>
                        <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" x-on:change="pick($event, 'uploadLogo', 512, true)">
                    </label>
                    @if ($look['hasLogo'])
                        <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?" class="btn-secondary">Remove</button>
                    @endif
                </div>
            </div>
            @error('logo')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror

            <div class="mt-5 border-t border-slate-100 pt-4">
                <x-ui.toggle label="Show my logo in the top bar" hint="Off keeps the BizCore mark there. The logo still prints on invoices." wire:model.live="showLogo" />
            </div>
        </section>

        {{-- Look and feel --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Look and feel</h2>
            <p class="mt-1 text-xs text-slate-500">Let the background show through the screens.</p>

            <div class="mt-4 space-y-5">
                <x-ui.toggle label="See-through interface" hint="The panels turn glass-like so your background shows behind them." wire:model.live="glass" />

                <div class="{{ $glass ? '' : 'opacity-50' }}">
                    <label for="level" class="mb-1 flex items-center justify-between text-sm font-medium text-slate-800">Glass strength <span class="text-xs font-normal text-slate-500">{{ $level }}%</span></label>
                    <input id="level" type="range" min="0" max="100" step="5" wire:model.live.debounce.250ms="level" class="w-full accent-indigo-600" aria-describedby="level-hint">
                    <p id="level-hint" class="mt-1 text-xs text-slate-500">Higher shows more of the background.</p>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-slate-800">Background</p>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($scenes as $key => [$label])
                            <button type="button" wire:click="chooseBackground('{{ $key }}')" aria-pressed="{{ $background === $key ? 'true' : 'false' }}"
                                    class="group overflow-hidden rounded-xl border-2 text-left transition {{ $background === $key ? 'border-indigo-600 ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-slate-300' }}">
                                <span class="block h-14 w-full bg-ink bg-cover bg-center" @if ($sceneUrls[$key]) style="background-image: url('{{ $sceneUrls[$key] }}')" @endif></span>
                                <span class="block px-2 py-1.5 text-xs font-medium text-slate-700">{{ $label }}</span>
                            </button>
                        @endforeach

                        @if ($look['hasCustomBackground'])
                            <button type="button" wire:click="chooseBackground('custom')" aria-pressed="{{ $background === 'custom' ? 'true' : 'false' }}"
                                    class="group overflow-hidden rounded-xl border-2 text-left transition {{ $background === 'custom' ? 'border-indigo-600 ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-slate-300' }}">
                                <span class="block h-14 w-full bg-cover bg-center" style="background-image: url('{{ \App\Support\Appearance::backgroundUrl('custom', now()->timestamp) }}')"></span>
                                <span class="block px-2 py-1.5 text-xs font-medium text-slate-700">Your image</span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <label class="btn-secondary cursor-pointer">
                            <span x-show="busy !== 'uploadBackground'">{{ $look['hasCustomBackground'] ? 'Change my image' : 'Use my own image' }}</span><span x-show="busy === 'uploadBackground'" x-cloak>Saving&hellip;</span>
                            <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" x-on:change="pick($event, 'uploadBackground', 1920, false)">
                        </label>
                        @if ($look['hasCustomBackground'])
                            <button type="button" wire:click="removeBackground" wire:confirm="Remove your background image?" class="text-sm text-red-600 hover:underline">Remove it</button>
                        @endif
                    </div>
                    @error('background')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Other settings live elsewhere; point to them --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold">More settings</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <a href="{{ route('company.settings') }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-brand-soft">
                    <p class="text-sm font-semibold">Company and invoices</p><p class="mt-1 text-xs text-slate-500">Profile, bank details, tax and payment methods.</p>
                </a>
                <a href="{{ route('company.settings') }}#mpesa" class="rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-brand-soft">
                    <p class="text-sm font-semibold">M-Pesa and email alerts</p><p class="mt-1 text-xs text-slate-500">Connect M-Pesa and choose which events send an email.</p>
                </a>
                <a href="{{ route('company.team') }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-brand-soft">
                    <p class="text-sm font-semibold">Team and roles</p><p class="mt-1 text-xs text-slate-500">Who can see and do what.</p>
                </a>
            </div>
        </section>
    </div>
</div>

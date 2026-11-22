@extends('layouts.guest')
@section('title', 'Set up verification')
@section('content')
<p class="mb-4 text-sm text-slate-600">Your account needs a second step. Scan this QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, Authy), then enter the 6-digit code it shows.</p>
<div class="mb-4 flex justify-center rounded-xl bg-white p-2 ring-1 ring-slate-200"><div class="h-44 w-44 [&>svg]:h-full [&>svg]:w-full">{!! $qr !!}</div></div>
<p class="mb-4 text-center text-xs text-slate-500">Cannot scan? Enter this key in your app:<br><span class="select-all font-mono text-sm font-semibold tracking-wider text-slate-800">{{ chunk_split($secret, 4, ' ') }}</span></p>
<form method="POST" action="{{ route('two-factor.confirm') }}" class="auth-form space-y-4">
    @csrf
    <div>
        <label for="code" class="mb-1 block text-sm font-medium">6-digit code</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required class="input tracking-[0.3em]" placeholder="123456">
        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="btn-primary w-full !py-2.5">Turn on verification</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-5 text-center text-sm">@csrf<button type="submit" class="text-slate-500 hover:underline">Sign out</button></form>
@endsection

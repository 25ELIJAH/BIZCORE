@extends('layouts.guest')
@section('title', 'Verification code')
@section('content')
<p class="mb-5 text-sm text-slate-600">Open your authenticator app and enter the 6-digit code for BIZCORE ERP.</p>
<form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
    @csrf
    <div>
        <label for="code" class="mb-1 block text-sm font-medium">Verification code</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus class="input tracking-[0.3em]" placeholder="123456">
        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        <p class="mt-2 text-xs text-slate-500">Lost your phone? Enter one of your recovery codes instead (like <span class="font-mono">abcde-fghij</span>).</p>
    </div>
    <button type="submit" class="btn-primary w-full !py-2.5">Verify</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-5 text-center text-sm">@csrf<button type="submit" class="text-slate-500 hover:underline">Sign out</button></form>
@endsection

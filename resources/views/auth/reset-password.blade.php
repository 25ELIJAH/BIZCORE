@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label for="email" class="mb-1 block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required
               class="input">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="mb-1 block text-sm font-medium">New password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password"
               class="input">
        <p class="mt-1 text-xs text-slate-500">At least 8 characters with letters and numbers.</p>
        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
               class="input">
    </div>
    <button type="submit" class="btn-primary w-full !py-2.5">Reset password</button>
</form>
@endsection

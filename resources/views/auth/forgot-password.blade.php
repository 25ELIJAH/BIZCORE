@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
<p class="mb-4 text-sm text-slate-600">Enter your email and we will send you a link to reset your password.</p>
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="mb-1 block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Send reset link</button>
    <a href="{{ route('login') }}" class="block text-center text-sm text-indigo-600 hover:underline">Back to sign in</a>
</form>
@endsection

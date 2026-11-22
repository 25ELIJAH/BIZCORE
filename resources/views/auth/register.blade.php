@extends('layouts.guest')
@section('title', 'Create your company')
@section('heading', 'Create your company')
@section('subheading', 'Set up BIZCORE for your business in a minute. You become its administrator.')
@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <div class="hidden" aria-hidden="true"><label>Leave this empty <input type="text" name="contact_url" tabindex="-1" autocomplete="off"></label></div>
    <div>
        <label for="company_name" class="mb-1 block text-sm font-medium">Company name</label>
        <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required autofocus autocomplete="organization" class="input">
        @error('company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="name" class="mb-1 block text-sm font-medium">Your name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" class="input">
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="email" class="mb-1 block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="input">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="phone" class="mb-1 block text-sm font-medium">Phone <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" class="input">
        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="mb-1 block text-sm font-medium">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
        <p class="mt-1 text-xs text-slate-500">At least 8 characters, with letters and numbers.</p>
        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
    </div>
    <button type="submit" class="btn-primary w-full !py-2.5">Create company</button>
    <p class="text-center text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Sign in</a></p>
</form>
@endsection

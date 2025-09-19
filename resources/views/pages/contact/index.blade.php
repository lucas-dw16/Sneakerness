@extends('layouts.app')
@section('title','Contact - '.config('app.name'))
@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
    <h1 class="text-3xl font-bold mb-6">Contact</h1>
    <p class="text-zinc-300 mb-10">Heb je een vraag over events, stands of samenwerkingen? Vul het formulier in en we nemen snel contact op.</p>
    <form method="POST" action="{{ route('contact.submit') }}" class="space-y-6">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Naam <span class="text-rose-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-md bg-zinc-900 border border-zinc-700 focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2" />
            @error('name')<p class="text-rose-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">E-mail <span class="text-rose-500">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-md bg-zinc-900 border border-zinc-700 focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2" />
            @error('email')<p class="text-rose-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Onderwerp <span class="text-rose-500">*</span></label>
            <input type="text" name="subject" value="{{ old('subject') }}" required class="w-full rounded-md bg-zinc-900 border border-zinc-700 focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2" />
            @error('subject')<p class="text-rose-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Bericht <span class="text-rose-500">*</span></label>
            <textarea name="message" rows="6" required class="w-full rounded-md bg-zinc-900 border border-zinc-700 focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2">{{ old('message') }}</textarea>
            @error('message')<p class="text-rose-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="pt-2">
            <button class="inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-500 px-5 py-2.5 font-medium transition">Verstuur</button>
        </div>
    </form>
</div>
@endsection

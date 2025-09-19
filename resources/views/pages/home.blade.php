@extends('layouts.app')
@section('title','Home - '.config('app.name'))

@section('content')
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-indigo-700/10 via-zinc-900 to-zinc-950 pointer-events-none"></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 relative">
        <div class="max-w-2xl">
            <h1 class="text-4xl sm:text-5xl font-bold leading-tight tracking-tight mb-6">Experience the Culture of Sneaker Events</h1>
            <p class="text-lg text-zinc-300 mb-8">Bekijk onze upcoming events, registreer je stand als vendor of neem contact op voor partnerships en community mogelijkheden.</p>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('events.index') }}" class="inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-500 px-6 py-3 font-medium transition">Bekijk Events</a>
                <a href="{{ route('contact.show') }}" class="inline-flex items-center rounded-md border border-zinc-700 hover:border-indigo-500 px-6 py-3 font-medium transition">Contact</a>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
    <h2 class="text-2xl font-semibold mb-8">Laatste Published Events</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($events as $event)
            <a href="{{ route('events.show',$event->slug) }}" class="group rounded-lg border border-zinc-800 hover:border-indigo-500 bg-zinc-900/40 p-5 flex flex-col gap-3 transition">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span>{{ $event->starts_at->format('d M Y') }}</span>
                    <span class="px-2 py-0.5 rounded bg-indigo-600/20 text-indigo-300 text-[11px] uppercase tracking-wide">{{ $event->status }}</span>
                </div>
                <h3 class="text-lg font-medium group-hover:text-indigo-400 transition">{{ $event->name }}</h3>
                <p class="text-sm text-zinc-400 line-clamp-3">{{ Str::limit(strip_tags($event->description),130) }}</p>
                <div class="mt-auto pt-2 text-sm text-indigo-400 flex items-center gap-1">Bekijk <span aria-hidden>→</span></div>
            </a>
        @empty
            <p class="text-zinc-400 col-span-full">Nog geen events beschikbaar.</p>
        @endforelse
    </div>
</section>
@endsection
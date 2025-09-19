@extends('layouts.app')
@section('title','Events - '.config('app.name'))
@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14">
    <h1 class="text-3xl font-bold mb-8">Events</h1>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($events as $event)
            <a href="{{ route('events.show',$event->slug) }}" class="group rounded-lg border border-zinc-800 hover:border-indigo-500 bg-zinc-900/40 p-5 flex flex-col gap-3 transition">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span>{{ $event->starts_at->format('d M Y') }}</span>
                    <span class="px-2 py-0.5 rounded bg-indigo-600/20 text-indigo-300 text-[11px] uppercase tracking-wide">{{ $event->status }}</span>
                </div>
                <h2 class="text-lg font-medium group-hover:text-indigo-400 transition">{{ $event->name }}</h2>
                <p class="text-sm text-zinc-400 line-clamp-3">{{ Str::limit(strip_tags($event->description),130) }}</p>
                <div class="mt-auto pt-2 text-sm text-indigo-400 flex items-center gap-1">Bekijk <span aria-hidden>→</span></div>
            </a>
        @empty
            <p class="text-zinc-400 col-span-full">Geen events gevonden.</p>
        @endforelse
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', $event->name.' - '.config('app.name'))
@section('content')
<article class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-14">
    <a href="{{ route('events.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Terug naar events</a>
    <header class="mt-4 mb-8">
        <h1 class="text-4xl font-bold tracking-tight mb-4">{{ $event->name }}</h1>
        <div class="flex flex-wrap gap-4 text-sm text-zinc-400">
            <div>{{ $event->starts_at->format('d M Y H:i') }} - {{ $event->ends_at->format('d M Y H:i') }}</div>
            @if($event->location)
                <div>Locatie: <span class="text-zinc-300">{{ $event->location }}</span></div>
            @endif
            @if($event->capacity)
                <div>Capaciteit: <span class="text-zinc-300">{{ number_format($event->capacity) }}</span></div>
            @endif
        </div>
    </header>
    <div class="prose prose-invert max-w-none">
        {!! $event->description !!}
    </div>
    <div class="mt-12">
        <a href="{{ route('contact.show') }}" class="inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-500 px-5 py-2.5 font-medium transition">Interesse? Neem contact op</a>
    </div>
</article>
@endsection
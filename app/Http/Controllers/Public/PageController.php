<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function home()
    {
        $events = Event::query()
            ->where('status','published')
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
        return view('pages.home', compact('events'));
    }

    public function events()
    {
        $events = Event::query()
            ->where('status','published')
            ->orderBy('starts_at')
            ->paginate(12);
        return view('pages.events.index', compact('events'));
    }

    public function eventShow(string $slug)
    {
        $event = Event::where('slug',$slug)->where('status','published')->firstOrFail();
        return view('pages.events.show', compact('event'));
    }
}

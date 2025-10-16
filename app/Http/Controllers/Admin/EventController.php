<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * EventController - Handles all Event CRUD operations and business logic.
 * 
 * Separates business logic from Filament UI components following MVC pattern.
 * Used by Filament Resources and potentially API endpoints.
 */
class EventController extends Controller
{
    /**
     * Display a listing of events with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Database\Eloquent\Collection
     */
    public function index(Request $request)
    {
        $query = Event::query();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }

        $events = $query->with(['stands', 'tickets'])
                       ->orderBy('start_date')
                       ->get();

        return $request->wantsJson() ? response()->json($events) : $events;
    }

    /**
     * Show a single event with related data.
     *
     * @param Event $event
     * @return Event
     */
    public function show(Event $event)
    {
        return $event->load(['stands.vendor', 'tickets.user']);
    }

    /**
     * Store a newly created event.
     *
     * @param Request $request
     * @return JsonResponse|Event
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'required|string|max:255',
            'max_capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Auto-generate slug if not provided
        if (!isset($data['slug']) || empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        $event = Event::create($data);

        return $request->wantsJson() 
            ? response()->json($event, 201)
            : $event;
    }

    /**
     * Update an existing event.
     *
     * @param Request $request
     * @param Event $event
     * @return JsonResponse|Event
     */
    public function update(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'location' => 'sometimes|string|max:255',
            'max_capacity' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:draft,published,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Update slug if name changes
        if (isset($data['name']) && $data['name'] !== $event->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $event->id);
        }

        $event->update($data);

        return $request->wantsJson() 
            ? response()->json($event)
            : $event;
    }

    /**
     * Delete an event (with business logic checks).
     *
     * @param Event $event
     * @return JsonResponse|array
     */
    public function destroy(Event $event)
    {
        // Business logic: Check if event has sold tickets
        if ($event->tickets()->where('status', 'paid')->exists()) {
            return response()->json([
                'error' => 'Cannot delete event with paid tickets'
            ], 422);
        }

        // Soft delete or hard delete based on business rules
        if ($event->tickets()->exists() || $event->stands()->exists()) {
            // Soft delete if has related data
            $event->update(['status' => 'cancelled']);
            $message = 'Event marked as cancelled due to existing relations';
        } else {
            // Hard delete if no relations
            $event->delete();
            $message = 'Event deleted successfully';
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Publish an event (change status to published).
     *
     * @param Event $event
     * @return JsonResponse
     */
    public function publish(Event $event)
    {
        // Business validation
        if ($event->status === 'published') {
            return response()->json([
                'error' => 'Event is already published'
            ], 422);
        }

        if (!$event->stands()->exists()) {
            return response()->json([
                'error' => 'Cannot publish event without stands'
            ], 422);
        }

        $event->update(['status' => 'published']);

        return response()->json([
            'message' => 'Event published successfully',
            'event' => $event
        ]);
    }

    /**
     * Get event statistics.
     *
     * @param Event $event
     * @return JsonResponse
     */
    public function statistics(Event $event)
    {
        $stats = [
            'total_stands' => $event->stands()->count(),
            'occupied_stands' => $event->stands()->whereHas('vendor')->count(),
            'total_tickets_sold' => $event->tickets()->where('status', 'paid')->sum('quantity'),
            'total_revenue' => $event->tickets()->where('status', 'paid')->sum('total_price'),
            'pending_tickets' => $event->tickets()->where('status', 'pending')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Generate a unique slug for the event.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return string
     */
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Event::where('slug', $slug)
                   ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                   ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get events by status for dashboard widgets.
     *
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByStatus(string $status)
    {
        return Event::where('status', $status)
                   ->with(['stands', 'tickets'])
                   ->orderBy('start_date')
                   ->get();
    }

    /**
     * Get upcoming events for public display.
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUpcoming(int $limit = 10)
    {
        return Event::where('status', 'published')
                   ->where('start_date', '>=', now())
                   ->orderBy('start_date')
                   ->limit($limit)
                   ->get();
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * TicketController - Handles all Ticket CRUD operations and business logic.
 * 
 * Manages ticket purchases, authorization checks, pricing calculations,
 * and ticket-specific business rules following MVC pattern.
 */
class TicketController extends Controller
{
    /**
     * Get detailed ticket information using JOINs.
     * 
     * Demonstratie van JOIN queries zoals gevraagd door docent.
     *
     * @param Request $request  
     * @return JsonResponse
     */
    public function getTicketsWithDetails(Request $request)
    {
        // JOIN query om tickets te krijgen met event en user informatie
        $query = DB::table('tickets')
            ->select([
                'tickets.*',
                'events.name as event_name',
                'events.start_date',
                'events.end_date',
                'events.location as event_location',
                'users.name as user_name',
                'users.email as user_email'
            ])
            ->join('events', 'tickets.event_id', '=', 'events.id')
            ->join('users', 'tickets.user_id', '=', 'users.id');

        // Filter op event indien opgegeven
        if ($request->filled('event_id')) {
            $query->where('events.id', $request->event_id);
        }

        // Filter op status
        if ($request->filled('status')) {
            $query->where('tickets.status', $request->status);
        }

        // Filter op ticket type
        if ($request->filled('type')) {
            $query->where('tickets.type', $request->type);
        }

        $tickets = $query->orderBy('events.start_date')
                        ->orderBy('tickets.created_at')
                        ->get();

        return response()->json([
            'message' => 'Tickets retrieved with JOIN queries',
            'data' => $tickets,
            'total_count' => $tickets->count()
        ]);
    }

    /**
     * Get event revenue analysis using complex JOINs.
     * 
     * Nog een voorbeeld van JOIN met aggregatie functies.
     *
     * @return JsonResponse
     */
    public function getEventRevenueAnalysis()
    {
        // JOIN met aggregatie voor event revenue analysis
        $analysis = DB::table('events')
            ->select([
                'events.id',
                'events.name',
                'events.start_date',
                'events.location',
                DB::raw('COUNT(tickets.id) as total_tickets'),
                DB::raw('SUM(tickets.quantity) as total_quantity'),
                DB::raw('SUM(tickets.total_price) as total_revenue'),
                DB::raw('AVG(tickets.unit_price) as avg_ticket_price'),
                DB::raw('COUNT(DISTINCT tickets.user_id) as unique_buyers'),
                DB::raw('COUNT(CASE WHEN tickets.status = "confirmed" THEN 1 END) as confirmed_tickets'),
                DB::raw('COUNT(CASE WHEN tickets.status = "pending" THEN 1 END) as pending_tickets')
            ])
            ->leftJoin('tickets', 'events.id', '=', 'tickets.event_id')
            ->groupBy('events.id', 'events.name', 'events.start_date', 'events.location')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return response()->json([
            'message' => 'Event revenue analysis with complex JOINs',
            'data' => $analysis
        ]);
    }

    /**
     * Get ticket sales statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSalesStatistics(Request $request)
    {
        $query = Ticket::query();

        // Apply filters
        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = [
            'total_tickets' => $query->count(),
            'total_revenue' => $query->sum('total_price'),
            'average_price' => $query->avg('unit_price'),
            'total_quantity' => $query->sum('quantity'),
            'by_status' => Ticket::selectRaw('status, COUNT(*) as count, SUM(total_price) as revenue')
                                 ->groupBy('status')
                                 ->get(),
            'by_type' => Ticket::selectRaw('type, COUNT(*) as count, SUM(total_price) as revenue')
                               ->groupBy('type')
                               ->get(),
        ];

        return response()->json($stats);
    }
    /**
     * Display a listing of tickets with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Database\Eloquent\Collection
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['user', 'event']);

        // Apply role-based filtering
        $user = Auth::user();
        if ($user && !$user->hasAnyRole(['admin', 'support'])) {
            $query->where('user_id', $user->id);
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        return $request->wantsJson() ? response()->json($tickets) : $tickets;
    }

    /**
     * Show a single ticket with related data.
     *
     * @param Ticket $ticket
     * @return Ticket|JsonResponse
     */
    public function show(Ticket $ticket)
    {
        // Authorization check
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'support']) && $ticket->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $ticket->load(['user', 'event']);
    }

    /**
     * Store a newly created ticket purchase.
     *
     * @param Request $request
     * @return JsonResponse|Ticket
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'quantity' => 'required|integer|min:1|max:10',
            'unit_price' => 'required|numeric|min:0',
            'type' => 'required|in:regular,vip',
            'user_id' => 'nullable|exists:users,id', // Admin can specify user
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Set user_id if not provided (current user)
        if (!isset($data['user_id'])) {
            $data['user_id'] = Auth::id();
        }

        // Business validation
        $event = Event::find($data['event_id']);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        if ($event->status !== 'published') {
            return response()->json(['error' => 'Event is not available for ticket sales'], 422);
        }

        // Check capacity (if event has max_capacity)
        if ($event->max_capacity) {
            $existingTickets = $event->tickets()->where('status', 'paid')->sum('quantity');
            if (($existingTickets + $data['quantity']) > $event->max_capacity) {
                return response()->json([
                    'error' => 'Not enough tickets available',
                    'available' => $event->max_capacity - $existingTickets
                ], 422);
            }
        }

        // Set default status (non-admin users get pending)
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'support'])) {
            $data['status'] = 'pending';
        } else {
            $data['status'] = $data['status'] ?? 'pending';
        }

        // Calculate total price (will be recalculated by model)
        $data['total_price'] = $data['quantity'] * $data['unit_price'];

        $ticket = Ticket::create($data);

        Log::info("Ticket purchase created", [
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'event_id' => $ticket->event_id,
            'quantity' => $ticket->quantity,
            'total_price' => $ticket->total_price
        ]);

        return $request->wantsJson() 
            ? response()->json($ticket->load(['user', 'event']), 201)
            : $ticket;
    }

    /**
     * Update an existing ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse|Ticket
     */
    public function update(Request $request, Ticket $ticket)
    {
        // Authorization check
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'support'])) {
            if ($ticket->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            if ($ticket->status !== 'pending') {
                return response()->json(['error' => 'Can only edit pending tickets'], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'sometimes|integer|min:1|max:10',
            'unit_price' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:regular,vip',
            'status' => 'sometimes|in:pending,paid,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Business logic: Only admin/support can change status
        if (isset($data['status']) && !$user->hasAnyRole(['admin', 'support'])) {
            unset($data['status']);
        }

        // Prevent changing paid tickets (except by admin)
        if ($ticket->status === 'paid' && !$user->hasAnyRole(['admin'])) {
            return response()->json(['error' => 'Cannot modify paid tickets'], 422);
        }

        $ticket->update($data);

        Log::info("Ticket updated", [
            'ticket_id' => $ticket->id,
            'updated_by' => $user->id,
            'changes' => $data
        ]);

        return $request->wantsJson() 
            ? response()->json($ticket->load(['user', 'event']))
            : $ticket;
    }

    /**
     * Delete a ticket (with business logic checks).
     *
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function destroy(Ticket $ticket)
    {
        $user = Auth::user();

        // Authorization check
        if (!$user->hasAnyRole(['admin', 'support'])) {
            if ($ticket->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            if ($ticket->status === 'paid') {
                return response()->json(['error' => 'Cannot delete paid tickets'], 422);
            }
        }

        // Business logic: Paid tickets can only be cancelled, not deleted
        if ($ticket->status === 'paid') {
            $ticket->update(['status' => 'cancelled']);
            $message = 'Ticket cancelled (was paid)';
        } else {
            $ticket->delete();
            $message = 'Ticket deleted successfully';
        }

        Log::info("Ticket removed", [
            'ticket_id' => $ticket->id,
            'action' => $ticket->status === 'paid' ? 'cancelled' : 'deleted',
            'by_user' => $user->id
        ]);

        return response()->json(['message' => $message]);
    }

    /**
     * Mark ticket as paid.
     *
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function markAsPaid(Ticket $ticket)
    {
        $user = Auth::user();
        
        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($ticket->status === 'paid') {
            return response()->json(['error' => 'Ticket is already paid'], 422);
        }

        if ($ticket->status === 'cancelled') {
            return response()->json(['error' => 'Cannot mark cancelled ticket as paid'], 422);
        }

        $ticket->update(['status' => 'paid']);

        Log::info("Ticket marked as paid", [
            'ticket_id' => $ticket->id,
            'by_user' => $user->id
        ]);

        return response()->json([
            'message' => 'Ticket marked as paid',
            'ticket' => $ticket->load(['user', 'event'])
        ]);
    }

    /**
     * Cancel a ticket.
     *
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function cancel(Ticket $ticket)
    {
        $user = Auth::user();

        // Authorization check
        if (!$user->hasAnyRole(['admin', 'support']) && $ticket->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($ticket->status === 'cancelled') {
            return response()->json(['error' => 'Ticket is already cancelled'], 422);
        }

        $ticket->update(['status' => 'cancelled']);

        Log::info("Ticket cancelled", [
            'ticket_id' => $ticket->id,
            'by_user' => $user->id
        ]);

        return response()->json([
            'message' => 'Ticket cancelled successfully',
            'ticket' => $ticket->load(['user', 'event'])
        ]);
    }

    /**
     * Get ticket sales statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request)
    {
        $query = Ticket::query();

        // Apply date filters if provided
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = [
            'total_tickets' => $query->sum('quantity'),
            'total_revenue' => $query->where('status', 'paid')->sum('total_price'),
            'pending_revenue' => $query->where('status', 'pending')->sum('total_price'),
            'by_status' => $query->selectRaw('status, SUM(quantity) as count, SUM(total_price) as revenue')
                                ->groupBy('status')
                                ->get()
                                ->keyBy('status'),
            'by_type' => $query->selectRaw('type, SUM(quantity) as count, SUM(total_price) as revenue')
                              ->groupBy('type')
                              ->get()
                              ->keyBy('type'),
            'by_event' => $query->with('event:id,name')
                               ->selectRaw('event_id, SUM(quantity) as count, SUM(total_price) as revenue')
                               ->groupBy('event_id')
                               ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Get tickets for a specific event.
     *
     * @param Event $event
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByEvent(Event $event)
    {
        return $event->tickets()->with(['user'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get tickets for a specific user.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByUser(User $user)
    {
        return $user->tickets()->with(['event'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Calculate pricing for ticket purchase (used by frontend).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculatePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $quantity = $request->quantity;
        $unitPrice = $request->unit_price;
        $totalPrice = round($quantity * $unitPrice, 2);

        return response()->json([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'formatted_total' => '€' . number_format($totalPrice, 2, ',', '.')
        ]);
    }
}
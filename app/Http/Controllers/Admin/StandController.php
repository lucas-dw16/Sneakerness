<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stand;
use App\Models\Event;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * StandController - Handles all Stand CRUD operations and business logic.
 * 
 * Manages event stands, vendor assignments, capacity management,
 * and stand-specific business rules following MVC pattern.
 */
class StandController extends Controller
{
    /**
     * Display a listing of stands with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Database\Eloquent\Collection
     */
    public function index(Request $request)
    {
        $query = Stand::with(['event', 'vendor']);

        // Apply role-based filtering
        $user = Auth::user();
        if ($user && $user->hasAnyRole(['verkoper', 'contactpersoon'])) {
            // Limit to user's vendor stands
            if ($user->vendor) {
                $query->where('vendor_id', $user->vendor->id);
            } elseif ($user->contactPerson) {
                $query->where('vendor_id', $user->contactPerson->vendor_id);
            } else {
                // User has role but no vendor association - show nothing
                $query->whereRaw('1=0');
            }
        }

        // Apply filters
        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('available')) {
            if ($request->boolean('available')) {
                $query->whereNull('vendor_id');
            } else {
                $query->whereNotNull('vendor_id');
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('number', 'like', '%' . $request->search . '%')
                  ->orWhere('location', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $stands = $query->orderBy('number')->get();

        return $request->wantsJson() ? response()->json($stands) : $stands;
    }

    /**
     * Show a single stand with related data.
     *
     * @param Stand $stand
     * @return Stand|JsonResponse
     */
    public function show(Stand $stand)
    {
        // Authorization check for vendor roles
        $user = Auth::user();
        if ($user && $user->hasAnyRole(['verkoper', 'contactpersoon'])) {
            if ($user->vendor && $stand->vendor_id !== $user->vendor->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            } elseif ($user->contactPerson && $stand->vendor_id !== $user->contactPerson->vendor_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        return $stand->load(['event', 'vendor.contactPersons']);
    }

    /**
     * Store a newly created stand.
     *
     * @param Request $request
     * @return JsonResponse|Stand
     */
    public function store(Request $request)
    {
        // Only admin and support can create stands
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'number' => 'required|string|max:50',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'size' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'vendor_id' => 'nullable|exists:vendors,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Business validation: Check if stand number is unique for this event
        $standExists = Stand::where('event_id', $data['event_id'])
                           ->where('number', $data['number'])
                           ->exists();

        if ($standExists) {
            return response()->json([
                'error' => 'Stand number already exists for this event'
            ], 422);
        }

        // Check if vendor is available (not already assigned to another stand in this event)
        if ($data['vendor_id']) {
            $vendorHasStand = Stand::where('event_id', $data['event_id'])
                                  ->where('vendor_id', $data['vendor_id'])
                                  ->exists();

            if ($vendorHasStand) {
                return response()->json([
                    'error' => 'Vendor is already assigned to another stand in this event'
                ], 422);
            }
        }

        $stand = Stand::create($data);

        Log::info("Stand created", [
            'stand_id' => $stand->id,
            'event_id' => $stand->event_id,
            'number' => $stand->number,
            'vendor_assigned' => !is_null($stand->vendor_id),
            'created_by' => $user->id
        ]);

        return $request->wantsJson() 
            ? response()->json($stand->load(['event', 'vendor']), 201)
            : $stand;
    }

    /**
     * Update an existing stand.
     *
     * @param Request $request
     * @param Stand $stand
     * @return JsonResponse|Stand
     */
    public function update(Request $request, Stand $stand)
    {
        $user = Auth::user();

        // Authorization check
        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'number' => 'sometimes|string|max:50',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'size' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'vendor_id' => 'nullable|exists:vendors,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Business validation: Check if new stand number is unique for this event
        if (isset($data['number'])) {
            $standExists = Stand::where('event_id', $stand->event_id)
                               ->where('number', $data['number'])
                               ->where('id', '!=', $stand->id)
                               ->exists();

            if ($standExists) {
                return response()->json([
                    'error' => 'Stand number already exists for this event'
                ], 422);
            }
        }

        // Check vendor availability if changing vendor assignment
        if (isset($data['vendor_id']) && $data['vendor_id'] !== $stand->vendor_id) {
            if ($data['vendor_id']) {
                $vendorHasStand = Stand::where('event_id', $stand->event_id)
                                      ->where('vendor_id', $data['vendor_id'])
                                      ->where('id', '!=', $stand->id)
                                      ->exists();

                if ($vendorHasStand) {
                    return response()->json([
                        'error' => 'Vendor is already assigned to another stand in this event'
                    ], 422);
                }
            }
        }

        $oldVendorId = $stand->vendor_id;
        $stand->update($data);

        Log::info("Stand updated", [
            'stand_id' => $stand->id,
            'updated_by' => $user->id,
            'changes' => array_keys($data),
            'vendor_changed' => isset($data['vendor_id']) && $data['vendor_id'] !== $oldVendorId
        ]);

        return $request->wantsJson() 
            ? response()->json($stand->load(['event', 'vendor']))
            : $stand;
    }

    /**
     * Delete a stand (with business logic checks).
     *
     * @param Stand $stand
     * @return JsonResponse
     */
    public function destroy(Stand $stand)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Business logic: Check if stand has a vendor assigned
        if ($stand->vendor_id) {
            return response()->json([
                'error' => 'Cannot delete stand with assigned vendor. Remove vendor first.'
            ], 422);
        }

        Log::info("Stand deleted", [
            'stand_id' => $stand->id,
            'event_id' => $stand->event_id,
            'number' => $stand->number,
            'deleted_by' => $user->id
        ]);

        $stand->delete();

        return response()->json(['message' => 'Stand deleted successfully']);
    }

    /**
     * Assign vendor to stand.
     *
     * @param Request $request
     * @param Stand $stand
     * @return JsonResponse
     */
    public function assignVendor(Request $request, Stand $stand)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $vendorId = $request->vendor_id;

        // Check if stand is already assigned
        if ($stand->vendor_id) {
            return response()->json([
                'error' => 'Stand is already assigned to a vendor'
            ], 422);
        }

        // Check if vendor is already assigned to another stand in this event
        $vendorHasStand = Stand::where('event_id', $stand->event_id)
                              ->where('vendor_id', $vendorId)
                              ->exists();

        if ($vendorHasStand) {
            return response()->json([
                'error' => 'Vendor is already assigned to another stand in this event'
            ], 422);
        }

        $stand->update(['vendor_id' => $vendorId]);

        Log::info("Vendor assigned to stand", [
            'stand_id' => $stand->id,
            'vendor_id' => $vendorId,
            'assigned_by' => $user->id
        ]);

        return response()->json([
            'message' => 'Vendor assigned to stand successfully',
            'stand' => $stand->load(['event', 'vendor'])
        ]);
    }

    /**
     * Remove vendor from stand.
     *
     * @param Stand $stand
     * @return JsonResponse
     */
    public function removeVendor(Stand $stand)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$stand->vendor_id) {
            return response()->json([
                'error' => 'Stand has no assigned vendor'
            ], 422);
        }

        $oldVendorId = $stand->vendor_id;
        $stand->update(['vendor_id' => null]);

        Log::info("Vendor removed from stand", [
            'stand_id' => $stand->id,
            'old_vendor_id' => $oldVendorId,
            'removed_by' => $user->id
        ]);

        return response()->json([
            'message' => 'Vendor removed from stand successfully',
            'stand' => $stand->load(['event'])
        ]);
    }

    /**
     * Get stand statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request)
    {
        $query = Stand::query();

        // Apply event filter if provided
        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        $totalStands = $query->count();
        $assignedStands = $query->whereNotNull('vendor_id')->count();
        $availableStands = $totalStands - $assignedStands;

        $stats = [
            'total_stands' => $totalStands,
            'assigned_stands' => $assignedStands,
            'available_stands' => $availableStands,
            'occupancy_rate' => $totalStands > 0 ? round(($assignedStands / $totalStands) * 100, 2) : 0,
            'by_event' => Stand::selectRaw('event_id, COUNT(*) as total, COUNT(vendor_id) as assigned')
                             ->with('event:id,name')
                             ->groupBy('event_id')
                             ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Get stands for a specific event.
     *
     * @param Event $event
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByEvent(Event $event)
    {
        return $event->stands()->with(['vendor'])->orderBy('number')->get();
    }

    /**
     * Get available stands for vendor assignment.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailable(Request $request)
    {
        $query = Stand::whereNull('vendor_id');

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        return $query->with(['event:id,name'])
                    ->orderBy('number')
                    ->get(['id', 'event_id', 'number', 'location', 'size', 'price']);
    }

    /**
     * Get stands with detailed event and vendor information using JOINs.
     * 
     * Demonstratie van JOIN queries zoals gevraagd door docent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStandsWithDetails(Request $request)
    {
        // JOIN query om stands te krijgen met event en vendor informatie
        $query = Stand::select([
                'stands.*',
                'events.name as event_name',
                'events.start_date',
                'events.end_date',
                'vendors.name as vendor_name',
                'vendors.company_name',
                'vendors.email as vendor_email'
            ])
            ->join('events', 'stands.event_id', '=', 'events.id')
            ->leftJoin('vendors', 'stands.vendor_id', '=', 'vendors.id');

        // Filter op event indien opgegeven
        if ($request->filled('event_id')) {
            $query->where('events.id', $request->event_id);
        }

        // Filter op beschikbaarheid
        if ($request->filled('available')) {
            if ($request->boolean('available')) {
                $query->whereNull('stands.vendor_id');
            } else {
                $query->whereNotNull('stands.vendor_id');
            }
        }

        $stands = $query->orderBy('events.start_date')
                       ->orderBy('stands.number')
                       ->get();

        return response()->json([
            'message' => 'Stands retrieved with JOIN queries',
            'data' => $stands,
            'total_count' => $stands->count()
        ]);
    }

    /**
     * Get vendor occupancy statistics using JOIN and GROUP BY.
     * 
     * Nog een voorbeeld van JOIN met aggregatie functies.
     *
     * @return JsonResponse
     */
    public function getVendorOccupancyStats()
    {
        // JOIN met aggregatie om vendor statistieken te krijgen
        $vendorStats = Stand::select([
                'vendors.id',
                'vendors.name',
                'vendors.company_name',
                'events.name as event_name',
                DB::raw('COUNT(stands.id) as total_stands'),
                DB::raw('SUM(CASE WHEN stands.price IS NOT NULL THEN stands.price ELSE 0 END) as total_value')
            ])
            ->join('events', 'stands.event_id', '=', 'events.id')
            ->join('vendors', 'stands.vendor_id', '=', 'vendors.id')
            ->whereNotNull('stands.vendor_id')
            ->groupBy('vendors.id', 'vendors.name', 'vendors.company_name', 'events.id', 'events.name')
            ->orderBy('total_stands', 'desc')
            ->get();

        return response()->json([
            'message' => 'Vendor occupancy statistics with JOIN and GROUP BY',
            'data' => $vendorStats
        ]);
    }

    /**
     * Bulk create stands for an event.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkCreate(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'stand_prefix' => 'required|string|max:10',
            'start_number' => 'required|integer|min:1',
            'end_number' => 'required|integer|min:1|gte:start_number',
            'location' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $createdStands = [];
        $skippedStands = [];

        for ($i = $data['start_number']; $i <= $data['end_number']; $i++) {
            $standNumber = $data['stand_prefix'] . $i;

            // Check if stand already exists
            $exists = Stand::where('event_id', $data['event_id'])
                           ->where('number', $standNumber)
                           ->exists();

            if ($exists) {
                $skippedStands[] = $standNumber;
                continue;
            }

            $stand = Stand::create([
                'event_id' => $data['event_id'],
                'number' => $standNumber,
                'location' => $data['location'] ?? null,
                'size' => $data['size'] ?? null,
                'price' => $data['price'] ?? null,
            ]);

            $createdStands[] = $stand;
        }

        Log::info("Bulk stands created", [
            'event_id' => $data['event_id'],
            'created_count' => count($createdStands),
            'skipped_count' => count($skippedStands),
            'created_by' => $user->id
        ]);

        return response()->json([
            'message' => 'Bulk creation completed',
            'created_count' => count($createdStands),
            'skipped_count' => count($skippedStands),
            'created_stands' => $createdStands,
            'skipped_stands' => $skippedStands,
        ]);
    }
}
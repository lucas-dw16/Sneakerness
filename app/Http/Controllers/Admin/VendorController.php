<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * VendorController - Handles all Vendor CRUD operations and business logic.
 * 
 * Manages vendor accounts, user account creation, stand assignments,
 * and vendor-specific business rules following MVC pattern.
 */
class VendorController extends Controller
{
    /**
     * Display a listing of vendors with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Database\Eloquent\Collection
     */
    public function index(Request $request)
    {
        $query = Vendor::with(['user', 'stands', 'contactPersons']);

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('company_name', 'like', '%' . $request->search . '%')
                  ->orWhere('contact_email', 'like', '%' . $request->search . '%')
                  ->orWhere('contact_phone', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('has_user')) {
            if ($request->boolean('has_user')) {
                $query->whereNotNull('user_id');
            } else {
                $query->whereNull('user_id');
            }
        }

        if ($request->filled('with_stands')) {
            if ($request->boolean('with_stands')) {
                $query->has('stands');
            } else {
                $query->doesntHave('stands');
            }
        }

        $vendors = $query->orderBy('company_name')->get();

        return $request->wantsJson() ? response()->json($vendors) : $vendors;
    }

    /**
     * Show a single vendor with related data.
     *
     * @param Vendor $vendor
     * @return Vendor
     */
    public function show(Vendor $vendor)
    {
        return $vendor->load(['user.roles', 'stands.event', 'contactPersons']);
    }

    /**
     * Store a newly created vendor.
     *
     * @param Request $request
     * @return JsonResponse|Vendor
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            
            // User account fields (optional)
            'create_user_account' => 'boolean',
            'user_name' => 'required_if:create_user_account,true|string|max:255',
            'user_email' => 'required_if:create_user_account,true|email|unique:users,email',
            'user_password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Create vendor
        $vendor = Vendor::create([
            'company_name' => $data['company_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
            'website' => $data['website'] ?? null,
        ]);

        // Create user account if requested
        if ($data['create_user_account'] ?? false) {
            $userController = new UserController();
            $user = $userController->createFromVendor([
                'name' => $data['user_name'],
                'email' => $data['user_email'],
                'password' => $data['user_password'] ?? null,
                'original_password' => $data['user_password'] ?? null,
            ], 'verkoper', $vendor);

            $vendor->refresh(); // Reload to get user_id
        }

        Log::info("Vendor created", [
            'vendor_id' => $vendor->id,
            'company_name' => $vendor->company_name,
            'has_user_account' => !is_null($vendor->user_id),
            'created_by' => Auth::id()
        ]);

        return $request->wantsJson() 
            ? response()->json($vendor->load(['user']), 201)
            : $vendor;
    }

    /**
     * Update an existing vendor.
     *
     * @param Request $request
     * @param Vendor $vendor
     * @return JsonResponse|Vendor
     */
    public function update(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $vendor->update($data);

        Log::info("Vendor updated", [
            'vendor_id' => $vendor->id,
            'updated_by' => Auth::id(),
            'changes' => array_keys($data)
        ]);

        return $request->wantsJson() 
            ? response()->json($vendor->load(['user']))
            : $vendor;
    }

    /**
     * Delete a vendor (with business logic checks).
     *
     * @param Vendor $vendor
     * @return JsonResponse
     */
    public function destroy(Vendor $vendor)
    {
        // Business logic: Check if vendor has active stands
        if ($vendor->stands()->exists()) {
            return response()->json([
                'error' => 'Cannot delete vendor with assigned stands'
            ], 422);
        }

        // Check if vendor has contact persons
        if ($vendor->contactPersons()->exists()) {
            return response()->json([
                'error' => 'Cannot delete vendor with contact persons. Remove them first.'
            ], 422);
        }

        // Store user_id for potential cleanup
        $userId = $vendor->user_id;
        
        $vendor->delete();

        // Optionally delete linked user account (business decision)
        if ($userId) {
            $user = User::find($userId);
            if ($user && !$user->tickets()->exists()) {
                $user->delete();
                Log::info("Linked user account deleted with vendor", ['user_id' => $userId]);
            }
        }

        Log::info("Vendor deleted", [
            'vendor_id' => $vendor->id,
            'deleted_by' => Auth::id()
        ]);

        return response()->json(['message' => 'Vendor deleted successfully']);
    }

    /**
     * Create or update user account for vendor.
     *
     * @param Request $request
     * @param Vendor $vendor
     * @return JsonResponse
     */
    public function manageUserAccount(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:create,update,remove',
            'user_name' => 'required_if:action,create,update|string|max:255',
            'user_email' => 'required_if:action,create|email|unique:users,email,' . ($vendor->user_id ?? 'NULL'),
            'user_password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        switch ($data['action']) {
            case 'create':
                if ($vendor->user_id) {
                    return response()->json(['error' => 'Vendor already has a user account'], 422);
                }

                $userController = new UserController();
                $user = $userController->createFromVendor([
                    'name' => $data['user_name'],
                    'email' => $data['user_email'],
                    'password' => $data['user_password'] ?? null,
                ], 'verkoper', $vendor);

                return response()->json([
                    'message' => 'User account created successfully',
                    'user' => $user->load('roles')
                ]);

            case 'update':
                if (!$vendor->user_id) {
                    return response()->json(['error' => 'Vendor has no user account'], 422);
                }

                $userController = new UserController();
                $user = $userController->update($request, $vendor->user);

                return response()->json([
                    'message' => 'User account updated successfully',
                    'user' => $user
                ]);

            case 'remove':
                if (!$vendor->user_id) {
                    return response()->json(['error' => 'Vendor has no user account'], 422);
                }

                $user = $vendor->user;
                $vendor->update(['user_id' => null]);

                // Check if user can be safely deleted
                if (!$user->tickets()->exists()) {
                    $user->delete();
                    $message = 'User account removed and deleted';
                } else {
                    $message = 'User account unlinked (user has tickets, not deleted)';
                }

                return response()->json(['message' => $message]);

            default:
                return response()->json(['error' => 'Invalid action'], 422);
        }
    }

    /**
     * Get vendors with their stand and contact information using JOINs.
     * 
     * Demonstratie van complexere JOIN queries zoals gevraagd door docent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVendorsWithRelations(Request $request)
    {
        // Complexere JOIN query om vendors te krijgen met stands en contacten
        $query = DB::table('vendors')
            ->select([
                'vendors.*',
                'stands.id as stand_id',
                'stands.number as stand_number',
                'stands.location as stand_location',
                'events.name as event_name',
                'events.start_date',
                'contact_persons.name as contact_name',
                'contact_persons.email as contact_email',
                'contact_persons.phone as contact_phone'
            ])
            ->leftJoin('stands', 'vendors.id', '=', 'stands.vendor_id')
            ->leftJoin('events', 'stands.event_id', '=', 'events.id')
            ->leftJoin('contact_persons', 'vendors.id', '=', 'contact_persons.vendor_id');

        // Filter op event indien opgegeven
        if ($request->filled('event_id')) {
            $query->where('events.id', $request->event_id);
        }

        // Filter op vendors met stands
        if ($request->filled('has_stands')) {
            if ($request->boolean('has_stands')) {
                $query->whereNotNull('stands.id');
            } else {
                $query->whereNull('stands.id');
            }
        }

        $vendors = $query->orderBy('vendors.name')
                        ->orderBy('events.start_date')
                        ->get();

        return response()->json([
            'message' => 'Vendors retrieved with JOIN queries',
            'data' => $vendors,
            'total_count' => $vendors->count()
        ]);
    }

    /**
     * Get vendor performance metrics using multiple JOINs and aggregations.
     * 
     * Nog een voorbeeld van JOIN met GROUP BY en aggregatie functies.
     *
     * @return JsonResponse
     */
    public function getPerformanceMetrics()
    {
        // JOIN met aggregatie voor vendor performance metrics
        $metrics = DB::table('vendors')
            ->select([
                'vendors.id',
                'vendors.name',
                'vendors.company_name',
                DB::raw('COUNT(DISTINCT stands.id) as total_stands'),
                DB::raw('COUNT(DISTINCT events.id) as total_events'),
                DB::raw('COUNT(DISTINCT contact_persons.id) as total_contacts'),
                DB::raw('SUM(CASE WHEN stands.price IS NOT NULL THEN stands.price ELSE 0 END) as total_stand_value'),
                DB::raw('AVG(stands.price) as avg_stand_price')
            ])
            ->leftJoin('stands', 'vendors.id', '=', 'stands.vendor_id')
            ->leftJoin('events', 'stands.event_id', '=', 'events.id')
            ->leftJoin('contact_persons', 'vendors.id', '=', 'contact_persons.vendor_id')
            ->groupBy('vendors.id', 'vendors.name', 'vendors.company_name')
            ->orderBy('total_stands', 'desc')
            ->get();

        return response()->json([
            'message' => 'Vendor performance metrics with complex JOINs',
            'data' => $metrics
        ]);
    }

    /**
     * Get vendor statistics.
     *
     * @return JsonResponse
     */
    public function statistics()
    {
        $stats = [
            'total_vendors' => Vendor::count(),
            'vendors_with_users' => Vendor::whereNotNull('user_id')->count(),
            'vendors_with_stands' => Vendor::has('stands')->count(),
            'vendors_with_contact_persons' => Vendor::has('contactPersons')->count(),
            'recent_vendors' => Vendor::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get stands for a specific vendor.
     *
     * @param Vendor $vendor
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStands(Vendor $vendor)
    {
        return $vendor->stands()->with(['event'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get contact persons for a specific vendor.
     *
     * @param Vendor $vendor
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getContactPersons(Vendor $vendor)
    {
        return $vendor->contactPersons()->with(['user'])->orderBy('name')->get();
    }

    /**
     * Assign vendor to stand.
     *
     * @param Request $request
     * @param Vendor $vendor
     * @return JsonResponse
     */
    public function assignToStand(Request $request, Vendor $vendor)
    {
        $validator = Validator::make($request->all(), [
            'stand_id' => 'required|exists:stands,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $standId = $request->stand_id;

        // Check if stand is already assigned
        $stand = \App\Models\Stand::find($standId);
        if ($stand->vendor_id && $stand->vendor_id !== $vendor->id) {
            return response()->json([
                'error' => 'Stand is already assigned to another vendor'
            ], 422);
        }

        $stand->update(['vendor_id' => $vendor->id]);

        Log::info("Vendor assigned to stand", [
            'vendor_id' => $vendor->id,
            'stand_id' => $standId,
            'assigned_by' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Vendor assigned to stand successfully',
            'stand' => $stand->load(['event'])
        ]);
    }

    /**
     * Get vendors available for stand assignment.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableForStand(Request $request)
    {
        $eventId = $request->event_id;

        $query = Vendor::query();

        // If event_id provided, exclude vendors already assigned to stands in that event
        if ($eventId) {
            $query->whereDoesntHave('stands', function ($q) use ($eventId) {
                $q->where('event_id', $eventId);
            });
        }

        return $query->orderBy('company_name')->get(['id', 'company_name', 'contact_email']);
    }
}
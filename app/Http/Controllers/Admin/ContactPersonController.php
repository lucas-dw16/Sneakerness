<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactPerson;
use App\Models\Vendor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
// use App\Mail\UserAccountCreated; // Will be created later

/**
 * ContactPersonController - Handles all ContactPerson CRUD operations and business logic.
 * 
 * Manages contact person records, vendor relationships, user account creation,
 * and contact-specific business rules following MVC pattern.
 */
class ContactPersonController extends Controller
{
    /**
     * Display a listing of contact persons with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Database\Eloquent\Collection
     */
    public function index(Request $request)
    {
        $query = ContactPerson::with(['vendor', 'user']);

        // Apply role-based filtering
        $user = Auth::user();
        if ($user && $user->hasRole('verkoper')) {
            // Vendors can only see contact persons from their own vendor
            if ($user->vendor) {
                $query->where('vendor_id', $user->vendor->id);
            } else {
                // Vendor role but no vendor association - show nothing
                $query->whereRaw('1=0');
            }
        }

        // Apply filters
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        $contactPersons = $query->orderBy('name')->get();

        return $request->wantsJson() ? response()->json($contactPersons) : $contactPersons;
    }

    /**
     * Show a single contact person with related data.
     *
     * @param ContactPerson $contactPerson
     * @return ContactPerson|JsonResponse
     */
    public function show(ContactPerson $contactPerson)
    {
        // Authorization check for vendor roles
        $user = Auth::user();
        if ($user && $user->hasRole('verkoper')) {
            if ($user->vendor && $contactPerson->vendor_id !== $user->vendor->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        return $contactPerson->load(['vendor', 'user']);
    }

    /**
     * Store a newly created contact person.
     *
     * @param Request $request
     * @return JsonResponse|ContactPerson
     */
    public function store(Request $request)
    {
        // Authorization check
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'support', 'verkoper'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'create_user_account' => 'boolean',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Vendor role can only create contacts for their own vendor
        if ($user->hasRole('verkoper') && $user->vendor) {
            if ($data['vendor_id'] !== $user->vendor->id) {
                return response()->json(['error' => 'Can only create contacts for your own vendor'], 403);
            }
        }

        // Business validation: Check if email is unique
        $contactExists = ContactPerson::where('email', $data['email'])->exists();
        if ($contactExists) {
            return response()->json([
                'error' => 'A contact person with this email already exists'
            ], 422);
        }

        // Remove user account creation flag from contact data
        $createUserAccount = $data['create_user_account'] ?? false;
        $password = $data['password'] ?? null;
        unset($data['create_user_account'], $data['password']);

        $contactPerson = ContactPerson::create($data);

        // Create user account if requested
        if ($createUserAccount) {
            try {
                $userAccountResult = $this->createUserAccountInternal($contactPerson, $password);
                
                if ($userAccountResult['success']) {
                    $contactPerson->update(['user_id' => $userAccountResult['user']->id]);
                    
                    Log::info("Contact person created with user account", [
                        'contact_id' => $contactPerson->id,
                        'user_id' => $userAccountResult['user']->id,
                        'created_by' => $user->id
                    ]);
                } else {
                    Log::warning("Contact person created but user account failed", [
                        'contact_id' => $contactPerson->id,
                        'error' => $userAccountResult['error'],
                        'created_by' => $user->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Error creating user account for contact person", [
                    'contact_id' => $contactPerson->id,
                    'error' => $e->getMessage(),
                    'created_by' => $user->id
                ]);
            }
        }

        Log::info("Contact person created", [
            'contact_id' => $contactPerson->id,
            'vendor_id' => $contactPerson->vendor_id,
            'has_user_account' => !is_null($contactPerson->user_id),
            'created_by' => $user->id
        ]);

        return $request->wantsJson() 
            ? response()->json($contactPerson->load(['vendor', 'user']), 201)
            : $contactPerson;
    }

    /**
     * Update an existing contact person.
     *
     * @param Request $request
     * @param ContactPerson $contactPerson
     * @return JsonResponse|ContactPerson
     */
    public function update(Request $request, ContactPerson $contactPerson)
    {
        $user = Auth::user();

        // Authorization check
        if (!$user->hasAnyRole(['admin', 'support', 'verkoper'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Vendor role can only update their own vendor's contacts
        if ($user->hasRole('verkoper') && $user->vendor) {
            if ($contactPerson->vendor_id !== $user->vendor->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => 'sometimes|exists:vendors,id',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Vendor role cannot change vendor_id
        if ($user->hasRole('verkoper') && isset($data['vendor_id'])) {
            return response()->json(['error' => 'Cannot change vendor assignment'], 403);
        }

        // Business validation: Check if new email is unique
        if (isset($data['email'])) {
            $contactExists = ContactPerson::where('email', $data['email'])
                                         ->where('id', '!=', $contactPerson->id)
                                         ->exists();
            if ($contactExists) {
                return response()->json([
                    'error' => 'A contact person with this email already exists'
                ], 422);
            }

            // Update linked user email if exists
            if ($contactPerson->user) {
                $contactPerson->user->update(['email' => $data['email']]);
            }
        }

        $contactPerson->update($data);

        Log::info("Contact person updated", [
            'contact_id' => $contactPerson->id,
            'updated_by' => $user->id,
            'changes' => array_keys($data)
        ]);

        return $request->wantsJson() 
            ? response()->json($contactPerson->load(['vendor', 'user']))
            : $contactPerson;
    }

    /**
     * Delete a contact person.
     *
     * @param ContactPerson $contactPerson
     * @return JsonResponse
     */
    public function destroy(ContactPerson $contactPerson)
    {
        $user = Auth::user();

        // Authorization check
        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Business logic: Check if contact person has linked user
        if ($contactPerson->user) {
            return response()->json([
                'error' => 'Cannot delete contact person with linked user account. Remove user link first.'
            ], 422);
        }

        Log::info("Contact person deleted", [
            'contact_id' => $contactPerson->id,
            'vendor_id' => $contactPerson->vendor_id,
            'deleted_by' => $user->id
        ]);

        $contactPerson->delete();

        return response()->json(['message' => 'Contact person deleted successfully']);
    }

    /**
     * Create user account for contact person.
     *
     * @param Request $request
     * @param ContactPerson $contactPerson
     * @return JsonResponse
     */
    public function createUserAccount(Request $request, ContactPerson $contactPerson)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['admin', 'support', 'verkoper'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if contact already has user account
        if ($contactPerson->user) {
            return response()->json([
                'error' => 'Contact person already has a user account'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $password = $request->password;

        try {
            $result = $this->createUserAccountInternal($contactPerson, $password);
            
            if ($result['success']) {
                $contactPerson->update(['user_id' => $result['user']->id]);
                
                return response()->json([
                    'message' => 'User account created successfully',
                    'user' => $result['user'],
                    'password_sent' => $result['password_sent']
                ]);
            } else {
                return response()->json(['error' => $result['error']], 422);
            }
        } catch (\Exception $e) {
            Log::error("Error creating user account", [
                'contact_id' => $contactPerson->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to create user account'
            ], 500);
        }
    }

    /**
     * Remove user account link from contact person.
     *
     * @param ContactPerson $contactPerson
     * @return JsonResponse
     */
    public function removeUserAccount(ContactPerson $contactPerson)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['admin', 'support'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$contactPerson->user) {
            return response()->json([
                'error' => 'Contact person has no linked user account'
            ], 422);
        }

        $userId = $contactPerson->user_id;
        $contactPerson->update(['user_id' => null]);

        Log::info("User account unlinked from contact person", [
            'contact_id' => $contactPerson->id,
            'user_id' => $userId,
            'unlinked_by' => $user->id
        ]);

        return response()->json([
            'message' => 'User account link removed successfully'
        ]);
    }

    /**
     * Get contact persons for a specific vendor.
     *
     * @param Vendor $vendor
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByVendor(Vendor $vendor)
    {
        return $vendor->contactPersons()->with(['user'])->orderBy('name')->get();
    }

    /**
     * Get contact person statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request)
    {
        $query = ContactPerson::query();

        // Apply vendor filter if provided
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        $totalContacts = $query->count();
        $contactsWithUsers = $query->whereNotNull('user_id')->count();

        $stats = [
            'total_contacts' => $totalContacts,
            'contacts_with_users' => $contactsWithUsers,
            'contacts_without_users' => $totalContacts - $contactsWithUsers,
            'user_account_rate' => $totalContacts > 0 ? round(($contactsWithUsers / $totalContacts) * 100, 2) : 0,
            'by_vendor' => ContactPerson::selectRaw('vendor_id, COUNT(*) as total, COUNT(user_id) as with_users')
                                      ->with('vendor:id,name')
                                      ->groupBy('vendor_id')
                                      ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Private method to create user account for contact person.
     *
     * @param ContactPerson $contactPerson
     * @param string|null $password
     * @return array
     */
    private function createUserAccountInternal(ContactPerson $contactPerson, ?string $password): array
    {
        // Check if user with this email already exists
        $userExists = User::where('email', $contactPerson->email)->exists();
        if ($userExists) {
            return [
                'success' => false,
                'error' => 'User with this email already exists'
            ];
        }

        // Generate password if not provided
        $generatedPassword = false;
        if (!$password) {
            $password = bin2hex(random_bytes(8)); // Generate 16-character password
            $generatedPassword = true;
        }

        // Create user account
        $userData = [
            'name' => $contactPerson->name,
            'email' => $contactPerson->email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ];

        $user = User::create($userData);

        // Assign contactpersoon role
        $user->assignRole('contactpersoon');

        $passwordSent = false;

        // Send email with credentials if password was generated or no password provided
        if ($generatedPassword) {
            try {
                // TODO: Create UserAccountCreated mailable class
                // Mail::to($user->email)->send(new UserAccountCreated($user, $password));
                $passwordSent = false; // Set to true when mail is implemented
                
                Log::info("User account credentials should be emailed", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'password' => $password // Remove in production
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send user credentials email", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => true,
            'user' => $user,
            'password_sent' => $passwordSent
        ];
    }
}
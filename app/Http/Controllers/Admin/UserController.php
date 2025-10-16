<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * UserController - Handles all User CRUD operations and business logic.
 * 
 * Manages user accounts, role assignments, password generation,
 * and automated email notifications following MVC pattern.
 */
class UserController extends Controller
{
    /**
     * Display a listing of users with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Database\Eloquent\Collection
     */
    public function index(Request $request)
    {
        $query = User::query()->with('roles');

        // Apply filters
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('active')) {
            // Assuming we might add an 'active' field later
            $query->where('active', $request->boolean('active'));
        }

        $users = $query->orderBy('name')->get();

        return $request->wantsJson() ? response()->json($users) : $users;
    }

    /**
     * Show a single user with related data.
     *
     * @param User $user
     * @return User
     */
    public function show(User $user)
    {
        return $user->load(['roles', 'vendor', 'contactPerson', 'tickets']);
    }

    /**
     * Store a newly created user.
     *
     * @param Request $request
     * @return JsonResponse|User
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'role' => 'required|exists:roles,name',
            'send_credentials' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle password logic
        $passwordProvided = !empty($data['password']);
        $generatedPassword = null;

        if ($passwordProvided) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Generate temporary password
            $generatedPassword = Str::random(12);
            $data['password'] = Hash::make($generatedPassword);
        }

        // Create user
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Assign role
        $user->assignRole($data['role']);

        // Send credentials email if requested
        if ($data['send_credentials'] ?? true) {
            $this->sendCredentialsEmail($user, $generatedPassword);
        }

        return $request->wantsJson() 
            ? response()->json($user->load('roles'), 201)
            : $user;
    }

    /**
     * Update an existing user.
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse|User
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle password update
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Update user
        $user->update($data);

        // Update role if provided
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $request->wantsJson() 
            ? response()->json($user->load('roles'))
            : $user;
    }

    /**
     * Delete a user (with business logic checks).
     *
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user)
    {
        // Business logic: Check if user has critical relations
        if ($user->tickets()->where('status', 'paid')->exists()) {
            return response()->json([
                'error' => 'Cannot delete user with paid tickets'
            ], 422);
        }

        if ($user->hasRole(['admin']) && User::role('admin')->count() <= 1) {
            return response()->json([
                'error' => 'Cannot delete the last admin user'
            ], 422);
        }

        // Check if user is linked to vendor
        if ($user->vendor()->exists()) {
            return response()->json([
                'error' => 'Cannot delete user linked to vendor. Remove vendor link first.'
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Assign role to user.
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function assignRole(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user->syncRoles([$request->role]);

        return response()->json([
            'message' => 'Role assigned successfully',
            'user' => $user->load('roles')
        ]);
    }

    /**
     * Reset user password and optionally send new credentials.
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function resetPassword(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:8',
            'send_email' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $newPassword = $request->password ?? Str::random(12);
        
        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        if ($request->boolean('send_email', true)) {
            $this->sendPasswordResetEmail($user, $newPassword);
        }

        return response()->json([
            'message' => 'Password reset successfully',
            'password' => $request->password ? null : $newPassword // Only return if generated
        ]);
    }

    /**
     * Get user statistics for dashboard.
     *
     * @return JsonResponse
     */
    public function statistics()
    {
        $stats = [
            'total_users' => User::count(),
            'by_role' => User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                             ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                             ->selectRaw('roles.name as role, COUNT(*) as count')
                             ->groupBy('roles.name')
                             ->pluck('count', 'role'),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_users_with_tickets' => User::whereHas('tickets')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Create user account from vendor creation.
     *
     * @param array $userData
     * @param string $role
     * @param Vendor|null $vendor
     * @return User
     */
    public function createFromVendor(array $userData, string $role = 'verkoper', ?Vendor $vendor = null): User
    {
        $passwordProvided = !empty($userData['password']);
        $generatedPassword = null;

        if ($passwordProvided) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            $generatedPassword = Str::random(12);
            $userData['password'] = Hash::make($generatedPassword);
        }

        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => $userData['password'],
        ]);

        $user->assignRole($role);

        // Link to vendor if provided
        if ($vendor) {
            $vendor->update(['user_id' => $user->id]);
        }

        // Send appropriate email
        if ($passwordProvided) {
            $this->sendCredentialsEmail($user, $userData['original_password']);
        } else {
            $this->sendPasswordResetEmail($user, $generatedPassword);
        }

        return $user;
    }

    /**
     * Send credentials email to new user.
     *
     * @param User $user
     * @param string|null $password
     * @return void
     */
    private function sendCredentialsEmail(User $user, ?string $password): void
    {
        try {
            // TODO: Create proper Mailable class for user credentials
            // For now, just log the credentials
            Log::info("User credentials created", [
                'user_id' => $user->id,
                'email' => $user->email,
                'password_sent' => !is_null($password)
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send credentials email", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send password reset email to user.
     *
     * @param User $user
     * @param string $newPassword
     * @return void
     */
    private function sendPasswordResetEmail(User $user, string $newPassword): void
    {
        try {
            // TODO: Create proper Mailable class for password reset
            // For now, just log the reset
            Log::info("Password reset for user", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send password reset email", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\School;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Body: { email, password, school_id? }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|string',  // accepts email OR 10-digit mobile number
            'password'    => 'required|string',
            'school_code' => 'nullable|string',
        ]);

        $identifier = $request->email;
        $isPhone    = preg_match('/^[0-9]{10}$/', $identifier);

        // Resolve school_id from school_code if provided
        $schoolId = $request->school_id ?? null;
        if ($request->filled('school_code')) {
            $school = School::where('school_code', strtoupper($request->school_code))->first();
            if (!$school) {
                throw ValidationException::withMessages([
                    'school_code' => ['Invalid school code. Please check and try again.'],
                ]);
            }
            $schoolId = $school->id;
        }

        if ($isPhone) {
            // Phone login — for teachers, parents, students, staff
            $query = User::where('phone', $identifier)
                         ->where('status', 'active')
                         ->whereIn('role', ['teacher', 'parent', 'student', 'staff']);

            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }
        } else {
            // Email login — for admin / super_admin
            $query = User::where('email', $identifier)
                         ->where('status', 'active');

            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }
        }

        $user = $query->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials. Please check your mobile number / email and password.'],
            ]);
        }

        // Block inactive user accounts
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
                'code'    => 'ACCOUNT_DEACTIVATED',
            ], 401);
        }

        // Block login for deactivated or deleted schools
        if ($user->school_id) {
            $school = School::withTrashed()->find($user->school_id);
            if (!$school || $school->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'School not found.',
                    'code'    => 'SCHOOL_NOT_FOUND',
                ], 401);
            }
            if (!$school->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your school account has been deactivated. Please contact Vikashana support.',
                    'code'    => 'SCHOOL_DEACTIVATED',
                ], 401);
            }
        }

        // Update last login
        $user->update(['last_login' => now()]);

        // Create token (name = device/app identifier for mobile support)
        $tokenName = $request->input('device_name', 'web');
        $token     = $user->createToken($tokenName)->plainTextToken;

        // Log login activity
        ActivityLog::log($user->id, $user->school_id, 'login', 'auth', 'Logged in to Vikashana', '🔑');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'phone'     => $user->phone,
                    'role'      => $user->role,
                    'avatar'    => $user->avatar ? asset('storage/'.$user->avatar) : null,
                    'school_id' => $user->school_id,
                    'school'    => $user->school_id ? [
                        'id'          => $user->school->id,
                        'name'        => $user->school->name,
                        'school_code' => $user->school->school_code,
                        'logo'        => $user->school->logo ? asset('storage/'.$user->school->logo) : null,
                    ] : null,
                    'children' => $user->role === 'parent'
                        ? StudentParent::where('user_id', $user->id)
                            ->with(['student.schoolClass:id,name', 'student.section:id,name'])
                            ->get()
                            ->filter(fn($p) => $p->student !== null)
                            ->map(fn($p) => [
                                'student_id'   => $p->student->id,
                                'name'         => $p->student->name,
                                'admission_no' => $p->student->admission_no,
                                'class'        => $p->student->schoolClass->name ?? '—',
                                'section'      => $p->student->section->name ?? '—',
                                'class_id'     => $p->student->class_id,
                                'section_id'   => $p->student->section_id,
                                'photo'        => $p->student->photo
                                    ? asset('storage/' . $p->student->photo) : null,
                                'dob'          => $p->student->dob?->toDateString(),
                                'status'       => $p->student->status,
                                'relation'     => $p->relation,
                            ])
                            ->values()
                        : null,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/me
     * Returns current authenticated user — used by mobile app on launch
     */
    public function me(Request $request): JsonResponse
    {
        $user         = $request->user()->load('school');
        $subscription = $user->school_id
            ? \App\Models\Subscription::where('school_id', $user->school_id)->first()
            : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'phone'     => $user->phone,
                'role'      => $user->role,
                'avatar'    => $user->avatar ? asset('storage/'.$user->avatar) : null,
                'school_id' => $user->school_id,
                'school'    => $user->school_id ? [
                    'id'       => $user->school->id,
                    'name'     => $user->school->name,
                    'logo'     => $user->school->logo ? asset('storage/'.$user->school->logo) : null,
                    'address'  => $user->school->address,
                    'phone'    => $user->school->phone,
                    'settings' => $user->school->settings,
                ] : null,
                'last_login'  => $user->last_login?->toISOString(),
                'created_at'  => $user->created_at?->toISOString(),
                'dept'        => $user->settings['dept'] ?? null,
                'bio'         => $user->settings['bio']  ?? null,
                'children'       => $user->role === 'parent'
                    ? StudentParent::where('user_id', $user->id)
                        ->with(['student.schoolClass:id,name', 'student.section:id,name'])
                        ->get()
                        ->filter(fn($p) => $p->student !== null)
                        ->map(fn($p) => [
                            'student_id'   => $p->student->id,
                            'name'         => $p->student->name,
                            'admission_no' => $p->student->admission_no,
                            'class'        => $p->student->schoolClass->name ?? '—',
                            'section'      => $p->student->section->name ?? '—',
                            'class_id'     => $p->student->class_id,
                            'section_id'   => $p->student->section_id,
                            'photo'        => $p->student->photo
                                ? asset('storage/' . $p->student->photo)
                                : null,
                            'dob'          => $p->student->dob?->toDateString(),
                            'status'       => $p->student->status,
                            'relation'     => $p->relation,
                        ])
                        ->values()
                    : null,
                'children_count' => $user->role === 'parent'
                    ? StudentParent::where('user_id', $user->id)->count()
                    : null,
                'subscription' => $subscription ? [
                    'plan'            => $subscription->plan,
                    'status'          => $subscription->status,
                    'billing_cycle'   => $subscription->billing_cycle,
                    'renewal_date'    => $subscription->renewal_date?->toDateString(),
                    'trial_ends_at'   => $subscription->trial_ends_at?->toISOString(),
                    'trial_days_left' => $subscription->trial_days_left,
                    'is_trial'        => $subscription->isTrialActive(),
                    'mobile_enabled'  => $subscription->mobile_enabled,
                    'student_count'   => $subscription->student_count,
                    'monthly_amount'  => $subscription->monthly_amount,
                    'limits'          => \App\Models\Subscription::getLimits($subscription->plan),
                    'is_blocked'      => $subscription->isBlocked(),
                    'is_grace_period' => $subscription->isInGracePeriod(),
                    'grace_days_left' => $subscription->grace_days_left,
                ] : [
                    'plan'           => 'pro',
                    'status'         => 'trial',
                    'is_trial'       => true,
                    'is_blocked'     => false,
                    'is_grace_period'=> false,
                    'grace_days_left'=> 0,
                    'mobile_enabled' => true,
                    'limits'         => \App\Models\Subscription::getLimits('pro'),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revokes current token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * PUT /api/v1/auth/profile  (POST when sending a file)
     * Body: { name?, phone?, avatar? }
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'   => 'sometimes|string|max:100',
            'phone'  => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:2048',
            'dept'   => 'nullable|string|max:100',
            'bio'    => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        // Store dept/bio in user settings JSON
        $settingsUpdate = [];
        if (array_key_exists('dept', $data)) $settingsUpdate['dept'] = $data['dept'];
        if (array_key_exists('bio',  $data)) $settingsUpdate['bio']  = $data['bio'];
        unset($data['dept'], $data['bio']);

        if (!empty($settingsUpdate)) {
            $data['settings'] = array_merge($user->settings ?? [], $settingsUpdate);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'role'   => $user->role,
                'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
                'dept'   => $user->settings['dept'] ?? null,
                'bio'    => $user->settings['bio']  ?? null,
            ],
        ]);
    }

    /**
     * PUT /api/v1/auth/password
     * Body: { current_password, password, password_confirmation }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $request->password]);

        // Revoke all tokens except current (force re-login on other devices)
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        ActivityLog::log($request->user()->id, $request->user()->school_id, 'update', 'auth', 'Changed account password', '🔑');

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * GET /api/v1/auth/activity
     * Returns last 50 activity log entries for the authenticated user.
     */
    public function activity(Request $request): JsonResponse
    {
        $limit  = min((int) $request->input('limit', 20), 100);
        $offset = (int) $request->input('offset', 0);

        $logs = ActivityLog::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get(['id','action','module','description','icon','ip_address','created_at']);

        $total = ActivityLog::where('user_id', $request->user()->id)->count();

        return response()->json([
            'success' => true,
            'data'    => $logs->map(fn($l) => [
                'id'          => $l->id,
                'action'      => $l->action,
                'module'      => $l->module,
                'description' => $l->description,
                'icon'        => $l->icon,
                'ip_address'  => $l->ip_address,
                'created_at'  => $l->created_at->toISOString(),
            ]),
            'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ]);
    }
}

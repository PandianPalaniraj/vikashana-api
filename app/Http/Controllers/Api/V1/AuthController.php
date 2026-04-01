<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\School;
use App\Models\StudentParent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Body: { login, password }
     *
     * Email  → admin / super_admin only
     * Mobile → teacher / parent / staff
     *          If same mobile in multiple schools → returns school selection list
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $login   = trim($request->login);
        $isEmail = str_contains($login, '@');

        if ($isEmail) {
            // ── Email login (admin / super_admin) ──────────────────────
            $user = User::where('email', $login)
                        ->whereIn('role', ['super_admin', 'admin'])
                        ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password.',
                ], 401);
            }

            return $this->generateLoginResponse($user, $request);

        } else {
            // ── Mobile login (teacher / parent / staff) ────────────────
            // Strip country code prefix if present
            $mobile = preg_replace('/[^0-9]/', '', $login);
            if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
                $mobile = substr($mobile, 2);
            }

            // Find ALL matching users across all schools
            $users = User::where('phone', $mobile)
                         ->whereIn('role', ['teacher', 'parent', 'staff'])
                         ->where('status', 'active')
                         ->with('school')
                         ->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mobile number not registered.',
                ], 401);
            }

            // Verify password against all matched users (DOB format)
            $verified = $users->filter(fn($u) => Hash::check($request->password, $u->password));

            if ($verified->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password. Use your date of birth in ddmmyyyy format (e.g. 14031985).',
                ], 401);
            }

            // Only keep users whose school is active and not deleted
            $active = $verified->filter(
                fn($u) => $u->school && $u->school->is_active && !$u->school->trashed()
            );

            if ($active->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your school account is deactivated. Please contact Vikashana support.',
                    'code'    => 'SCHOOL_DEACTIVATED',
                ], 401);
            }

            // Multiple active schools — ask user to pick one
            if ($active->count() > 1) {
                return response()->json([
                    'success'                    => true,
                    'requires_school_selection'  => true,
                    'message'                    => 'You are registered in multiple schools. Please select your school.',
                    'schools'                    => $active->map(fn($u) => [
                        'user_id'     => $u->id,
                        'school_id'   => $u->school_id,
                        'school_name' => $u->school->name,
                        'school_logo' => $u->school->logo ? asset('storage/'.$u->school->logo) : null,
                        'role'        => $u->role,
                    ])->values(),
                ]);
            }

            return $this->generateLoginResponse($active->first(), $request);
        }
    }

    /**
     * POST /api/v1/auth/select-school
     * Body: { user_id, password }
     * Called after multi-school selection to issue a scoped token.
     */
    public function selectSchool(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'  => 'required|integer',
            'password' => 'required|string',
        ]);

        $user = User::with('school')->find($request->user_id);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->school || !$user->school->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'School is deactivated.',
                'code'    => 'SCHOOL_DEACTIVATED',
            ], 401);
        }

        return $this->generateLoginResponse($user, $request);
    }

    /**
     * Shared helper — validates status, creates token, builds response.
     */
    private function generateLoginResponse(User $user, Request $request): JsonResponse
    {
        if ($user->role !== 'super_admin') {
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated.',
                    'code'    => 'ACCOUNT_DEACTIVATED',
                ], 401);
            }

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
        }

        $user->update(['last_login' => now()]);
        $tokenName = $request->input('device_name', 'web');
        $token     = $user->createToken($tokenName)->plainTextToken;

        ActivityLog::log($user->id, $user->school_id, 'login', 'auth', 'Logged in to Vikashana', '🔑');

        $subscription = $user->school_id
            ? Subscription::where('school_id', $user->school_id)->first()
            : null;

        $school = $user->school_id ? $user->school ?? School::find($user->school_id) : null;

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
                    'school'    => $school ? [
                        'id'          => $school->id,
                        'name'        => $school->name,
                        'school_code' => $school->school_code,
                        'logo'        => $school->logo ? asset('storage/'.$school->logo) : null,
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
                                'photo'        => $p->student->photo ? asset('storage/'.$p->student->photo) : null,
                                'dob'          => $p->student->dob?->toDateString(),
                                'status'       => $p->student->status,
                                'relation'     => $p->relation,
                            ])->values()
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
                        'limits'          => Subscription::getLimits($subscription->plan),
                        'is_blocked'      => $subscription->isBlocked(),
                        'is_grace_period' => $subscription->isInGracePeriod(),
                        'grace_days_left' => $subscription->grace_days_left,
                    ] : null,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user         = $request->user()->load('school');
        $subscription = $user->school_id
            ? Subscription::where('school_id', $user->school_id)->first()
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
                'children'    => $user->role === 'parent'
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
                            'photo'        => $p->student->photo ? asset('storage/'.$p->student->photo) : null,
                            'dob'          => $p->student->dob?->toDateString(),
                            'status'       => $p->student->status,
                            'relation'     => $p->relation,
                        ])->values()
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
                    'limits'          => Subscription::getLimits($subscription->plan),
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
                    'limits'         => Subscription::getLimits('pro'),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    /**
     * PUT /api/v1/auth/profile  (POST when sending a file)
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
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

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
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        ActivityLog::log($user->id, $user->school_id, 'update', 'auth', 'Changed account password', '🔑');

        return response()->json(['success' => true, 'message' => 'Password changed successfully']);
    }

    /**
     * GET /api/v1/auth/activity
     */
    public function activity(Request $request): JsonResponse
    {
        $limit  = min((int) $request->input('limit', 20), 100);
        $offset = (int) $request->input('offset', 0);

        $logs  = ActivityLog::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->skip($offset)->take($limit)
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

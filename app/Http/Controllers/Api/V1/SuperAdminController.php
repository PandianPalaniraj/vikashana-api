<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Feedback;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SystemAnnouncement;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    protected function formatDate($date): ?string
    {
        if (!$date) return null;
        return \Carbon\Carbon::parse($date)->format('d M Y');
    }

    // ── Dashboard Stats ────────────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        $totalSchools   = School::count();
        $activeSchools  = Subscription::where('status', 'active')->count();
        $trialSchools   = Subscription::where('status', 'trial')->count();
        $overdueSchools = Subscription::where('status', 'overdue')->count();
        $freeSchools    = Subscription::where('plan', 'free')->count();
        $paidSchools    = Subscription::whereIn('plan', ['starter', 'pro', 'premium', 'enterprise'])
            ->whereIn('status', ['active', 'trial'])
            ->count();
        $totalStudents  = Student::where('status', 'Active')->count();
        $totalTeachers  = Teacher::where('status', 'Active')->count();

        // MRR from monthly_amount field (already accounts for billing cycle)
        $mrr = Subscription::whereIn('status', ['active', 'trial'])
            ->where('plan', '!=', 'free')
            ->sum('monthly_amount');

        $avgRevenue = $paidSchools > 0 ? round($mrr / $paidSchools, 2) : 0;
        $conversionRate = $totalSchools > 0 ? round(($paidSchools / $totalSchools) * 100, 1) : 0;

        $newThisMonth   = School::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $expiringThisWeek = Subscription::whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->where('status', 'trial')
            ->count();

        $expiringTrials = Subscription::with('school:id,name,phone')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->where('status', 'trial')
            ->get()
            ->map(fn($s) => [
                'school_id'     => $s->school_id,
                'school_name'   => $s->school?->name,
                'school_phone'  => $s->school?->phone,
                'plan'          => $s->plan,
                'trial_ends_at' => $this->formatDate($s->trial_ends_at),
                'days_left'     => max(0, (int) now()->diffInDays($s->trial_ends_at, false)),
            ])
            ->sortBy('days_left')
            ->values();

        // Plan distribution
        $planCounts = Subscription::select('plan', DB::raw('count(*) as count'))
            ->groupBy('plan')
            ->pluck('count', 'plan');

        // Open feedback count
        $openFeedback = Feedback::whereIn('status', ['open', 'in_progress'])->count();

        // ARR estimate
        $arr = round($mrr * 12, 2);

        // Grace period schools (overdue but still within 15-day grace)
        $graceSchools = \App\Models\Subscription::where('status', 'overdue')
            ->whereNotNull('grace_period_ends_at')
            ->whereDate('grace_period_ends_at', '>=', today())
            ->with('school:id,name,phone')
            ->get()
            ->map(fn($s) => [
                'school_name'     => $s->school->name,
                'school_phone'    => $s->school->phone,
                'grace_ends'      => \Carbon\Carbon::parse($s->grace_period_ends_at)->format('d M Y'),
                'grace_days_left' => $s->grace_days_left,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'total_schools'               => $totalSchools,
                'active_schools'              => $activeSchools,
                'trial_schools'               => $trialSchools,
                'overdue_schools'             => $overdueSchools,
                'free_schools'                => $freeSchools,
                'paid_schools'                => $paidSchools,
                'total_students'              => $totalStudents,
                'total_teachers'              => $totalTeachers,
                'mrr'                         => round($mrr, 2),
                'arr'                         => $arr,
                'avg_revenue_per_school'      => $avgRevenue,
                'conversion_rate'             => $conversionRate,
                'new_schools_this_month'      => $newThisMonth,
                'expiring_this_week'          => $expiringThisWeek,
                'expiring_trials'             => $expiringTrials,
                'plan_distribution'           => $planCounts,
                'open_feedback'               => $openFeedback,
                'grace_period_schools'        => $graceSchools,
                'grace_period_schools_count'  => $graceSchools->count(),
            ],
        ]);
    }

    // ── Schools ────────────────────────────────────────────────────────────────
    public function schools(Request $request): JsonResponse
    {
        $q = School::with('subscription')
            ->withCount(['students', 'teachers'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($request->filled('status')) {
            $q->whereHas('subscription', fn($sq) => $sq->where('status', $request->status));
        }
        if ($request->filled('plan')) {
            $q->whereHas('subscription', fn($sq) => $sq->where('plan', $request->plan));
        }

        $schools = $q->paginate(20);
        return response()->json(['success' => true, 'data' => $schools->items(), 'meta' => [
            'total' => $schools->total(), 'per_page' => $schools->perPage(),
            'page'  => $schools->currentPage(), 'last_page' => $schools->lastPage(),
        ]]);
    }

    public function createSchool(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:schools,email',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'plan'    => 'nullable|in:free,starter,pro,premium,enterprise',
        ]);

        $school = DB::transaction(function () use ($validated) {
            // School::booted() auto-creates a Pro trial subscription
            $school = School::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'] ?? null,
                'address'   => $validated['address'] ?? null,
                'is_active' => true,
            ]);

            // Override plan if specified
            if (!empty($validated['plan']) && $validated['plan'] !== 'pro') {
                $school->subscription()->update([
                    'plan'           => $validated['plan'],
                    'mobile_enabled' => in_array($validated['plan'], ['pro', 'premium', 'enterprise']),
                ]);
            }

            // Create default admin user for the school
            User::create([
                'name'      => $validated['name'] . ' Admin',
                'email'     => $validated['email'],
                'password'  => 'password123',
                'role'      => 'admin',
                'school_id' => $school->id,
                'status'    => 'active',
            ]);

            return $school;
        });

        return response()->json(['success' => true, 'data' => $school->load('subscription'), 'message' => 'School created'], 201);
    }

    public function registerSchool(Request $request): JsonResponse
    {
        $data = $request->validate([
            // School info
            'school_name'       => 'required|string|max:200',
            'school_type'       => 'nullable|string|max:50',
            'affiliation_board' => 'nullable|string|max:50',
            'affiliation_no'    => 'nullable|string|max:50',
            'established_year'  => 'nullable|integer|min:1800|max:' . date('Y'),
            'address'           => 'nullable|string',
            'city'              => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:100',
            'pincode'           => 'nullable|string|max:10',
            'phone'             => 'required|string|max:20',
            'alternate_phone'   => 'nullable|string|max:20',
            'email'             => 'required|email|unique:schools,email',
            'website'           => 'nullable|string',
            // Principal
            'principal_name'    => 'required|string',
            'principal_phone'   => 'nullable|string|max:20',
            'principal_email'   => 'nullable|email',
            // Admin login
            'admin_name'        => 'nullable|string|max:100',
            'admin_email'       => 'required|email|unique:users,email',
            'admin_password'    => 'nullable|string|min:6',
            // Subscription
            'plan'              => 'nullable|in:free,starter,pro,premium,enterprise',
            'billing_cycle'     => 'nullable|in:monthly,annual',
            'estimated_students'=> 'nullable|integer|min:0',
            'trial_days'        => 'nullable|integer|min:0|max:365',
        ]);

        $result = DB::transaction(function () use ($data, $request) {

            // 1. Create school — store extra fields in settings JSON
            $school = School::create([
                'name'           => $data['school_name'],
                'address'        => $data['address'] ?? null,
                'phone'          => $data['phone'],
                'email'          => $data['email'],
                'website'        => $data['website'] ?? null,
                'affiliation_no' => $data['affiliation_no'] ?? null,
                'is_active'      => true,
                'settings'       => [
                    'principal'         => $data['principal_name'],
                    'principal_phone'   => $data['principal_phone'] ?? null,
                    'principal_email'   => $data['principal_email'] ?? null,
                    'school_type'       => $data['school_type'] ?? null,
                    'affiliation_board' => $data['affiliation_board'] ?? null,
                    'established_year'  => $data['established_year'] ?? null,
                    'city'              => $data['city'] ?? null,
                    'state'             => $data['state'] ?? null,
                    'pincode'           => $data['pincode'] ?? null,
                    'alternate_phone'   => $data['alternate_phone'] ?? null,
                    'currency'          => 'INR',
                    'date_format'       => 'd/m/Y',
                ],
            ]);

            // 2. Create admin user
            $password  = !empty($data['admin_password'])
                ? $data['admin_password']
                : 'Admin@' . rand(1000, 9999);
            $adminName = !empty($data['admin_name'])
                ? $data['admin_name']
                : $data['principal_name'];

            $admin = User::create([
                'school_id' => $school->id,
                'name'      => $adminName,
                'email'     => $data['admin_email'],
                'password'  => $password,
                'role'      => 'admin',
                'status'    => 'active',
                'phone'     => $data['principal_phone'] ?? $data['phone'],
            ]);

            // 3. Upsert subscription with billing cycle + amount calculation
            $plan      = $data['plan'] ?? 'pro';
            $cycle     = $data['billing_cycle'] ?? 'monthly';
            $trialDays = $data['trial_days'] ?? 30;
            $students  = $data['estimated_students'] ?? 0;

            // Auto-calculate monthly amount
            $rates = ['starter' => ['monthly'=>15,'annual'=>12.50], 'pro' => ['monthly'=>25,'annual'=>20.83], 'premium' => ['monthly'=>40,'annual'=>33.33]];
            $monthlyAmount = isset($rates[$plan]) ? ($rates[$plan][$cycle] ?? 0) * max($students, 1) : 0;

            $renewalDate = $trialDays > 0
                ? ($cycle === 'annual' ? now()->addDays($trialDays)->addYear() : now()->addDays($trialDays)->addMonth())
                : ($cycle === 'annual' ? now()->addYear() : now()->addMonth());

            $school->subscription()->updateOrCreate(
                ['school_id' => $school->id],
                [
                    'plan'           => $plan,
                    'billing_cycle'  => $cycle,
                    'status'         => $trialDays > 0 ? 'trial' : 'active',
                    'trial_ends_at'  => $trialDays > 0 ? now()->addDays($trialDays) : null,
                    'renewal_date'   => $renewalDate,
                    'mobile_enabled' => in_array($plan, ['pro', 'premium', 'enterprise']),
                    'student_count'  => $students,
                    'monthly_amount' => $monthlyAmount,
                ]
            );

            // 4. Create default academic year
            AcademicYear::create([
                'school_id'  => $school->id,
                'name'       => date('Y') . '-' . (date('Y') + 1),
                'start_date' => date('Y') . '-06-01',
                'end_date'   => (date('Y') + 1) . '-03-31',
                'is_current' => true,
            ]);

            // 5. Create default classes with sections A & B
            $classes = [
                'Nursery', 'LKG', 'UKG',
                '1', '2', '3', '4', '5',
                '6', '7', '8', '9', '10', '11', '12',
            ];
            foreach ($classes as $i => $name) {
                $class = SchoolClass::create([
                    'school_id'     => $school->id,
                    'name'          => $name,
                    'display_order' => $i + 1,
                ]);
                foreach (['A', 'B'] as $sec) {
                    Section::create([
                        'school_id' => $school->id,
                        'class_id'  => $class->id,
                        'name'      => $sec,
                        'capacity'  => 40,
                    ]);
                }
            }

            // 6. Log activity
            ActivityLog::log(
                $request->user()->id,
                $school->id,
                'create',
                'schools',
                "Registered new school: {$school->name}",
                '🏫'
            );

            return [
                'school'   => $school->load('subscription'),
                'admin'    => $admin,
                'password' => $password,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'School registered successfully',
            'data'    => [
                'school' => $result['school'],
                'login'  => [
                    'email'    => $result['admin']->email,
                    'password' => $result['password'],
                    'url'      => 'https://app.vikashana.com',
                    'note'     => 'Share these credentials with the school admin',
                ],
            ],
        ], 201);
    }

    public function deleteSchool(Request $request, School $school): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 403);
        }

        DB::transaction(function () use ($school, $request) {
            ActivityLog::log(
                $request->user()->id,
                $school->id,
                'delete',
                'schools',
                "Deleted school: {$school->name}",
                '🗑️'
            );

            // Revoke all active tokens and delete all users belonging to this school.
            // Users have nullOnDelete() on school_id (so super_admin can have null),
            // meaning they would survive the school deletion — we must remove them explicitly.
            $school->users()->each(function (User $user) {
                $user->tokens()->delete();
                $user->delete();
            });

            $school->delete();
        });

        return response()->json(['success' => true, 'message' => 'School deleted successfully.']);
    }

    public function showSchool(School $school): JsonResponse
    {
        $school->load('subscription');
        $school->loadCount(['students', 'teachers']);
        $lastLogin = $school->users()->whereNotNull('last_login')->latest('last_login')->value('last_login');

        $adminUser = $school->users()->where('role', 'admin')->first(['id', 'name', 'email', 'phone', 'status', 'last_login']);

        return response()->json(['success' => true, 'data' => array_merge($school->toArray(), [
            'last_activity' => $lastLogin,
            'admin_user'    => $adminUser ? [
                'id'         => $adminUser->id,
                'name'       => $adminUser->name,
                'email'      => $adminUser->email,
                'phone'      => $adminUser->phone,
                'status'     => $adminUser->status,
                'last_login' => $adminUser->last_login?->toISOString(),
            ] : null,
        ])]);
    }

    public function resetAdminPassword(Request $request, School $school): JsonResponse
    {
        $request->validate(['password' => 'required|string|min:6']);

        $adminUser = $school->users()->where('role', 'admin')->first();
        if (!$adminUser) {
            return response()->json(['success' => false, 'message' => 'No admin user found for this school'], 404);
        }

        $adminUser->update(['password' => $request->password]);

        return response()->json([
            'success'  => true,
            'message'  => 'Admin password reset successfully.',
            'email'    => $adminUser->email,
            'password' => $request->password,
        ]);
    }

    public function updateSchool(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'email'             => 'sometimes|email|unique:schools,email,' . $school->id,
            'phone'             => 'nullable|string|max:20',
            'alternate_phone'   => 'nullable|string|max:20',
            'address'           => 'nullable|string',
            'city'              => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:100',
            'pincode'           => 'nullable|string|max:10',
            'website'           => 'nullable|string',
            'affiliation_no'    => 'nullable|string|max:50',
            'school_type'       => 'nullable|string|max:50',
            'affiliation_board' => 'nullable|string|max:50',
            'established_year'  => 'nullable|integer|min:1800|max:' . date('Y'),
            'principal_name'    => 'nullable|string|max:200',
            'principal_phone'   => 'nullable|string|max:20',
            'principal_email'   => 'nullable|email',
        ]);

        // Fields stored in settings JSON
        $settingsKeys = ['school_type','affiliation_board','established_year','city','state','pincode','alternate_phone','principal_name','principal_phone','principal_email'];
        $settingsData = [];
        foreach ($settingsKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $settingsData[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }
        if (!empty($settingsData)) {
            $school->settings = array_merge($school->settings ?? [], $settingsData);
            $school->save();
        }
        if (!empty($validated)) {
            $school->update($validated);
        }

        return response()->json(['success' => true, 'data' => $school->fresh('subscription'), 'message' => 'School updated']);
    }

    public function toggleStatus(School $school): JsonResponse
    {
        $school->update(['is_active' => !$school->is_active]);
        return response()->json(['success' => true, 'message' => $school->is_active ? 'School activated' : 'School deactivated']);
    }

    public function schoolStats(School $school): JsonResponse
    {
        $studentCount = $school->students()->where('status', 'Active')->count();
        $teacherCount = $school->teachers()->where('status', 'Active')->count();

        // Keep subscription.student_count in sync so billing pages show accurate numbers
        $school->subscription()?->update(['student_count' => $studentCount]);

        return response()->json(['success' => true, 'data' => [
            'students'   => $studentCount,
            'teachers'   => $teacherCount,
            'classes'    => $school->classes()->count(),
            'last_login' => $school->users()->whereNotNull('last_login')->latest('last_login')->value('last_login'),
        ]]);
    }

    public function impersonate(Request $request, School $school): JsonResponse
    {
        // Find the admin user of this school
        $adminUser = $school->users()->where('role', 'admin')->first();
        if (!$adminUser) {
            return response()->json(['success' => false, 'message' => 'No admin user found for this school'], 404);
        }

        // Revoke old impersonation tokens and create a new short-lived one
        $adminUser->tokens()->where('name', 'impersonate')->delete();
        $token = $adminUser->createToken('impersonate', ['*'], now()->addHours(2));

        // Log impersonation
        DB::table('audit_logs')->insert([
            'user_id'    => $request->user()->id,
            'school_id'  => $school->id,
            'action'     => 'impersonate',
            'model_type' => 'School',
            'model_id'   => $school->id,
            'details'    => json_encode(['target_admin' => $adminUser->email]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'token'   => $token->plainTextToken,
            'user'    => ['name' => $adminUser->name, 'email' => $adminUser->email],
            'expires_at' => now()->addHours(2)->toISOString(),
        ]);
    }

    // ── Subscriptions ──────────────────────────────────────────────────────────
    public function subscriptions(Request $request): JsonResponse
    {
        $q = Subscription::with('school')->orderByDesc('updated_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('plan'))   $q->where('plan', $request->plan);

        $subs = $q->paginate(20);
        return response()->json(['success' => true, 'data' => $subs->items(), 'meta' => [
            'total' => $subs->total(), 'per_page' => $subs->perPage(),
            'page'  => $subs->currentPage(), 'last_page' => $subs->lastPage(),
        ]]);
    }

    public function createSubscription(Request $request, School $school): JsonResponse
    {
        if ($school->subscription) {
            return response()->json(['success' => false, 'message' => 'Subscription already exists. Use update instead.'], 422);
        }

        $validated = $request->validate([
            'plan'           => 'required|in:free,starter,pro,premium,enterprise',
            'status'         => 'required|in:trial,active,overdue,cancelled',
            'billing_cycle'  => 'required|in:monthly,annual',
            'trial_ends_at'  => 'nullable|date',
            'renewal_date'   => 'nullable|date',
            'monthly_amount' => 'nullable|numeric|min:0',
            'mobile_enabled' => 'boolean',
            'notes'          => 'nullable|string',
        ]);

        $studentCount = Student::where('school_id', $school->id)->where('status', 'Active')->count();
        $rates = ['free' => 0, 'starter' => 15, 'pro' => 25, 'premium' => 40, 'enterprise' => 0];
        $rate = $rates[$validated['plan']] ?? 0;
        $monthly = $rate * max($studentCount, 0);

        $subscription = Subscription::create([
            'school_id'          => $school->id,
            'plan'               => $validated['plan'],
            'status'             => $validated['status'],
            'billing_cycle'      => $validated['billing_cycle'],
            'trial_ends_at'      => $validated['trial_ends_at'] ?? null,
            'renewal_date'       => $validated['renewal_date'] ?? null,
            'student_count'      => $studentCount,
            'amount_per_student' => $rate,
            'monthly_amount'     => $validated['monthly_amount'] ?? $monthly,
            'mobile_enabled'     => $validated['mobile_enabled'] ?? false,
            'notes'              => $validated['notes'] ?? null,
        ]);

        ActivityLog::create([
            'school_id'   => $school->id,
            'user_id'     => $request->user()->id,
            'module'      => 'subscriptions',
            'action'      => 'subscription_created',
            'description' => "Subscription created: Plan {$subscription->plan}, Status {$subscription->status}",
        ]);

        return response()->json(['success' => true, 'data' => $subscription, 'message' => 'Subscription created successfully']);
    }

    public function updateSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'plan'           => 'sometimes|in:free,starter,pro,premium,enterprise',
            'status'         => 'sometimes|in:trial,active,overdue,cancelled,expired',
            'billing_cycle'  => 'sometimes|in:monthly,annual',
            'renewal_date'   => 'sometimes|nullable|date',
            'monthly_amount' => 'sometimes|numeric|min:0',
            'mobile_enabled' => 'sometimes|boolean',
            'notes'          => 'nullable|string',
        ]);

        // Auto-recalculate amount if plan or billing_cycle changed and no manual amount given
        $plan  = $validated['plan']  ?? $subscription->plan;
        $cycle = $validated['billing_cycle'] ?? $subscription->billing_cycle;
        $count = $subscription->student_count ?? 0;

        $rates = ['free' => 0, 'starter' => 15, 'pro' => 25, 'premium' => 40, 'enterprise' => 0];
        $monthly = ($rates[$plan] ?? 0) * $count;
        $calculated = $cycle === 'annual' ? round($monthly * 12 * 0.8, 2) : $monthly;

        if (!isset($validated['monthly_amount']) || $validated['monthly_amount'] == 0) {
            $validated['monthly_amount'] = $calculated;
        }

        $subscription->update($validated);

        ActivityLog::create([
            'school_id'   => $subscription->school_id,
            'user_id'     => $request->user()->id,
            'module'      => 'subscriptions',
            'action'      => 'subscription_updated',
            'description' => "Plan: {$subscription->plan}, Status: {$subscription->status}",
        ]);

        return response()->json(['success' => true, 'data' => $subscription->fresh()->load('school')]);
    }

    public function extendTrial(Request $request, Subscription $subscription): JsonResponse
    {
        $days = $request->input('days', 14);
        $base = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
            ? $subscription->trial_ends_at
            : now();
        $subscription->update([
            'trial_ends_at' => $base->addDays($days),
            'status'        => 'trial',
        ]);
        return response()->json(['success' => true, 'data' => $subscription, 'message' => "Trial extended by {$days} days"]);
    }

    public function syncStudentCount(Subscription $subscription): JsonResponse
    {
        $subscription->syncStudentCount();
        return response()->json(['success' => true, 'data' => $subscription->fresh(), 'message' => 'Student count synced']);
    }

    // ── Feedback ───────────────────────────────────────────────────────────────
    public function feedback(Request $request): JsonResponse
    {
        $q = Feedback::with(['school:id,name', 'user:id,name'])->orderByDesc('created_at');
        if ($request->filled('status'))   $q->where('status', $request->status);
        if ($request->filled('category')) $q->where('category', $request->category);
        if ($request->filled('priority')) $q->where('priority', $request->priority);

        $items = $q->paginate(20);
        return response()->json(['success' => true, 'data' => $items->items(), 'meta' => [
            'total' => $items->total(), 'per_page' => $items->perPage(),
            'page'  => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    public function showFeedback(Feedback $feedback): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $feedback->load(['school:id,name', 'user:id,name,email'])]);
    }

    public function updateFeedback(Request $request, Feedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'status'      => 'sometimes|in:new,in_progress,resolved,closed',
            'priority'    => 'sometimes|in:low,medium,high,critical',
            'assigned_to' => 'nullable|exists:users,id',
        ]);
        if (isset($validated['status']) && $validated['status'] === 'resolved' && !$feedback->resolved_at) {
            $validated['resolved_at'] = now();
        }
        $feedback->update($validated);
        return response()->json(['success' => true, 'data' => $feedback, 'message' => 'Feedback updated']);
    }

    public function replyFeedback(Request $request, Feedback $feedback): JsonResponse
    {
        $request->validate(['reply' => 'required|string']);
        $feedback->update(['reply' => $request->reply]);
        return response()->json(['success' => true, 'data' => $feedback, 'message' => 'Reply sent']);
    }

    // ── System Announcements ───────────────────────────────────────────────────
    public function announcements(): JsonResponse
    {
        $items = SystemAnnouncement::with('creator:id,name')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function createAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'target'       => 'required|in:all,plan,school',
            'target_value' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);
        $ann = SystemAnnouncement::create(array_merge($validated, [
            'created_by' => $request->user()->id,
            'sent_at'    => $validated['scheduled_at'] ? null : now(),
        ]));
        return response()->json(['success' => true, 'data' => $ann, 'message' => 'Announcement created'], 201);
    }

    public function deleteAnnouncement(SystemAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();
        return response()->json(['success' => true, 'message' => 'Announcement deleted']);
    }

    // ── Team ───────────────────────────────────────────────────────────────────
    public function team(): JsonResponse
    {
        $team = User::where('role', 'super_admin')->orderBy('name')->get(['id', 'name', 'email', 'created_at']);
        return response()->json(['success' => true, 'data' => $team]);
    }

    public function addTeamMember(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);
        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => $validated['password'],
            'role'      => 'super_admin',
            'school_id' => null,
        ]);
        return response()->json(['success' => true, 'data' => $user, 'message' => 'Team member added'], 201);
    }

    public function updateTeamMember(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8',
        ]);
        $user->update($validated);
        return response()->json(['success' => true, 'data' => $user, 'message' => 'Updated']);
    }

    public function deleteTeamMember(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['success' => false, 'message' => 'Cannot delete yourself'], 422);
        }
        $user->delete();
        return response()->json(['success' => true, 'message' => 'Team member removed']);
    }

    // ── Subscription Detail ────────────────────────────────────────────────────
    public function showSubscription(Subscription $subscription): JsonResponse
    {
        $subscription->load('school');
        $payments = SubscriptionPayment::where('subscription_id', $subscription->id)
            ->with('recorder:id,name')
            ->orderByDesc('payment_date')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'amount'       => $p->amount,
                'payment_date' => $p->payment_date?->toDateString(),
                'method'       => $p->method,
                'reference_no' => $p->reference_no,
                'period_label' => $p->period_label,
                'notes'        => $p->notes,
                'recorded_by'  => $p->recorder?->name,
                'created_at'   => $p->created_at?->toISOString(),
            ]);

        $totalPaid = $payments->sum('amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'subscription' => $subscription,
                'payments'     => $payments,
                'total_paid'   => $totalPaid,
            ],
        ]);
    }

    // ── Payments ───────────────────────────────────────────────────────────────
    public function payments(Request $request): JsonResponse
    {
        $q = SubscriptionPayment::with(['school:id,name', 'recorder:id,name'])
            ->orderByDesc('payment_date');

        if ($request->filled('school_id'))  $q->where('school_id', $request->school_id);
        if ($request->filled('method'))     $q->where('method', $request->method);
        if ($request->filled('from'))       $q->where('payment_date', '>=', $request->from);
        if ($request->filled('to'))         $q->where('payment_date', '<=', $request->to);

        $items = $q->paginate(25);

        return response()->json([
            'success' => true,
            'data'    => $items->items(),
            'meta'    => [
                'total'     => $items->total(),
                'page'      => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
            'total_amount' => SubscriptionPayment::when($request->filled('from'), fn($q) => $q->where('payment_date', '>=', $request->from))
                ->when($request->filled('to'), fn($q) => $q->where('payment_date', '<=', $request->to))
                ->sum('amount'),
        ]);
    }

    public function recordPayment(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'method'       => 'required|in:cash,upi,bank_transfer,cheque,online,other',
            'reference_no' => 'nullable|string|max:100',
            'period_label' => 'nullable|string|max:50',
            'notes'        => 'nullable|string',
        ]);

        $validated['school_id']       = $subscription->school_id;
        $validated['subscription_id'] = $subscription->id;
        $validated['recorded_by']     = $request->user()->id;

        $payment = SubscriptionPayment::create($validated);

        // Auto-mark subscription as active when payment recorded
        if ($subscription->status !== 'active') {
            $subscription->update(['status' => 'active']);
        }

        ActivityLog::create([
            'school_id'   => $subscription->school_id,
            'user_id'     => $request->user()->id,
            'module'      => 'payments',
            'action'      => 'payment_recorded',
            'description' => "₹{$validated['amount']} via {$validated['method']}",
        ]);

        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    public function deletePayment(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 403);
        }
        $payment->delete();
        return response()->json(['success' => true, 'message' => 'Payment record deleted.']);
    }

    public function deleteSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 403);
        }
        // Remove linked invoice payments, then invoices, then subscription payments, then subscription
        $invoiceIds = SubscriptionInvoice::where('subscription_id', $subscription->id)->pluck('id');
        SubscriptionPayment::whereIn('invoice_id', $invoiceIds)->delete();
        SubscriptionInvoice::whereIn('id', $invoiceIds)->delete();
        SubscriptionPayment::where('subscription_id', $subscription->id)->delete();
        $subscription->delete();

        ActivityLog::log(
            $request->user()->id,
            $subscription->school_id,
            'delete',
            'subscriptions',
            "Deleted subscription for school_id {$subscription->school_id}",
            '🗑️'
        );

        return response()->json(['success' => true, 'message' => 'Subscription deleted.']);
    }

    // ── School Timeline ─────────────────────────────────────────────────────────
    public function timeline(School $school): JsonResponse
    {
        $events = collect();

        // School creation
        $events->push([
            'type'        => 'school_created',
            'icon'        => '🏫',
            'title'       => 'School registered',
            'description' => "School \"{$school->name}\" was registered",
            'date'        => $school->created_at,
            'color'       => '#7c3aed',
        ]);

        // Subscription events
        $sub = $school->subscription;
        if ($sub) {
            $events->push([
                'type'        => 'subscription_created',
                'icon'        => '📋',
                'title'       => ucfirst($sub->plan) . ' Plan activated',
                'description' => "Status: {$sub->status} · Cycle: {$sub->billing_cycle}",
                'date'        => $sub->created_at,
                'color'       => '#3b82f6',
            ]);

            if ($sub->trial_ends_at) {
                $events->push([
                    'type'        => 'trial_info',
                    'icon'        => '⏳',
                    'title'       => 'Trial period',
                    'description' => 'Trial ends ' . $this->formatDate($sub->trial_ends_at),
                    'date'        => $sub->trial_ends_at,
                    'color'       => '#f59e0b',
                ]);
            }
        }

        // Invoice events
        $invoices = SubscriptionInvoice::where('school_id', $school->id)
            ->orderBy('created_at')
            ->get();

        foreach ($invoices as $inv) {
            $events->push([
                'type'        => 'invoice_created',
                'icon'        => '🧾',
                'title'       => "Invoice {$inv->invoice_no} generated",
                'description' => "{$inv->period_label} · ₹{$inv->total} · Status: {$inv->status}",
                'date'        => $inv->created_at,
                'color'       => '#0ea5e9',
            ]);

            if ($inv->paid_at) {
                $events->push([
                    'type'        => 'invoice_paid',
                    'icon'        => '✅',
                    'title'       => "Invoice {$inv->invoice_no} paid",
                    'description' => "₹{$inv->total} fully paid",
                    'date'        => $inv->paid_at,
                    'color'       => '#22c55e',
                ]);
            }
        }

        // Payment events
        $payments = SubscriptionPayment::where('school_id', $school->id)
            ->with('recorder:id,name')
            ->orderBy('payment_date')
            ->get();

        foreach ($payments as $pmt) {
            // Skip if already covered by invoice paid event
            if ($pmt->invoice_id) continue;
            $events->push([
                'type'        => 'payment_recorded',
                'icon'        => '💰',
                'title'       => "Payment recorded",
                'description' => "₹{$pmt->amount} via {$pmt->method}" . ($pmt->period_label ? " · {$pmt->period_label}" : ''),
                'date'        => $pmt->payment_date ?? $pmt->created_at,
                'color'       => '#22c55e',
            ]);
        }

        // Sort by date descending
        $sorted = $events->sortByDesc('date')->values()->map(fn($e) => array_merge($e, [
            'date' => $this->formatDate($e['date']),
        ]));

        return response()->json(['success' => true, 'data' => $sorted]);
    }
}

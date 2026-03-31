<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    // ── GET /settings ─────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $school = School::findOrFail($request->user()->school_id);

        return response()->json([
            'success' => true,
            'data'    => $this->format($school),
        ]);
    }

    // ── PUT /settings ─────────────────────────────────────────────
    public function update(Request $request): JsonResponse
    {
        $school = School::findOrFail($request->user()->school_id);

        $request->validate([
            'name'           => 'sometimes|string|max:200',
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:150',
            'website'        => 'nullable|string|max:200',
            'affiliation_no' => 'nullable|string|max:50',
            'settings'       => 'nullable|array',
        ]);

        $updateData = [];
        foreach (['name','address','phone','email','website','affiliation_no'] as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        if ($request->has('settings')) {
            $existing = $school->settings ?? [];
            $updateData['settings'] = array_merge($existing, $request->input('settings', []));
        }

        if (!empty($updateData)) {
            $school->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated',
            'data'    => $this->format($school->fresh()),
        ]);
    }

    // ── GET /staff ────────────────────────────────────────────────
    public function staff(Request $request): JsonResponse
    {
        $users = User::where('school_id', $request->user()->school_id)
            ->whereIn('role', ['super_admin', 'admin', 'teacher', 'staff'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $users->map(fn($u) => $this->formatUser($u)),
        ]);
    }

    // ── POST /staff ───────────────────────────────────────────────
    public function createUser(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => ['required','email','max:150', Rule::unique('users')->where('school_id', $schoolId)],
            'password' => 'required|string|min:6|max:50',
            'role'     => 'required|in:admin,teacher,staff',
            'phone'    => [
                'nullable', 'string', 'max:20',
                Rule::unique('users')->where('school_id', $schoolId),
            ],
        ]);

        $user = User::create([
            'school_id' => $schoolId,
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => $request->password,
            'role'      => $request->role,
            'phone'     => $request->phone,
            'status'    => 'Active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "User {$user->name} created",
            'data'    => $this->formatUser($user),
        ], 201);
    }

    // ── PUT /staff/{id} ───────────────────────────────────────────
    public function updateUser(Request $request, $id): JsonResponse
    {
        $user = User::where('school_id', $request->user()->school_id)->findOrFail($id);

        $request->validate([
            'name'   => 'sometimes|string|max:100',
            'phone'  => [
                'nullable', 'string', 'max:20',
                Rule::unique('users')->where('school_id', $user->school_id)->ignore($user->id),
            ],
            'role'   => 'nullable|in:admin,teacher,staff,super_admin',
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $updateData = [];
        foreach (['name','phone','role','status'] as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated',
            'data'    => $this->formatUser($user->fresh()),
        ]);
    }

    // ── Formatters ────────────────────────────────────────────────
    private function format(School $s): array
    {
        return [
            'id'             => $s->id,
            'name'           => $s->name ?? '',
            'address'        => $s->address ?? '',
            'phone'          => $s->phone ?? '',
            'email'          => $s->email ?? '',
            'logo'           => $s->logo,
            'website'        => $s->website ?? '',
            'affiliation_no' => $s->affiliation_no ?? '',
            'settings'       => $s->settings ?? [],
        ];
    }

    private function formatUser(User $u): array
    {
        return [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'role'       => $u->role,
            'status'     => $u->status ?? 'Active',
            'phone'      => $u->phone ?? '',
            'last_login' => $u->last_login ? (
                $u->last_login instanceof \Carbon\Carbon
                    ? $u->last_login->toDateString()
                    : substr($u->last_login, 0, 10)
            ) : '—',
        ];
    }
}

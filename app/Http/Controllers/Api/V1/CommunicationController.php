<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\MessageTemplate;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────

    private function isTemplates(Request $request): bool
    {
        return $request->is('*/templates*');
    }

    private function annQuery(Request $request)
    {
        return Announcement::where('school_id', $request->user()->school_id)
            ->with([
                'createdBy:id,name',
                'schoolClass:id,name',
                'section:id,name',
            ]);
    }

    private function tplQuery(Request $request)
    {
        return MessageTemplate::where('school_id', $request->user()->school_id);
    }

    // ── apiResource: index ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($this->isTemplates($request)) {
            $perPage = $request->integer('per_page', 50);
            $tpls = $this->tplQuery($request)
                ->orderByDesc('created_at')
                ->paginate($perPage);
            return response()->json(['success' => true, 'data' => $tpls]);
        }

        // Announcements
        $q = $this->annQuery($request)->orderByDesc('is_pinned')->orderByDesc('created_at');

        // Parents only see announcements targeted at all/students/parents/class/section
        if ($request->user()->role === 'parent') {
            $q->whereIn('audience', ['all', 'students', 'parents', 'class', 'section']);
        } elseif ($request->filled('audience')) {
            $q->where('audience', $request->audience);
        }
        if ($request->boolean('pinned')) {
            $q->where('is_pinned', true);
        }

        $perPage = $request->integer('per_page', 50);
        $list = $q->paginate($perPage);

        return response()->json(['success' => true, 'data' => $list]);
    }

    // ── apiResource: store ────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if ($this->isTemplates($request)) {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'category' => 'required|string|max:100',
                'body'     => 'required|string',
                'tags'     => 'sometimes|array',
                'tags.*'   => 'string',
            ]);
            $tpl = MessageTemplate::create([
                'school_id'  => $request->user()->school_id,
                'name'       => $data['name'],
                'category'   => $data['category'],
                'body'       => $data['body'],
                'tags'       => $data['tags'] ?? [],
                'created_by' => $request->user()->id,
            ]);
            return response()->json(['success' => true, 'data' => $tpl], 201);
        }

        // Announcements
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'body'       => 'required|string',
            'audience'   => 'required|in:all,students,staff,parents,class,section',
            'class_id'   => 'sometimes|nullable|exists:classes,id',
            'section_id' => 'sometimes|nullable|exists:sections,id',
            'is_pinned'  => 'sometimes|boolean',
        ]);

        $ann = Announcement::create([
            'school_id'  => $request->user()->school_id,
            'title'      => $data['title'],
            'body'       => $data['body'],
            'audience'   => $data['audience'],
            'class_id'   => $data['class_id']   ?? null,
            'section_id' => $data['section_id'] ?? null,
            'is_pinned'  => $data['is_pinned']  ?? false,
            'created_by' => $request->user()->id,
        ]);

        $ann->load(['createdBy:id,name', 'schoolClass:id,name', 'section:id,name']);

        ActivityLog::log(
            $request->user()->id, $request->user()->school_id,
            'create', 'communications',
            "Posted announcement: {$ann->title}",
            '📣'
        );

        // Push notification for pinned announcements — notify all school users
        if ($ann->is_pinned) {
            try {
                app(PushNotificationService::class)->sendToSchool(
                    $request->user()->school_id,
                    '📣 ' . $ann->title,
                    strip_tags($ann->body),
                    ['screen' => 'Announcements']
                );
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        return response()->json(['success' => true, 'data' => $ann], 201);
    }

    // ── apiResource: show ─────────────────────────────────────────

    public function show(Request $request, $id): JsonResponse
    {
        if ($this->isTemplates($request)) {
            $tpl = $this->tplQuery($request)->findOrFail($id);
            return response()->json(['success' => true, 'data' => $tpl]);
        }

        $ann = $this->annQuery($request)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $ann]);
    }

    // ── apiResource: update ───────────────────────────────────────

    public function update(Request $request, $id): JsonResponse
    {
        if ($this->isTemplates($request)) {
            $tpl = $this->tplQuery($request)->findOrFail($id);
            $data = $request->validate([
                'name'     => 'sometimes|string|max:255',
                'category' => 'sometimes|string|max:100',
                'body'     => 'sometimes|string',
                'tags'     => 'sometimes|array',
                'tags.*'   => 'string',
            ]);
            $tpl->update($data);
            return response()->json(['success' => true, 'data' => $tpl]);
        }

        // Announcements
        $ann = $this->annQuery($request)->findOrFail($id);
        $data = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'body'       => 'sometimes|string',
            'audience'   => 'sometimes|in:all,students,staff,parents,class,section',
            'class_id'   => 'sometimes|nullable|exists:classes,id',
            'section_id' => 'sometimes|nullable|exists:sections,id',
            'is_pinned'  => 'sometimes|boolean',
        ]);
        $ann->update($data);
        $ann->load(['createdBy:id,name', 'schoolClass:id,name', 'section:id,name']);
        return response()->json(['success' => true, 'data' => $ann]);
    }

    // ── apiResource: destroy ──────────────────────────────────────

    public function destroy(Request $request, $id): JsonResponse
    {
        if ($this->isTemplates($request)) {
            $tpl = $this->tplQuery($request)->findOrFail($id);
            $tpl->delete();
            return response()->json(['success' => true]);
        }

        $ann = $this->annQuery($request)->findOrFail($id);
        $ann->delete();
        return response()->json(['success' => true]);
    }

    // ── togglePin ─────────────────────────────────────────────────

    public function togglePin(Request $request, $id): JsonResponse
    {
        $ann = $this->annQuery($request)->findOrFail($id);
        $ann->update(['is_pinned' => !$ann->is_pinned]);
        $ann->load(['createdBy:id,name', 'schoolClass:id,name', 'section:id,name']);
        return response()->json(['success' => true, 'data' => $ann]);
    }

    // ── broadcast (POST /broadcasts) ──────────────────────────────

    public function broadcast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience_type'  => 'required|string',
            'audience_label' => 'required|string',
            'message'        => 'required|string',
            'reach'          => 'required|integer|min:0',
        ]);

        $brd = Broadcast::create([
            'school_id'      => $request->user()->school_id,
            'audience_type'  => $data['audience_type'],
            'audience_label' => $data['audience_label'],
            'message'        => $data['message'],
            'reach'          => $data['reach'],
            'status'         => 'sent',
            'sent_by'        => $request->user()->id,
            'sent_at'        => now(),
        ]);

        return response()->json(['success' => true, 'data' => $brd], 201);
    }

    // ── broadcastHistory (GET /broadcasts) ───────────────────────

    public function broadcastHistory(Request $request): JsonResponse
    {
        $list = Broadcast::where('school_id', $request->user()->school_id)
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $list]);
    }
}

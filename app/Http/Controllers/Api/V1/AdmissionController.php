<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdmissionEnquiry;
use App\Models\Student;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    private function fmt(AdmissionEnquiry $e): array
    {
        return [
            'id'            => $e->id,
            'student_name'  => $e->student_name,
            'dob'           => $e->dob?->toDateString(),
            'gender'        => $e->gender,
            'apply_class'   => $e->apply_class,
            'parent_name'   => $e->parent_name,
            'parent_phone'  => $e->parent_phone,
            'parent_email'  => $e->parent_email,
            'address'       => $e->address,
            'source'        => $e->source,
            'stage'         => $e->stage,
            'notes'         => $e->notes,
            'follow_up_date'=> $e->follow_up_date?->toDateString(),
            'date'          => $e->enquiry_date?->toDateString(),
            'assigned_to'   => $e->assignedTo
                ? ['id' => $e->assignedTo->id, 'name' => $e->assignedTo->name]
                : null,
        ];
    }

    // GET /admissions/enquiries
    public function index(Request $request): JsonResponse
    {
        $q = AdmissionEnquiry::where('school_id', $request->user()->school_id)
            ->with('assignedTo:id,name')
            ->orderByDesc('enquiry_date')
            ->orderByDesc('id');

        if ($request->filled('stage'))     $q->where('stage', $request->stage);
        if ($request->filled('search'))    $q->where(function($sq) use ($request) {
            $sq->where('student_name', 'like', '%'.$request->search.'%')
               ->orWhere('parent_phone', 'like', '%'.$request->search.'%')
               ->orWhere('parent_name', 'like', '%'.$request->search.'%');
        });
        if ($request->filled('follow_up') && $request->follow_up) {
            $q->where('follow_up_date', now()->toDateString());
        }

        $perPage = min((int)($request->per_page ?? 20), 100);
        $paginated = $q->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => collect($paginated->items())->map(fn($e) => $this->fmt($e)),
            'meta'    => [
                'page'      => $paginated->currentPage(),
                'per_page'  => $paginated->perPage(),
                'total'     => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    // POST /admissions/enquiries
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_name'   => 'required|string|max:200',
            'parent_name'    => 'required|string|max:200',
            'parent_phone'   => 'required|string|max:20',
            'apply_class'    => 'required|string|max:20',
            'dob'            => 'nullable|date',
            'gender'         => 'nullable|in:Male,Female,Other',
            'parent_email'   => 'nullable|email',
            'source'         => 'nullable|string|max:50',
            'notes'          => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'assigned_to'    => 'nullable|exists:users,id',
        ]);

        $e = AdmissionEnquiry::create([
            'school_id'      => $request->user()->school_id,
            'student_name'   => $request->student_name,
            'dob'            => $request->dob,
            'gender'         => $request->input('gender', 'Male'),
            'apply_class'    => $request->apply_class,
            'parent_name'    => $request->parent_name,
            'parent_phone'   => $request->parent_phone,
            'parent_email'   => $request->parent_email,
            'address'        => $request->address,
            'source'         => $request->input('source', 'Walk-in'),
            'stage'          => 'new',
            'notes'          => $request->notes,
            'follow_up_date' => $request->follow_up_date,
            'assigned_to'    => $request->assigned_to,
            'enquiry_date'   => now()->toDateString(),
        ]);

        $e->load('assignedTo:id,name');
        return response()->json(['success' => true, 'message' => 'Enquiry created', 'data' => $this->fmt($e)], 201);
    }

    // GET /admissions/enquiries/{enquiry}
    public function show(Request $request, AdmissionEnquiry $enquiry): JsonResponse
    {
        abort_if($enquiry->school_id !== $request->user()->school_id, 403);
        $enquiry->load('assignedTo:id,name');
        return response()->json(['success' => true, 'data' => $this->fmt($enquiry)]);
    }

    // PUT /admissions/enquiries/{enquiry}
    public function update(Request $request, AdmissionEnquiry $enquiry): JsonResponse
    {
        abort_if($enquiry->school_id !== $request->user()->school_id, 403);

        $request->validate([
            'student_name'   => 'sometimes|required|string|max:200',
            'parent_name'    => 'sometimes|required|string|max:200',
            'parent_phone'   => 'sometimes|required|string|max:20',
            'apply_class'    => 'sometimes|required|string|max:20',
            'dob'            => 'nullable|date',
            'gender'         => 'nullable|in:Male,Female,Other',
            'parent_email'   => 'nullable|email',
            'source'         => 'nullable|string|max:50',
            'notes'          => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'assigned_to'    => 'nullable|exists:users,id',
        ]);

        $fields = ['student_name','dob','gender','apply_class','parent_name',
                   'parent_phone','parent_email','address','source','notes',
                   'follow_up_date','assigned_to'];

        $data = [];
        foreach ($fields as $f) {
            if ($request->has($f)) $data[$f] = $request->input($f);
        }
        $enquiry->update($data);
        $enquiry->load('assignedTo:id,name');

        return response()->json(['success' => true, 'message' => 'Enquiry updated', 'data' => $this->fmt($enquiry)]);
    }

    // DELETE /admissions/enquiries/{enquiry}
    public function destroy(Request $request, AdmissionEnquiry $enquiry): JsonResponse
    {
        abort_if($enquiry->school_id !== $request->user()->school_id, 403);
        $enquiry->delete();
        return response()->json(['success' => true, 'message' => 'Enquiry deleted']);
    }

    // PUT /admissions/enquiries/{enquiry}/stage
    public function updateStage(Request $request, AdmissionEnquiry $enquiry): JsonResponse
    {
        abort_if($enquiry->school_id !== $request->user()->school_id, 403);
        $request->validate(['stage' => 'required|in:new,contacted,visit,docs,enrolled,rejected']);
        $enquiry->update(['stage' => $request->stage]);
        return response()->json(['success' => true, 'message' => 'Stage updated', 'data' => ['stage' => $enquiry->stage]]);
    }

    // POST /admissions/enquiries/{enquiry}/convert
    public function convert(Request $request, AdmissionEnquiry $enquiry): JsonResponse
    {
        abort_if($enquiry->school_id !== $request->user()->school_id, 403);

        $request->validate([
            'admission_no'     => 'required|string|max:50',
            'class_id'         => 'required|exists:classes,id',
            'section_id'       => 'required|exists:sections,id',
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        // Create student record from enquiry data
        $student = Student::create([
            'school_id'        => $enquiry->school_id,
            'admission_no'     => $request->admission_no,
            'name'             => $enquiry->student_name,
            'dob'              => $enquiry->dob,
            'gender'           => $enquiry->gender ?? 'Male',
            'class_id'         => $request->class_id,
            'section_id'       => $request->section_id,
            'academic_year_id' => $request->academic_year_id,
            'status'           => 'Active',
        ]);

        // Record admission link
        \App\Models\Admission::create([
            'school_id'        => $enquiry->school_id,
            'enquiry_id'       => $enquiry->id,
            'student_id'       => $student->id,
            'academic_year_id' => $request->academic_year_id,
            'admission_no'     => $request->admission_no,
            'class_id'         => $request->class_id,
            'section_id'       => $request->section_id,
            'admitted_at'      => now(),
        ]);

        // Mark enquiry as enrolled
        $enquiry->update(['stage' => 'enrolled']);

        return response()->json([
            'success' => true,
            'message' => "{$student->name} enrolled successfully",
            'data'    => [
                'student_id'   => $student->id,
                'admission_no' => $student->admission_no,
                'name'         => $student->name,
            ],
        ], 201);
    }

    // GET /admissions/stats
    public function stats(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $base     = AdmissionEnquiry::where('school_id', $schoolId);

        $total    = (clone $base)->count();
        $enrolled = (clone $base)->where('stage', 'enrolled')->count();

        $byStage = [];
        foreach (['new','contacted','visit','docs','enrolled','rejected'] as $s) {
            $byStage[$s] = (clone $base)->where('stage', $s)->count();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'by_stage'        => $byStage,
                'total'           => $total,
                'enrolled'        => $enrolled,
                'conversion_rate' => $total > 0 ? round($enrolled / $total * 100) : 0,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\FeeInvoice;
use App\Models\Homework;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\Teacher;
use App\Models\AdmissionEnquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // GET /dashboard/staff-stats
    public function staffStats(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $today    = now()->toDateString();

        // Admissions pipeline (staff's primary focus)
        $pipeline = AdmissionEnquiry::where('school_id', $schoolId)
            ->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage');

        $totalEnquiries = AdmissionEnquiry::where('school_id', $schoolId)->count();
        $totalEnrolled  = (int) ($pipeline->get('enrolled', 0));
        $conversionRate = $totalEnquiries > 0
            ? round(($totalEnrolled / $totalEnquiries) * 100, 1)
            : 0;

        $todayEnquiries = AdmissionEnquiry::where('school_id', $schoolId)
            ->whereDate('created_at', $today)
            ->count();

        $followUpsToday = AdmissionEnquiry::where('school_id', $schoolId)
            ->whereDate('follow_up_date', $today)
            ->whereNotIn('stage', ['enrolled', 'rejected'])
            ->count();

        $thisWeek = AdmissionEnquiry::where('school_id', $schoolId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $enrolledMonth = AdmissionEnquiry::where('school_id', $schoolId)
            ->where('stage', 'enrolled')
            ->whereMonth('updated_at', now()->month)
            ->count();

        $totalStudents = Student::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->count();

        $recentEnquiries = AdmissionEnquiry::where('school_id', $schoolId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'student_name', 'apply_class', 'stage', 'source',
                   'created_at', 'parent_name', 'parent_phone']);

        $announcements = Announcement::where('school_id', $schoolId)
            ->where('is_pinned', true)
            ->latest()
            ->limit(3)
            ->get(['id', 'title', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => [
                'admissions' => [
                    'pipeline'         => $pipeline,
                    'today'            => $todayEnquiries,
                    'follow_ups_today' => $followUpsToday,
                    'this_week'        => $thisWeek,
                    'enrolled_month'   => $enrolledMonth,
                    'total_enquiries'  => $totalEnquiries,
                    'conversion_rate'  => $conversionRate,
                ],
                'students'         => ['total' => $totalStudents],
                'announcements'    => $announcements,
                'recent_enquiries' => $recentEnquiries,
            ],
        ]);
    }

    // GET /dashboard/stats
    public function stats(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $today    = now()->toDateString();

        // Students & Teachers
        $activeStudents   = Student::where('school_id', $schoolId)->where('status', 'Active')->count();
        $inactiveStudents = Student::where('school_id', $schoolId)->where('status', 'Inactive')->count();
        $totalStudents    = $activeStudents + $inactiveStudents;
        $totalTeachers    = Teacher::where('school_id', $schoolId)->where('status', 'Active')->count();

        // Attendance today
        $todayAttendance = Attendance::where('school_id', $schoolId)
            ->where('date', $today)
            ->selectRaw("
                COUNT(*) as total,
                SUM(status IN ('Present','Late')) as present,
                SUM(status = 'Absent') as absent
            ")->first();

        $presentToday  = (int)($todayAttendance->present ?? 0);
        $absentToday   = (int)($todayAttendance->absent  ?? 0);
        $totalMarked   = (int)($todayAttendance->total   ?? 0);
        $attendancePct = $totalMarked > 0
            ? round(($presentToday / $totalMarked) * 100, 1)
            : 0;

        // Fees
        $feeStats = FeeInvoice::where('school_id', $schoolId)
            ->selectRaw("
                SUM(total)                   as total_billed,
                SUM(paid)                    as total_collected,
                SUM(total - paid - discount) as total_due,
                SUM(status = 'Unpaid')       as unpaid_count,
                SUM(status = 'Partial')      as partial_count,
                SUM(status = 'Paid')         as paid_count
            ")->first();

        // Admissions pipeline
        $enquiries = AdmissionEnquiry::where('school_id', $schoolId)
            ->selectRaw("stage, COUNT(*) as count")
            ->groupBy('stage')
            ->pluck('count', 'stage');

        $activeStages    = ['new', 'contacted', 'visit', 'docs'];
        $activeEnquiries = $enquiries->only($activeStages)->sum();
        $enrolledCount   = (int)($enquiries['enrolled'] ?? 0);

        $thisWeekEnquiries = AdmissionEnquiry::where('school_id', $schoolId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Homework
        $homeworkPending = Homework::where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('due_date', '>=', $today)
            ->count();

        $homeworkOverdue = Homework::where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('due_date', '<', $today)
            ->count();

        // Leaves
        $leaveStats = StudentLeave::where('school_id', $schoolId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'Pending')  as pending,
                SUM(status = 'Approved') as approved,
                SUM(status = 'Rejected') as rejected
            ")->first();

        // Pinned announcements for dashboard
        $announcements = Announcement::where('school_id', $schoolId)
            ->where('is_pinned', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'body', 'audience', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => [
                'students'   => [
                    'total'    => $totalStudents,
                    'active'   => $activeStudents,
                    'inactive' => $inactiveStudents,
                ],
                'teachers'   => [
                    'total'  => $totalTeachers,
                    'active' => $totalTeachers,
                ],
                'attendance' => [
                    'present_today'  => $presentToday,
                    'absent_today'   => $absentToday,
                    'total_students' => $totalStudents,
                    'percent_today'  => $attendancePct,
                ],
                'fees' => [
                    'total_billed'    => (float)($feeStats->total_billed    ?? 0),
                    'total_collected' => (float)($feeStats->total_collected ?? 0),
                    'total_due'       => (float)($feeStats->total_due       ?? 0),
                    'unpaid_count'    => (int)  ($feeStats->unpaid_count    ?? 0),
                    'partial_count'   => (int)  ($feeStats->partial_count   ?? 0),
                    'paid_count'      => (int)  ($feeStats->paid_count      ?? 0),
                ],
                'admissions' => [
                    'pipeline'   => $enquiries,
                    'total'      => $enquiries->sum(),
                    'active'     => $activeEnquiries,
                    'this_week'  => $thisWeekEnquiries,
                    'enrolled'   => $enrolledCount,
                ],
                'homework' => [
                    'pending' => $homeworkPending,
                    'overdue' => $homeworkOverdue,
                ],
                'leaves' => [
                    'total'    => (int)($leaveStats->total    ?? 0),
                    'pending'  => (int)($leaveStats->pending  ?? 0),
                    'approved' => (int)($leaveStats->approved ?? 0),
                    'rejected' => (int)($leaveStats->rejected ?? 0),
                ],
                'announcements' => $announcements,
            ],
        ]);
    }
}

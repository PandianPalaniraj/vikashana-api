<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\FeeController;
use App\Http\Controllers\Api\V1\HomeworkController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\CommunicationController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\TimetableController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\SystemAnnouncementController;
use App\Http\Controllers\Api\V1\SuperAdminController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\SchoolBillingController;

// ── Public ────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    Route::post('auth/login',         [AuthController::class, 'login']);
    Route::post('auth/select-school', [AuthController::class, 'selectSchool']);

    // Health check — used by monitoring and deploy verification
    Route::get('health', function () {
        try { \Illuminate\Support\Facades\DB::connection()->getPdo(); $db = 'connected'; }
        catch (\Exception $e) { $db = 'error: '.$e->getMessage(); }
        return response()->json([
            'success' => true,
            'status'  => 'healthy',
            'version' => '1.0.0',
            'time'    => now()->toISOString(),
            'db'      => $db,
        ]);
    });
});

// ── Authenticated ─────────────────────────────────────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'school.active'])->group(function () {

    // Auth
    Route::get ('auth/me',       [AuthController::class, 'me']);
    Route::post('auth/logout',   [AuthController::class, 'logout']);
    Route::put ('auth/password', [AuthController::class, 'changePassword']);
    Route::put ('auth/profile',   [AuthController::class, 'updateProfile']);
    Route::post('auth/profile',   [AuthController::class, 'updateProfile']); // POST for multipart/avatar upload
    Route::get ('auth/activity',  [AuthController::class, 'activity']);

    // Dashboard
    Route::get('dashboard/stats',       [DashboardController::class, 'stats']);
    Route::get('dashboard/staff-stats', [DashboardController::class, 'staffStats']);

    // Students
    Route::apiResource('students', StudentController::class);
    Route::get ('students/{student}/attendance',            [StudentController::class, 'attendance']);
    Route::get ('students/{student}/fees',                  [StudentController::class, 'fees']);
    Route::get ('students/{student}/marks',                 [StudentController::class, 'marks']);
    Route::post('students/{student}/reset-parent-password', [StudentController::class, 'resetParentPassword']);

    // Teachers
    Route::apiResource('teachers', TeacherController::class);
    // Literal routes MUST come before parameterised {teacher} routes
    Route::post('teachers/attendance',                [TeacherController::class, 'saveAttendance']);
    Route::get ('teachers/attendance/report',         [TeacherController::class, 'attendanceReport']);
    Route::get ('teachers/attendance/daily',          [TeacherController::class, 'attendanceDailyGrid']);
    Route::get ('teachers/{teacher}/attendance',      [TeacherController::class, 'attendance']);
    Route::post('teachers/{teacher}/reset-password',  [TeacherController::class, 'resetPassword']);

    // Classes CRUD
    Route::get   ('classes',              [ClassController::class, 'index']);
    Route::post  ('classes',              [ClassController::class, 'store']);
    Route::get   ('classes/{id}',         [ClassController::class, 'show']);
    Route::put   ('classes/{id}',         [ClassController::class, 'update']);
    Route::delete('classes/{id}',         [ClassController::class, 'destroy']);
    Route::get   ('classes/{id}/students',[ClassController::class, 'students']);

    // Sections CRUD
    Route::get   ('sections',     [ClassController::class, 'sections']);
    Route::post  ('sections',     [ClassController::class, 'storeSection']);
    Route::put   ('sections/{id}',[ClassController::class, 'updateSection']);
    Route::delete('sections/{id}',[ClassController::class, 'destroySection']);

    // Subjects CRUD
    Route::get   ('subjects',     [ClassController::class, 'subjects']);
    Route::post  ('subjects',     [ClassController::class, 'storeSubject']);
    Route::put   ('subjects/{id}',[ClassController::class, 'updateSubject']);
    Route::delete('subjects/{id}',[ClassController::class, 'destroySubject']);

    // Academic Years
    Route::get   ('academic-years',      [ClassController::class, 'academicYears']);
    Route::post  ('academic-years',      [ClassController::class, 'storeAcademicYear']);
    Route::put   ('academic-years/{id}', [ClassController::class, 'updateAcademicYear']);
    Route::delete('academic-years/{id}', [ClassController::class, 'deleteAcademicYear']);

    // Timetable
    Route::get   ('timetable/mine', [TimetableController::class, 'mine']);
    Route::get   ('timetable',      [TimetableController::class, 'index']);
    Route::post  ('timetable/bulk', [TimetableController::class, 'bulk']);
    Route::delete('timetable/{timetable}', [TimetableController::class, 'destroy']);

    // Attendance
    Route::get ('attendance',        [AttendanceController::class, 'index']);
    Route::post('attendance',        [AttendanceController::class, 'markBulk']);
    Route::get ('attendance/report', [AttendanceController::class, 'report']);
    Route::get ('attendance/summary',[AttendanceController::class, 'summary']);
    Route::put ('attendance/{id}',   [AttendanceController::class, 'update']);

    // Fees
    Route::apiResource('fees/invoices', FeeController::class);
    Route::post('fees/invoices/{invoice}/pay',     [FeeController::class, 'recordPayment']);
    Route::get ('fees/invoices/{invoice}/receipt', [FeeController::class, 'receipt']);
    Route::get ('fees/summary',                    [FeeController::class, 'summary']);
    Route::post('fees/bulk',                       [FeeController::class, 'createBulk']);

    // Homework
    Route::apiResource('homework', HomeworkController::class);

    // Exams & Marks
    Route::apiResource('exams', ExamController::class);
    Route::get   ('exams/{exam}/marks',                            [ExamController::class, 'getMarks']);
    Route::post  ('exams/{exam}/marks',                            [ExamController::class, 'saveMarks']);
    Route::get   ('exams/{exam}/report',                           [ExamController::class, 'report']);
    Route::get   ('exams/{exam}/timetable',                        [ExamController::class, 'timetable']);
    Route::post  ('exams/{exam}/timetable',                        [ExamController::class, 'addTimetableSlot']);
    Route::put   ('exams/{exam}/timetable/{examSubject}',          [ExamController::class, 'updateTimetableSlot']);
    Route::delete('exams/{exam}/timetable/{examSubject}',          [ExamController::class, 'deleteTimetableSlot']);

    // Admissions
    Route::apiResource('admissions/enquiries', AdmissionController::class);
    Route::put ('admissions/enquiries/{enquiry}/stage',   [AdmissionController::class, 'updateStage']);
    Route::post('admissions/enquiries/{enquiry}/convert', [AdmissionController::class, 'convert']);
    Route::get ('admissions/stats',                        [AdmissionController::class, 'stats']);

    // Communications
    Route::apiResource('announcements', CommunicationController::class);
    Route::put ('announcements/{announcement}/pin', [CommunicationController::class, 'togglePin']);
    Route::post('broadcasts',                       [CommunicationController::class, 'broadcast']);
    Route::get ('broadcasts',                       [CommunicationController::class, 'broadcastHistory']);
    Route::apiResource('templates', CommunicationController::class);

    // Parents (mobile app — requires mobile_enabled subscription)
    Route::middleware('mobile.access')->group(function () {
        Route::get('parents/my-children',                   [ParentController::class, 'myChildren']);
        Route::get('parents/student/{studentId}/dashboard', [ParentController::class, 'studentDashboard']);
    });

    // Settings
    Route::get('settings',  [SettingsController::class, 'index']);
    Route::put('settings',  [SettingsController::class, 'update']);
    Route::get('staff',     [SettingsController::class, 'staff']);
    Route::post('staff',    [SettingsController::class, 'createUser']);
    Route::put('staff/{id}',[SettingsController::class, 'updateUser']);

    // Feedback
    Route::get ('feedback', [FeedbackController::class, 'index']);
    Route::post('feedback', [FeedbackController::class, 'store']);

    // Billing (school admin view)
    Route::get('my-invoices', [\App\Http\Controllers\Api\V1\SchoolBillingController::class, 'myInvoices']);

    // Notifications
    Route::get('notifications',         [NotificationController::class,         'index']);
    Route::get('system-announcements',  [SystemAnnouncementController::class,   'index']);

    // Push tokens
    Route::post  ('push-tokens', [PushTokenController::class, 'store']);
    Route::delete('push-tokens', [PushTokenController::class, 'destroy']);

    // Test push (remove in production)
    Route::post('test-push', function (\Illuminate\Http\Request $request) {
        $request->validate(['title' => 'required|string', 'body' => 'required|string']);
        app(\App\Services\PushNotificationService::class)->sendToUser(
            $request->user()->id,
            $request->title,
            $request->body,
            $request->input('data', [])
        );
        return response()->json(['message' => 'Test push sent to your devices']);
    });

    // Leaves
    Route::get   ('leaves',          [LeaveController::class, 'index']);
    Route::post  ('leaves',          [LeaveController::class, 'store']);
    Route::put   ('leaves/{leave}',  [LeaveController::class, 'update']);
    Route::delete('leaves/{leave}',  [LeaveController::class, 'destroy']);

    // Quiz
    Route::get ('quiz/classes',            [QuizController::class, 'classes']);
    Route::get ('quiz/subjects',           [QuizController::class, 'subjects']);
    Route::get ('quiz/questions',          [QuizController::class, 'questions']);
    Route::post('quiz/check',              [QuizController::class, 'check']);
    Route::get ('quiz/manage',             [QuizController::class, 'index']);
    Route::post('quiz/manage',             [QuizController::class, 'store']);
    Route::put ('quiz/manage/{question}',  [QuizController::class, 'update']);
    Route::delete('quiz/manage/{question}',[QuizController::class, 'destroy']);
});

// ── Super Admin Public ─────────────────────────────────────────
Route::prefix('superadmin/v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
});

// ── Super Admin Authenticated ──────────────────────────────────
Route::prefix('superadmin/v1')->middleware(['auth:sanctum', 'role:super_admin'])->group(function () {

    // Dashboard
    Route::get('stats', [SuperAdminController::class, 'stats']);

    // Schools
    Route::get   ('schools',                          [SuperAdminController::class, 'schools']);
    Route::post  ('schools',                          [SuperAdminController::class, 'createSchool']);
    Route::post  ('schools/register',                 [SuperAdminController::class, 'registerSchool']);
    Route::get   ('schools/{school}',                 [SuperAdminController::class, 'showSchool']);
    Route::delete('schools/{school}',                 [SuperAdminController::class, 'deleteSchool']);
    Route::put   ('schools/{school}',                 [SuperAdminController::class, 'updateSchool']);
    Route::post  ('schools/{school}/toggle-status',   [SuperAdminController::class, 'toggleStatus']);
    Route::get   ('schools/{school}/stats',           [SuperAdminController::class, 'schoolStats']);
    Route::post  ('schools/{school}/subscription',    [SuperAdminController::class, 'createSubscription']);
    Route::post  ('schools/{school}/impersonate',          [SuperAdminController::class, 'impersonate']);
    Route::get   ('schools/{school}/timeline',             [SuperAdminController::class, 'timeline']);
    Route::post  ('schools/{school}/reset-admin-password', [SuperAdminController::class, 'resetAdminPassword']);

    // Subscriptions
    Route::get('subscriptions',                                        [SuperAdminController::class, 'subscriptions']);
    Route::get('subscriptions/{subscription}',                         [SuperAdminController::class, 'showSubscription']);
    Route::put('subscriptions/{subscription}',                         [SuperAdminController::class, 'updateSubscription']);
    Route::post  ('subscriptions/{subscription}/extend-trial',      [SuperAdminController::class, 'extendTrial']);
    Route::post  ('subscriptions/{subscription}/sync-student-count',[SuperAdminController::class, 'syncStudentCount']);
    Route::post  ('subscriptions/{subscription}/payments',          [SuperAdminController::class, 'recordPayment']);
    Route::delete('subscriptions/{subscription}',                   [SuperAdminController::class, 'deleteSubscription']);

    // Payments
    Route::get   ('payments',            [SuperAdminController::class, 'payments']);
    Route::delete('payments/{payment}',  [SuperAdminController::class, 'deletePayment']);

    // Feedback
    Route::get ('feedback',              [SuperAdminController::class, 'feedback']);
    Route::get ('feedback/{feedback}',   [SuperAdminController::class, 'showFeedback']);
    Route::put ('feedback/{feedback}',   [SuperAdminController::class, 'updateFeedback']);
    Route::post('feedback/{feedback}/reply', [SuperAdminController::class, 'replyFeedback']);

    // System Announcements
    Route::get   ('announcements',                [SuperAdminController::class, 'announcements']);
    Route::post  ('announcements',                [SuperAdminController::class, 'createAnnouncement']);
    Route::delete('announcements/{announcement}', [SuperAdminController::class, 'deleteAnnouncement']);

    // Team
    Route::get   ('team',        [SuperAdminController::class, 'team']);
    Route::post  ('team',        [SuperAdminController::class, 'addTeamMember']);
    Route::put   ('team/{user}', [SuperAdminController::class, 'updateTeamMember']);
    Route::delete('team/{user}', [SuperAdminController::class, 'deleteTeamMember']);

    // Invoices
    Route::get ('invoices/schools',          [\App\Http\Controllers\Api\V1\InvoiceController::class, 'schools']);
    Route::get ('invoices',                  [\App\Http\Controllers\Api\V1\InvoiceController::class, 'index']);
    Route::post('invoices/generate',         [\App\Http\Controllers\Api\V1\InvoiceController::class, 'generate']);
    Route::get ('invoices/{id}',             [\App\Http\Controllers\Api\V1\InvoiceController::class, 'show']);
    Route::put   ('invoices/{id}/send',     [\App\Http\Controllers\Api\V1\InvoiceController::class, 'markSent']);
    Route::put   ('invoices/{id}/cancel',   [\App\Http\Controllers\Api\V1\InvoiceController::class, 'cancel']);
    Route::put   ('invoices/{id}',          [\App\Http\Controllers\Api\V1\InvoiceController::class, 'update']);
    Route::delete('invoices/{id}',          [\App\Http\Controllers\Api\V1\InvoiceController::class, 'destroy']);
    Route::post  ('invoices/{id}/payments', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'recordPayment']);
});
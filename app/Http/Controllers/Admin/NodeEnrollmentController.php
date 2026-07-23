<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NodeEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveNodeEnrollmentRequest;
use App\Models\NodeEnrollment;
use App\Services\Enrollment\EnrollmentException;
use App\Services\Enrollment\NodeEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class NodeEnrollmentController extends Controller
{
    public function __construct(private NodeEnrollmentService $service) {}

    public function index()
    {
        $enrollments = NodeEnrollment::with('node')->latest()->paginate(25);

        return view('admin.node-enrollments.index', compact('enrollments'));
    }

    public function show(NodeEnrollment $enrollment)
    {
        $enrollment->load(['node', 'claimant']);

        return view('admin.node-enrollments.show', compact('enrollment'));
    }

    public function claim(Request $request)
    {
        return view('admin.node-enrollments.claim', ['code' => $request->string('code')->toString()]);
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $attemptKey = 'node-enrollment-code:'.hash('sha256', strtoupper(str_replace([' ', '-'], '', $validated['code'])));
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return back()->withErrors(['code' => 'Trop de tentatives. Réessayez plus tard.'])->withInput();
        }
        try {
            $enrollment = $this->service->claim($validated['code'], (int) $request->user()->id);
        } catch (EnrollmentException $exception) {
            RateLimiter::hit($attemptKey, 600);

            return back()->withErrors(['code' => $exception->getMessage()])->withInput();
        }
        RateLimiter::clear($attemptKey);

        return redirect()->route('admin.node-enrollments.show', $enrollment);
    }

    public function approve(ApproveNodeEnrollmentRequest $request, NodeEnrollment $enrollment)
    {
        $this->service->approve($enrollment, $request->validated());

        return redirect()->route('admin.node-enrollments.show', $enrollment)->with('success', 'Node approuvé. L’installation peut continuer.');
    }

    public function deny(NodeEnrollment $enrollment)
    {
        $this->service->deny($enrollment);

        return back()->with('success', 'Enrôlement refusé.');
    }

    public function revoke(NodeEnrollment $enrollment)
    {
        $this->service->revoke($enrollment);

        return back()->with('success', 'Enrôlement révoqué.');
    }

    public function retry(NodeEnrollment $enrollment)
    {
        abort_unless(in_array($enrollment->status, [NodeEnrollmentStatus::Failed, NodeEnrollmentStatus::Validating], true), 409);
        $enrollment->update(['status' => NodeEnrollmentStatus::Validating, 'error_code' => null, 'sanitized_error' => null]);
        $this->service->validateAgent($enrollment->load('node'));

        return back()->with('success', 'Validation relancée.');
    }

    public function status(NodeEnrollment $enrollment)
    {
        return response()->json([
            'status' => $enrollment->status->value,
            'step' => $enrollment->step->value,
            'percentage' => $enrollment->percentage,
            'message' => $enrollment->public_message,
            'error_code' => $enrollment->error_code,
            'error_message' => $enrollment->sanitized_error,
            'terminal' => $enrollment->status->terminal(),
        ]);
    }

    public function automatic(ApproveNodeEnrollmentRequest $request)
    {
        $created = $this->service->createAutomatic($request->validated());

        return redirect()->route('admin.node-enrollments.show', $created['enrollment'])
            ->with('one_time_enrollment_token', $created['token']);
    }
}

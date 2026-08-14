<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\Program;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Faculty-side: propose an edit/delete on a course THEY created (route
 * middleware is role:faculty; ownership is checked here per-request since
 * a faculty account could otherwise pass someone else's course id).
 * Admin/intern-side: review the queue, approve or reject.
 *
 * Hold-until-approved: nothing about the course changes until an
 * admin/intern approves the request.
 */
class CourseChangeRequestController extends Controller
{
    public function editForm(Request $request, Course $course): View
    {
        $this->authorizeOwner($request, $course);

        return view('courses.request-edit', [
            'course' => $course,
            'programs' => Program::orderBy('code')->get(),
        ]);
    }

    public function requestUpdate(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeOwner($request, $course);
        $this->blockIfAlreadyPending($course);

        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'course_code' => [
                'required', 'string', 'max:20',
                Rule::unique('courses')
                    ->where(fn ($q) => $q->where('program_id', $request->input('program_id')))
                    ->ignore($course->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'year_level' => ['required', 'integer', 'min:1', 'max:4'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            'prerequisite' => ['nullable', 'string', 'max:255'],
            'corequisite' => ['nullable', 'string', 'max:255'],
            'lecture_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'lab_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'credited_units' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'tuition_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);

        // Per Rico, 2026-08-12: pressing "Submit" with no actual field
        // changes should be rejected — there's nothing for an admin to
        // review.
        if ($this->hasNoChanges($course, $validated)) {
            return back()
                ->withErrors(['request' => 'No changes were made. Please update at least one field before submitting your request.'])
                ->withInput();
        }

        CourseChangeRequest::create([
            'course_id' => $course->id,
            'requested_by' => $request->user()->id,
            'action' => 'update',
            'payload' => $validated,
            'status' => 'pending',
        ]);

        return redirect()->route('courses.show', $course)
            ->with('status', 'Your edit request has been submitted and is awaiting admin approval. The course has not been changed yet.');
    }

    public function requestDelete(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeOwner($request, $course);
        $this->blockIfAlreadyPending($course);

        CourseChangeRequest::create([
            'course_id' => $course->id,
            'requested_by' => $request->user()->id,
            'action' => 'delete',
            'status' => 'pending',
        ]);

        return redirect()->route('courses.show', $course)
            ->with('status', 'Your delete request has been submitted and is awaiting admin approval.');
    }

    /** Admin/intern: queue of pending requests. */
    public function index(): View
    {
        $requests = CourseChangeRequest::with(['course', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.course-requests.index', compact('requests'));
    }

    public function approve(Request $request, CourseChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === 'pending', 404);

        try {
            if ($changeRequest->action === 'update') {
                $changeRequest->course->update($changeRequest->payload->getArrayCopy());
            } else {
                $changeRequest->course->delete();
            }
        } catch (QueryException $e) {
            return back()->withErrors(['request' => 'The request could not be applied — there may be a conflict (e.g., a duplicate course code). Details: ' . $e->getMessage()]);
        }

        $changeRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Request approved.');
    }

    public function reject(Request $request, CourseChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === 'pending', 404);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:255'],
        ]);

        $changeRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        return back()->with('status', 'Request rejected.');
    }

    private function authorizeOwner(Request $request, Course $course): void
    {
        abort_unless($course->created_by === $request->user()->id, 403, 'You did not create this course, so you cannot edit or delete it.');
    }

    private function blockIfAlreadyPending(Course $course): void
    {
        if ($course->hasPendingChangeRequest()) {
            abort(422, 'A request for this course is already pending. Please wait for it to be reviewed before submitting another.');
        }
    }

    /**
     * True if every field in $validated matches the course's current
     * value — i.e., the faculty member submitted the form without
     * actually changing anything. Loose (!=) comparison so e.g. the
     * decimal string "2" from a form input still matches the model's
     * "2.0" without a false "changed" positive.
     */
    private function hasNoChanges(Course $course, array $validated): bool
    {
        foreach ($validated as $field => $newValue) {
            if (($course->{$field} ?? '') != ($newValue ?? '')) {
                return false;
            }
        }

        return true;
    }
}

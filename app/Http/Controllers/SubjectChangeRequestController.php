<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectChangeRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Faculty-side: propose an edit/delete on a subject THEY created (route
 * middleware is role:faculty; ownership is checked here per-request since
 * a faculty account could otherwise pass someone else's subject id).
 * Admin/intern-side: review the queue, approve or reject.
 *
 * Hold-until-approved: nothing about the subject changes until an
 * admin/intern approves the request.
 */
class SubjectChangeRequestController extends Controller
{
    public function editForm(Request $request, Subject $subject): View
    {
        $this->authorizeOwner($request, $subject);

        return view('subjects.request-edit', [
            'subject' => $subject,
            'programs' => Program::orderBy('code')->get(),
        ]);
    }

    public function requestUpdate(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorizeOwner($request, $subject);
        $this->blockIfAlreadyPending($subject);

        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'subject_code' => [
                'required', 'string', 'max:20',
                Rule::unique('subjects')
                    ->where(fn ($q) => $q->where('program_id', $request->input('program_id')))
                    ->ignore($subject->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'year_level' => ['required', 'integer', 'min:1', 'max:10'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            'prerequisite' => ['nullable', 'string', 'max:255'],
            'corequisite' => ['nullable', 'string', 'max:255'],
            'lecture_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'lab_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'credited_units' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'tuition_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);

        SubjectChangeRequest::create([
            'subject_id' => $subject->id,
            'requested_by' => $request->user()->id,
            'action' => 'update',
            'payload' => $validated,
            'status' => 'pending',
        ]);

        return redirect()->route('subjects.show', $subject)
            ->with('status', 'Naipasa ang edit request — naghihintay ng admin approval. Hindi pa nagbabago ang subject.');
    }

    public function requestDelete(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorizeOwner($request, $subject);
        $this->blockIfAlreadyPending($subject);

        SubjectChangeRequest::create([
            'subject_id' => $subject->id,
            'requested_by' => $request->user()->id,
            'action' => 'delete',
            'status' => 'pending',
        ]);

        return redirect()->route('subjects.show', $subject)
            ->with('status', 'Naipasa ang delete request — naghihintay ng admin approval.');
    }

    /** Admin/intern: queue of pending requests. */
    public function index(): View
    {
        $requests = SubjectChangeRequest::with(['subject', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.subject-requests.index', compact('requests'));
    }

    public function approve(Request $request, SubjectChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === 'pending', 404);

        try {
            if ($changeRequest->action === 'update') {
                $changeRequest->subject->update($changeRequest->payload->getArrayCopy());
            } else {
                $changeRequest->subject->delete();
            }
        } catch (QueryException $e) {
            return back()->withErrors(['request' => 'Hindi na-apply ang request — posibleng may conflict na (hal. duplicate subject code). Detalye: ' . $e->getMessage()]);
        }

        $changeRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Na-approve ang request.');
    }

    public function reject(Request $request, SubjectChangeRequest $changeRequest): RedirectResponse
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

        return back()->with('status', 'Na-reject ang request.');
    }

    private function authorizeOwner(Request $request, Subject $subject): void
    {
        abort_unless($subject->created_by === $request->user()->id, 403, 'Hindi mo ito nagawa, kaya hindi mo puwedeng i-edit/i-delete.');
    }

    private function blockIfAlreadyPending(Subject $subject): void
    {
        if ($subject->hasPendingChangeRequest()) {
            abort(422, 'May naka-pending nang request para sa subject na ito. Hintayin munang ma-review bago mag-submit ulit.');
        }
    }
}

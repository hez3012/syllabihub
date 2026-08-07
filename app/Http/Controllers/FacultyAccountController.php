<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin/intern tool for creating faculty login accounts. Replaces the old
 * "assign subjects to faculty" feature (dropped — faculty now create/own
 * their own subjects directly, see SubjectController/SubjectChangeRequestController).
 *
 * Emailing the credentials to the actual faculty member is a manual step
 * outside this app (per Rico: dev team handles it, real emails collected
 * via a separate Google Form) — this controller only creates the account.
 */
class FacultyAccountController extends Controller
{
    public function index(): View
    {
        $faculty = User::query()
            ->where('role', 'faculty')
            ->withCount('createdSubjects')
            ->orderBy('name')
            ->get();

        return view('admin.faculty-accounts.index', compact('faculty'));
    }

    public function create(): View
    {
        return view('admin.faculty-accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'faculty',
        ]);

        return redirect()->route('faculty-accounts.index')
            ->with('status', "Nagawa ang faculty account para kay {$validated['name']}. I-relay ang email/password sa kanila (manual, outside ng system).");
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $activeTab = $request->get('tab', 'logs');

        $logs = AuditLog::orderByDesc('created_at')->paginate(20);
        $trails = AuditTrail::orderByDesc('created_at')->paginate(20);

        return view('admin.audit.index', compact('activeTab', 'logs', 'trails'));
    }
}

<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\Request;

class BugReportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'steps' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'string', 'max:2000'],
            'user_agent' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['tenant_uuid'] = session('tenant_uuid');
        $data['user_id'] = auth()->id();

        BugReport::create($data);

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TenantModuleController extends Controller
{
    public function imports(): View
    {
        return view('tenant.module', ['title' => 'Imports']);
    }

    public function campaigns(): View
    {
        return view('tenant.module', ['title' => 'Campaigns']);
    }

    public function inbox(): View
    {
        return view('tenant.module', ['title' => 'Inbox']);
    }

    public function exports(): View
    {
        return view('tenant.module', ['title' => 'Exports']);
    }
}

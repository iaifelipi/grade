<?php

namespace App\Http\Controllers\Admin\Monetization;

use App\Http\Controllers\Controller;
use App\Services\Admin\Monetization\MonetizationDashboardService;
use Illuminate\View\View;

class MonetizationDashboardController extends Controller
{
    public function index(MonetizationDashboardService $service): View
    {
        return view('admin.monetization.dashboard', $service->summary());
    }
}

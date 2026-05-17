<?php

namespace App\Http\Controllers\Admin;

use App\Services\Analytics\AnalyticsService;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index()
    {
        return response()->json($this->analytics->dashboard());
    }
}

<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class AdminViewController extends Controller
{
    public function login()           { return view('admin.login'); }
    public function dashboard()       { return view('admin.dashboard'); }
    public function inbox()           { return view('admin.inbox'); }
    public function conversations()   { return view('admin.conversations'); }
    public function comments()        { return view('admin.comments'); }
    public function aiSettings()      { return view('admin.ai-settings'); }
    public function integrations()    { return view('admin.integrations'); }
    public function products()        { return view('admin.products'); }
    public function orders()          { return view('admin.orders'); }
    public function escalations()     { return view('admin.escalations'); }
    public function analytics()       { return view('admin.analytics'); }
    public function settings()        { return view('admin.settings'); }
    public function setupChecklist()  { return view('admin.setup-checklist'); }
    public function health()          { return view('admin.health'); }
    public function testChecklist()   { return view('admin.test-checklist'); }
    public function metaReadiness()   { return view('admin.meta-readiness'); }
    public function privacySafety()   { return view('admin.privacy-safety'); }
    public function messengerBeta()   { return view('admin.messenger-beta'); }
}

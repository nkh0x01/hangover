<?php

namespace App\Http\Controllers\Admin;

use App\Models\AiPrompt;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function listPrompts(Request $request)
    {
        return response()->json(['data' => AiPrompt::orderByDesc('id')->limit(100)->get()]);
    }

    public function showPrompt(string $slug)
    {
        return response()->json(['data' => AiPrompt::where('slug', $slug)->orderByDesc('version')->limit(20)->get()]);
    }

    public function savePrompt(Request $request)
    {
        $data = $request->validate([
            'slug'  => 'required|string|max:64',
            'body'  => 'required|string|max:20000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $latest = AiPrompt::where('slug', $data['slug'])->max('version') ?? 0;

        $prompt = AiPrompt::create([
            'slug'       => $data['slug'],
            'version'    => $latest + 1,
            'is_active'  => false,
            'body'       => $data['body'],
            'notes'      => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        AuditLog::record('employee', 'prompt.draft', 'ai_prompts', $prompt->id, ['slug' => $data['slug']], $request->user()?->id);

        return response()->json(['data' => $prompt]);
    }

    public function activatePrompt(Request $request, int $id)
    {
        $prompt = AiPrompt::findOrFail($id);
        AiPrompt::where('slug', $prompt->slug)->update(['is_active' => false]);
        $prompt->update(['is_active' => true]);

        AuditLog::record('employee', 'prompt.activate', 'ai_prompts', $prompt->id, ['slug' => $prompt->slug], $request->user()?->id);

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Services\IntegrationTester;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntegrationsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private IntegrationTester $tester,
    ) {}

    public function show(string $group)
    {
        if (! array_key_exists($group, SettingsService::GROUPS)) {
            return response()->json(['error' => 'unknown_group'], 404);
        }
        return response()->json([
            'group' => $group,
            'settings' => $this->settings->groupPayload($group),
        ]);
    }

    public function all()
    {
        $out = [];
        foreach (array_keys(SettingsService::GROUPS) as $group) {
            $out[$group] = $this->settings->groupPayload($group);
        }
        return response()->json(['settings' => $out]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'group' => 'required|string|in:'.implode(',', array_keys(SettingsService::GROUPS)),
            'values' => 'required|array',
            'values.*' => 'nullable|string',
        ]);

        $allowedKeys = SettingsService::GROUPS[$data['group']];
        $auditLines = [];
        $written = 0;

        foreach ($data['values'] as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                $auditLines[] = "  - {$key}: SKIPPED (not in allowed keys for group)";
                continue;
            }
            // Empty string = "leave existing value" so users can save without
            // re-typing every secret. Explicit removal is via DELETE.
            if ($value === null || $value === '') {
                $auditLines[] = "  - {$key}: SKIPPED (empty value)";
                continue;
            }
            $this->settings->set($key, $value, $data['group']);
            $len = strlen((string) $value);
            $auditLines[] = "  - {$key}: SAVED (len={$len})";
            $written++;
        }

        // Audit log to disk — diagnostic for "I saved but it didn't persist".
        // Logs only metadata: group, key names, value LENGTHS. NEVER the values.
        $entry = sprintf(
            "[%s] user=%s ip=%s group=%s written=%d/%d\n%s\n",
            now()->toIso8601String(),
            optional($request->user())->email ?? '?',
            $request->ip(),
            $data['group'],
            $written,
            count($data['values']),
            implode("\n", $auditLines),
        );
        @file_put_contents(storage_path('logs/admin-settings-save.log'), $entry, FILE_APPEND | LOCK_EX);

        return response()->json([
            'ok' => true,
            'written' => $written,
            'audit' => $auditLines,
            'settings' => $this->settings->groupPayload($data['group']),
        ]);
    }

    public function test(string $type)
    {
        if (! array_key_exists($type, SettingsService::GROUPS) && $type !== 'escalation') {
            return response()->json(['success' => false, 'message' => 'unknown_type'], 404);
        }
        return response()->json($this->tester->test($type));
    }

    public function delete(string $group, string $key)
    {
        if (! array_key_exists($group, SettingsService::GROUPS)) {
            return response()->json(['error' => 'unknown_group'], 404);
        }
        if (! in_array($key, SettingsService::GROUPS[$group], true)) {
            return response()->json(['error' => 'unknown_key'], 404);
        }
        $this->settings->forget($key);
        return response()->json([
            'ok' => true,
            'settings' => $this->settings->groupPayload($group),
        ]);
    }
}

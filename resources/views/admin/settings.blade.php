@extends('admin.layout')

@section('title', 'Settings')
@section('subtitle', 'პროფილი, password, team და systemic preferences')

@section('content')
<div x-data="settingsPage()">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">

            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-4">პროფილი</h3>
                <div class="space-y-3">
                    <div>
                        <div class="field-label">სახელი</div>
                        <input class="field-input" :value="$root.me?.name" readonly>
                    </div>
                    <div>
                        <div class="field-label">ელფოსტა</div>
                        <input class="field-input" :value="$root.me?.email" readonly>
                    </div>
                    <div>
                        <div class="field-label">როლი</div>
                        <input class="field-input" :value="$root.me?.role" readonly>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">
                    Read-only ამ ეტაპზე. პროფილის რედაქტირება მზადდება.
                </p>
            </div>

            <div class="card p-5 border-l-4 border-amber-400">
                <h3 class="font-semibold text-slate-900 mb-2">⚠️ Password Change</h3>
                <p class="text-sm text-slate-600 mb-4">
                    UI-ში password change ფუნქცია მზადდება. სანამ მზადაა, შეცვალე CLI-დან:
                </p>
                <pre class="bg-slate-900 text-slate-200 text-xs p-3 rounded-lg overflow-x-auto"><code>cd ~/bot.gadget.ge_app
/opt/cpanel/ea-php83/root/usr/bin/php artisan tinker
$u = App\Models\Employee::where('email','owner@gadget.ge')->first();
$u->password = Hash::make('YOUR-STRONG-PASSWORD');
$u->save();</code></pre>
            </div>

            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Demo credentials warning</h3>
                <p class="text-sm text-slate-600 mb-3">
                    Default credentials არის <code class="bg-slate-100 px-1.5 py-0.5 rounded">owner@gadget.ge / password</code>.
                    ეს არ უნდა იყოს live-ზე.
                </p>
                <button @click="localStorage.removeItem('demo_banner_dismissed'); $root.toast('Banner-ი ისევ ჩანს', 'success')"
                        class="btn btn-secondary text-xs">
                    Demo banner-ის გამოჩენა
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Other configuration</h3>
                <div class="space-y-2 text-sm">
                    <a href="/admin/integrations" class="flex justify-between text-slate-700 hover:text-brand-700">
                        <span>Integrations</span><span>→</span>
                    </a>
                    <a href="/admin/ai-settings" class="flex justify-between text-slate-700 hover:text-brand-700">
                        <span>AI Settings</span><span>→</span>
                    </a>
                    <a href="/admin/setup-checklist" class="flex justify-between text-slate-700 hover:text-brand-700">
                        <span>Setup checklist</span><span>→</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function settingsPage() {
    return {};
}
</script>
@endpush
@endsection

@extends('admin.layout')

@section('title', 'Privacy & Safety')
@section('subtitle', 'როგორ იცავს ბოტი კლიენტებსა და ბიზნესს')

@section('content')
<div x-data="privacySafety()" x-init="load()">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 grid place-items-center">🛡</span>
                პროდუქტის სიზუსტე
            </h3>
            <ul class="space-y-2 text-sm text-slate-700">
                <li class="flex gap-2"><span class="text-emerald-600">✓</span> AI <strong>ვერ იგონებს</strong> პროდუქტს, ფასს, მარაგს ან ფასდაკლებას</li>
                <li class="flex gap-2"><span class="text-emerald-600">✓</span> ყველა პროდუქტის მონაცემი მხოლოდ <strong>WooCommerce REST API</strong>-დან</li>
                <li class="flex gap-2"><span class="text-emerald-600">✓</span> Validator ამოწმებს — თუ AI ცდილობს catalog-ის გარეთ ბრენდი ახსენოს, reply იბლოკება</li>
                <li class="flex gap-2"><span class="text-emerald-600">✓</span> თუ WC-ში პროდუქტი ვერ მოიძებნა → "ამაზე გადავამოწმებ გუნდთან" (ნუ მოიგონებს)</li>
            </ul>
        </div>

        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 grid place-items-center">⚠</span>
                ესკალაცია & ადამიანი
            </h3>
            <ul class="space-y-2 text-sm text-slate-700">
                <li class="flex gap-2"><span class="text-amber-600">✓</span> Complaint / warranty / refund / legal / angry → <strong>auto-reply არ ხდება</strong></li>
                <li class="flex gap-2"><span class="text-amber-600">✓</span> Human takeover — თანამშრომელს შეუძლია ნებისმიერ დროს აიყვანოს საუბარი (AI ჩერდება)</li>
                <li class="flex gap-2"><span class="text-amber-600">✓</span> Escalated conversations → AI არ ერევა</li>
                <li class="flex gap-2"><span class="text-amber-600">✓</span> Emergency Stop — ერთი ღილაკით ყველა AI reply ჩერდება</li>
            </ul>
        </div>

        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-brand-100 text-brand-700 grid place-items-center">🔒</span>
                მონაცემები & secrets
            </h3>
            <ul class="space-y-2 text-sm text-slate-700">
                <li class="flex gap-2"><span class="text-brand-600">✓</span> API keys / tokens — DB-ში <strong>encrypted</strong> (Laravel Crypt)</li>
                <li class="flex gap-2"><span class="text-brand-600">✓</span> Secrets არასოდეს გამოდის UI-ში — masked (sk-•••abcd)</li>
                <li class="flex gap-2"><span class="text-brand-600">✓</span> ვინახავთ მხოლოდ საჭიროს: PSID, message text, conversation state</li>
                <li class="flex gap-2"><span class="text-brand-600">✓</span> Webhook POST-ი HMAC-SHA256 signature-ით verified</li>
            </ul>
        </div>

        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-slate-200 text-slate-700 grid place-items-center">📋</span>
                Observability & audit
            </h3>
            <ul class="space-y-2 text-sm text-slate-700">
                <li class="flex gap-2"><span class="text-slate-600">✓</span> ყველა AI გადაწყვეტილება audit-logged (intent, query, products, source)</li>
                <li class="flex gap-2"><span class="text-slate-600">✓</span> auto-reply.log — scheduled / sent / skipped / failed</li>
                <li class="flex gap-2"><span class="text-slate-600">✓</span> "Why AI replied?" panel ყოველ conversation-ში</li>
                <li class="flex gap-2"><span class="text-slate-600">✓</span> Rate limit — max N AI reply / საათში / conversation (anti-spam)</li>
            </ul>
        </div>
    </div>

    <!-- Live status strip -->
    <div class="card mt-4 p-4" x-show="status" x-cloak>
        <h3 class="font-semibold text-slate-900 mb-3">ცოცხალი safety status</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div class="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                <span class="text-slate-600">Emergency Stop</span>
                <span :class="status.auto_reply_enabled ? 'badge bg-emerald-100 text-emerald-700' : 'badge bg-red-100 text-red-700'"
                      x-text="status.auto_reply_enabled ? 'AI ON' : 'STOPPED'"></span>
            </div>
            <div class="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                <span class="text-slate-600">Safety gates</span>
                <span class="badge bg-emerald-100 text-emerald-700">8 active</span>
            </div>
            <div class="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                <span class="text-slate-600">Sensitive-intent block</span>
                <span class="badge bg-emerald-100 text-emerald-700">on</span>
            </div>
            <div class="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                <span class="text-slate-600">WC-grounded only</span>
                <span class="badge bg-emerald-100 text-emerald-700">enforced</span>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function privacySafety() {
    return {
        status: null,
        async load() {
            try {
                const shell = Alpine.$data(document.body);
                const h = await shell.api('/health');
                const master = (h.checks || []).find(c => c.key === 'master_toggle');
                this.status = { auto_reply_enabled: master?.status === 'ok' };
            } catch (e) { this.status = { auto_reply_enabled: false }; }
        },
    };
}
</script>
@endpush
@endsection

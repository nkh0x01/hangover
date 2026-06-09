@extends('admin.layout')

@section('title', 'Beta Test Checklist')
@section('subtitle', 'Messenger scenarios — manual verification before customer launch')

@section('content')
<div x-data="testChecklistPage()" x-init="boot()">

    <div class="card mb-4 p-4 bg-amber-50 border-l-4 border-amber-400">
        <div class="font-semibold text-slate-900 mb-1">⚠ Manual checklist — Messenger only</div>
        <div class="text-xs text-slate-600">
            ეს გვერდი help-პერსონალს ეუბნება როგორ შევამოწმოთ თითო ფუნქცია სანამ customer-ებს გაუშვებთ.
            Real Meta DM-ის ნაცვლად შეგვიძლია გამოვიყენოთ შიდა <code>messenger:simulate-inbound</code> და artisan command-ი — ჯერ artisan-ით ცადო, შემდეგ real Meta.
        </div>
    </div>

    <div class="space-y-3">
        <template x-for="(scn, i) in scenarios" :key="scn.id">
            <div class="card p-4">
                <div class="flex items-start gap-3">
                    <input type="checkbox" :checked="isChecked(scn.id)" @change="toggle(scn.id)" class="w-5 h-5 mt-0.5 shrink-0">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs text-slate-400" x-text="(i+1).toString().padStart(2, '0')"></span>
                            <h4 class="font-semibold text-slate-900" x-text="scn.title"></h4>
                            <span :class="scn.priority === 'high' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600'" class="badge text-[10px]" x-text="scn.priority"></span>
                        </div>
                        <p class="text-sm text-slate-600 mt-1" x-text="scn.what"></p>
                        <div class="mt-2 text-xs">
                            <div class="text-slate-500"><strong>Test:</strong> <code class="bg-slate-100 px-1.5 py-0.5 rounded text-[11px]" x-text="scn.howSimulator"></code></div>
                            <div class="text-emerald-700 mt-1"><strong>Expected:</strong> <span x-text="scn.expected"></span></div>
                            <div class="text-slate-500 mt-1"><strong>Real Meta:</strong> <span x-text="scn.howReal"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="card mt-6 p-4 bg-slate-50">
        <div class="font-medium text-slate-900 mb-2">Beta readiness summary</div>
        <div class="text-sm text-slate-600">
            <span x-text="checkedCount()"></span> / <span x-text="scenarios.length"></span> სცენარი დადასტურებულია
            <template x-if="checkedCount() === scenarios.length">
                <span class="ml-2 badge bg-emerald-100 text-emerald-700">✓ READY FOR BETA</span>
            </template>
            <template x-if="checkedCount() < scenarios.length">
                <span class="ml-2 badge bg-amber-100 text-amber-700">⚠ Incomplete</span>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function testChecklistPage() {
    return {
        scenarios: [
            {
                id: 'greeting',
                title: 'Greeting / general intent',
                priority: 'high',
                what: 'Customer-ის "გამარჯობა" → ბოტი მეგობრულად პასუხობს. არცერთი ბრენდი ნახსენები, no upsell.',
                howSimulator: 'php artisan messenger:simulate-inbound --text="გამარჯობა" --sender=BETA001',
                expected: 'auto-reply.log "action=failed" (fake PSID), source=general, no product_ids',
                howReal: 'Send "გამარჯობა" from real FB account → 10s → bot replies warm Georgian.',
            },
            {
                id: 'product',
                title: 'Product request → WC grounded reply',
                priority: 'high',
                what: '"აიფონ 15-ის ქეისი მინდა" → ბოტი real WC პროდუქტს გვირჩევს + image + Georgian sales text.',
                howSimulator: 'php artisan messenger:simulate-inbound --text="აიფონ 15-ის ქეისი მინდა" --sender=BETA002',
                expected: 'auto-reply.log source=wc_grounded, product_ids non-empty, classification=permanent (fake PSID)',
                howReal: 'Real FB DM → 10s wait → ფოტო + ქართული პასუხი 30-დან 100 ლარამდე.',
            },
            {
                id: 'multi-msg',
                title: 'Multi-message debounce',
                priority: 'high',
                what: '3 quick messages within 2s → only LAST job runs (pending_reply_job_id rotation).',
                howSimulator: '3× php artisan messenger:simulate-inbound rapidly with same sender',
                expected: 'auto-reply.log shows 3 scheduled entries but only 1 sent/failed/skipped',
                howReal: 'Real FB rapid-fire 3 messages → only ONE bot reply.',
            },
            {
                id: 'no-product',
                title: 'No WC match → polite fallback + internal note',
                priority: 'high',
                what: 'Random/obscure query → WC returns 0 → ბოტი არ ცდილობს, internal note იქმნება.',
                howSimulator: 'php artisan messenger:simulate-inbound --text="ფიფყური ჟრიამული" --sender=BETA003',
                expected: 'auto-reply.log skipped reason=no_wc_products; new internal note on conversation',
                howReal: 'Real FB asks for non-existent product → conversation shows in inbox with a note "no match — manual review"',
            },
            {
                id: 'angry',
                title: 'Angry/complaint customer',
                priority: 'high',
                what: 'Complaint / refund / warranty / angry tone → ბოტი NOT auto-reply (sensitive intent gate).',
                howSimulator: 'php artisan messenger:simulate-inbound --text="უხეშად მომექცნენ, ვითხოვ ჩემს ფულს" --sender=BETA004',
                expected: 'auto-reply.log skipped reason=sensitive_intent:complaint',
                howReal: 'Real FB angry msg → bot silent; conv shows in inbox for human takeover.',
            },
            {
                id: 'warranty',
                title: 'Warranty/service question',
                priority: 'medium',
                what: 'Customer asks about service/warranty → bot escalates (sensitive_intent or general no-product).',
                howSimulator: 'php artisan messenger:simulate-inbound --text="გარანტიის ვადა" --sender=BETA005',
                expected: 'no auto-reply for warranty intent; conv visible in inbox',
                howReal: 'Real FB warranty question → bot silent, agent picks up.',
            },
            {
                id: 'takeover',
                title: 'Human takeover overrides AI',
                priority: 'high',
                what: 'Agent clicks Takeover → conv.ai_paused=1 → next inbound, bot stays silent.',
                howSimulator: 'curl POST /api/admin/inbox/{id}/takeover; then send another simulate-inbound',
                expected: 'auto-reply.log skipped reason=ai_paused_manual_takeover',
                howReal: 'Take any active conv, click Takeover → next customer message: bot stays silent.',
            },
            {
                id: 'release',
                title: 'Release back to AI',
                priority: 'high',
                what: 'Agent clicks Release on a paused conv → AI resumes on next inbound.',
                howSimulator: 'curl POST /api/admin/inbox/{id}/release; then simulate-inbound product',
                expected: 'auto-reply.log scheduled + sent/failed (no skip)',
                howReal: 'Release a paused conv → customer next msg → bot replies.',
            },
            {
                id: 'duplicate-prevention',
                title: 'Duplicate prevention',
                priority: 'high',
                what: 'Even with retries / requeues, the same Message body is never sent twice within 60s.',
                howSimulator: 'Trigger force-retry of GenerateAIReply; verify outbound count = 1',
                expected: 'Message.body MD5 dedup check fires; no duplicate row in messages table',
                howReal: 'Watch outbound for any conv — never 2 identical messages within 60s.',
            },
            {
                id: 'queue-delay',
                title: 'Queue delay + tick freshness',
                priority: 'medium',
                what: 'Cron tick runs every minute; jobs available_at respected. Pending count stays low.',
                howSimulator: 'tail tick.log; check jobs table after burst of simulate-inbound',
                expected: 'tick.log freshness <60s; jobs table drains within 1 cron cycle',
                howReal: 'Send 5 customer messages; all replies arrive within 60-90s of timer.',
            },
        ],

        boot() {
            this.checks = JSON.parse(localStorage.getItem('test_checklist_v1') || '{}');
        },
        checks: {},
        isChecked(id) { return !!this.checks[id]; },
        toggle(id) {
            this.checks[id] = !this.checks[id];
            localStorage.setItem('test_checklist_v1', JSON.stringify(this.checks));
        },
        checkedCount() {
            return Object.values(this.checks).filter(Boolean).length;
        },
    };
}
</script>
@endpush
@endsection

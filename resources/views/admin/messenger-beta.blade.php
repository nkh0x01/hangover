@extends('admin.layout')

@section('title', 'Messenger Beta')
@section('subtitle', 'Real-time beta metrics — Messenger only')

@section('content')
<div x-data="messengerBeta()" x-init="load()">

    <!-- Ready banner -->
    <div class="card mb-4 p-4 border-l-4 flex items-center gap-4"
         :class="data?.ready ? 'border-emerald-400 bg-emerald-50' : 'border-amber-400 bg-amber-50'">
        <div class="flex-1">
            <div class="font-semibold text-slate-900">
                <span x-show="data?.ready">✓ All gate checks pass — ready for live launch</span>
                <span x-show="data && !data.ready">⚠ Gate checks failing — see blockers below</span>
                <span x-show="!data">Loading…</span>
            </div>
            <div x-show="data && !data.ready" class="mt-1 space-y-0.5">
                <template x-for="b in (data?.ready_blockers || [])" :key="b">
                    <div class="text-xs text-amber-700">• <span x-text="b"></span></div>
                </template>
            </div>
            <div x-show="data" class="text-xs text-slate-500 mt-1">
                Rollout mode: <strong x-text="data?.rollout_mode"></strong>
                · Computed: <span x-text="data?.computed_at ? new Date(data.computed_at).toLocaleTimeString() : ''"></span>
            </div>
        </div>
        <button @click="load()" :disabled="loading" class="btn btn-secondary !py-1.5 text-xs">↻ Refresh</button>
    </div>

    <div x-show="loading" x-cloak class="card p-8 text-center text-sm text-slate-500">
        <div class="w-6 h-6 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
        მეტრიკები იტვირთება…
    </div>

    <!-- Dev mode warning -->
    <div x-show="!loading && data?.dev_mode_warning?.detected" x-cloak
         class="card mb-4 p-4 bg-amber-50 border border-amber-300">
        <div class="font-semibold text-amber-800 text-sm">🔒 Meta App — Development Mode detected</div>
        <div class="text-xs text-amber-700 mt-1" x-text="data?.dev_mode_warning?.hint"></div>
        <div class="text-xs text-amber-600 mt-1">Distinct PSIDs in webhook log: <strong x-text="data?.dev_mode_warning?.distinct_psids"></strong></div>
    </div>

    <div x-show="!loading && data" x-cloak class="space-y-6">

        <!-- Today's counters -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-slate-900" x-text="data.counters_today?.inbound ?? '—'"></div>
                <div class="text-xs text-slate-500 mt-1">Inbound today</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-emerald-600" x-text="data.counters_today?.outbound_ai ?? '—'"></div>
                <div class="text-xs text-slate-500 mt-1">AI auto-replied</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-amber-600" x-text="data.counters_today?.skipped_awaiting_human ?? '—'"></div>
                <div class="text-xs text-slate-500 mt-1">Skipped → human</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold" :class="(data.counters_today?.failed_sends ?? 0) > 0 ? 'text-red-600' : 'text-slate-400'" x-text="data.counters_today?.failed_sends ?? '—'"></div>
                <div class="text-xs text-slate-500 mt-1">Failed sends</div>
            </div>
        </div>

        <!-- Skip breakdown -->
        <div class="card p-4">
            <h3 class="font-semibold text-slate-900 mb-3 text-sm">Skip breakdown (today)</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-sm">
                <div>
                    <div class="font-bold text-slate-700" x-text="data.counters_today?.no_product ?? '—'"></div>
                    <div class="text-xs text-slate-500">No WC product</div>
                </div>
                <div>
                    <div class="font-bold text-slate-700" x-text="data.counters_today?.complaint_warranty ?? '—'"></div>
                    <div class="text-xs text-slate-500">Complaint/warranty</div>
                </div>
                <div>
                    <div class="font-bold text-slate-700" x-text="data.counters_today?.rollout_suppressed ?? '—'"></div>
                    <div class="text-xs text-slate-500">Rollout suppressed</div>
                </div>
                <div>
                    <div class="font-bold text-slate-700" x-text="data.counters_today?.duplicate_prevented ?? '—'"></div>
                    <div class="text-xs text-slate-500">Duplicate prevented</div>
                </div>
            </div>
        </div>

        <!-- Ready gate checks -->
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 font-semibold text-slate-900">Ready Gate Checks</div>
            <div class="divide-y divide-slate-100">
                <template x-for="c in (data.ready_checks || [])" :key="c.key">
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <span :class="c.status === 'ok' ? 'text-emerald-600' : 'text-red-600'" x-text="c.status === 'ok' ? '✓' : '✕'" class="w-4 text-center font-bold shrink-0"></span>
                        <span class="flex-1 text-sm text-slate-700" x-text="c.label"></span>
                        <span x-show="c.detail" class="text-xs text-slate-400" x-text="c.detail"></span>
                        <span :class="c.status === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'" class="badge text-[10px]" x-text="c.status"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Tester scenario checklist -->
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 font-semibold text-slate-900">Tester Scenarios (last 7 days)</div>
            <div class="divide-y divide-slate-100">
                <template x-for="s in (data.tester_checklist || [])" :key="s.key">
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <span :class="s.status === 'ok' ? 'text-emerald-600' : 'text-slate-300'" x-text="s.status === 'ok' ? '✓' : '○'" class="w-4 text-center font-bold shrink-0"></span>
                        <span class="flex-1 text-sm" :class="s.status === 'ok' ? 'text-slate-700' : 'text-slate-400'" x-text="s.label"></span>
                        <span x-show="s.hits > 0" class="text-xs text-slate-400" x-text="s.hits + 'x'"></span>
                        <span :class="s.status === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="badge text-[10px]" x-text="s.status"></span>
                    </div>
                </template>
            </div>
            <div class="px-5 py-2.5 bg-slate-50 text-xs text-slate-500">
                <span x-text="(data.tester_checklist || []).filter(s => s.status === 'ok').length"></span> /
                <span x-text="(data.tester_checklist || []).length"></span> scenarios recorded
            </div>
        </div>

        <!-- Recent auto-reply events -->
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 font-semibold text-slate-900">Recent Auto-Reply Events</div>
            <div x-show="(data.recent_auto_reply_events || []).length === 0" class="px-5 py-4 text-sm text-slate-400">No events yet.</div>
            <div class="overflow-x-auto" x-show="(data.recent_auto_reply_events || []).length > 0">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Time</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Action</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Conv</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Source</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Intent</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="e in (data.recent_auto_reply_events || [])" :key="e.id">
                            <tr>
                                <td class="px-4 py-2 text-slate-500 whitespace-nowrap" x-text="e.ts ? new Date(e.ts).toLocaleTimeString() : '—'"></td>
                                <td class="px-4 py-2">
                                    <span :class="e.action === 'auto_reply_sent' || e.action === 'reply.sent' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" class="badge text-[10px]" x-text="e.action === 'reply.sent' ? 'sent' : (e.action === 'auto_reply_sent' ? 'sent' : 'skipped')"></span>
                                </td>
                                <td class="px-4 py-2">
                                    <a :href="'/admin/inbox?conv=' + e.conv_id" class="text-brand-600 hover:underline" x-text="'#' + e.conv_id"></a>
                                </td>
                                <td class="px-4 py-2 text-slate-600" x-text="e.source || '—'"></td>
                                <td class="px-4 py-2 text-slate-600" x-text="e.intent || '—'"></td>
                                <td class="px-4 py-2 text-slate-400 max-w-[180px] truncate" x-text="e.reason || '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent no-product queries -->
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 font-semibold text-slate-900">Recent No-Product Queries</div>
            <div x-show="(data.recent_no_product_queries || []).length === 0" class="px-5 py-4 text-sm text-slate-400">No no-product queries yet.</div>
            <div class="divide-y divide-slate-100" x-show="(data.recent_no_product_queries || []).length > 0">
                <template x-for="(q, i) in (data.recent_no_product_queries || [])" :key="i">
                    <div class="px-5 py-3 text-xs">
                        <div class="flex items-center justify-between mb-1">
                            <a :href="'/admin/inbox?conv=' + q.conv_id" class="text-brand-600 hover:underline font-medium" x-text="'Conv #' + q.conv_id"></a>
                            <span class="text-slate-400" x-text="q.ts ? new Date(q.ts).toLocaleString() : '—'"></span>
                        </div>
                        <div class="text-slate-700">Customer: <em x-text="q.customer_message || '—'"></em></div>
                        <div class="text-slate-500 mt-0.5">AI query: <code class="bg-slate-100 px-1 rounded" x-text="q.query || '—'"></code></div>
                        <div x-show="(q.queries_tried || []).length > 1" class="text-slate-400 mt-0.5">
                            Also tried: <span x-text="(q.queries_tried || []).join(', ')"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
function messengerBeta() {
    return {
        loading: false,
        data: null,

        async load() {
            this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                this.data = await shell.api('/messenger-beta');
            } catch (e) {} finally { this.loading = false; }
        },
    };
}
</script>
@endpush
@endsection

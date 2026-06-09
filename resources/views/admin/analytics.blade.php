@extends('admin.layout')

@section('title', 'Analytics')
@section('subtitle', 'Auto-reply, takeovers, top products')

@section('content')
<div x-data="analyticsPage()" x-init="load()">

    <div class="flex items-center gap-3 mb-4">
        <select x-model="since" @change="load()" class="px-3 py-1.5 rounded-md border border-slate-200 text-sm">
            <option value="24h">Last 24h</option>
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
        </select>
        <button @click="load()" :disabled="loading" class="btn btn-secondary !py-1.5 text-xs">↻ Refresh</button>
        <span class="text-xs text-slate-400" x-text="data?.computed_at"></span>
    </div>

    <div x-show="loading" x-cloak class="card p-10 text-center text-sm text-slate-500">
        <div class="w-6 h-6 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
        ვტვირთავთ…
    </div>

    <div x-show="!loading && data" x-cloak>
        <!-- KPI cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="card p-4">
                <div class="text-xs text-slate-500 uppercase">Auto replies (period)</div>
                <div class="text-2xl font-bold text-slate-900" x-text="data.counters.auto_reply_sent_period"></div>
                <div class="text-xs text-emerald-600" x-text="'last 24h: ' + data.counters.auto_reply_sent_24h"></div>
            </div>
            <div class="card p-4">
                <div class="text-xs text-slate-500 uppercase">Skipped</div>
                <div class="text-2xl font-bold text-slate-900" x-text="data.counters.auto_reply_skipped_period"></div>
                <div class="text-xs text-amber-600">awaiting human</div>
            </div>
            <div class="card p-4">
                <div class="text-xs text-slate-500 uppercase">Takeovers</div>
                <div class="text-2xl font-bold text-slate-900" x-text="data.counters.takeovers_period"></div>
                <div class="text-xs text-slate-500">manual escalations</div>
            </div>
            <div class="card p-4">
                <div class="text-xs text-slate-500 uppercase">Manual replies</div>
                <div class="text-2xl font-bold text-slate-900" x-text="data.counters.manual_replies_period"></div>
                <div class="text-xs text-brand-600">by employees</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Inbound vs outbound</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Inbound</span><strong x-text="data.counters.inbound_period"></strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Outbound AI</span><strong x-text="data.counters.outbound_ai_period"></strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Outbound human</span><strong x-text="data.counters.outbound_human_period"></strong></div>
                </div>
            </div>
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Conversations by platform</h3>
                <div class="space-y-1 text-sm">
                    <template x-for="(c, p) in data.conversations_by_platform" :key="p">
                        <div class="flex justify-between">
                            <span class="capitalize text-slate-700" x-text="p"></span>
                            <strong x-text="c"></strong>
                        </div>
                    </template>
                    <div x-show="Object.keys(data.conversations_by_platform || {}).length === 0" x-cloak class="text-xs text-slate-400">no data</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Top skip reasons</h3>
                <div class="space-y-1.5 text-sm">
                    <template x-for="(c, r) in data.top_skip_reasons" :key="r">
                        <div class="flex justify-between">
                            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded" x-text="r"></code>
                            <strong x-text="c"></strong>
                        </div>
                    </template>
                    <div x-show="Object.keys(data.top_skip_reasons || {}).length === 0" x-cloak class="text-xs text-slate-400">no skips</div>
                </div>
            </div>
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Lead funnel</h3>
                <div class="space-y-1.5 text-sm">
                    <template x-for="(c, status) in data.lead_funnel" :key="status">
                        <div class="flex justify-between">
                            <span class="text-slate-600" x-text="status"></span>
                            <strong x-text="c"></strong>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3">Top recommended products (period)</h3>
            <div x-show="data.top_products && data.top_products.length === 0" x-cloak class="text-xs text-slate-400">no recommendations recorded yet</div>
            <div class="space-y-1.5 text-sm">
                <template x-for="p in data.top_products" :key="p.product_id">
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-b-0">
                        <div class="flex-1 min-w-0">
                            <span class="text-slate-900" x-text="p.name"></span>
                            <code class="text-[10px] text-slate-400 ml-2" x-text="'id=' + p.product_id"></code>
                        </div>
                        <span class="text-xs bg-brand-50 text-brand-700 px-1.5 py-0.5 rounded font-medium" x-text="p.count + '×'"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function analyticsPage() {
    return {
        loading: false,
        since: '7d',
        data: null,
        async load() {
            this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                this.data = await shell.api('/analytics?since=' + this.since);
            } catch (e) {} finally { this.loading = false; }
        },
    };
}
</script>
@endpush
@endsection

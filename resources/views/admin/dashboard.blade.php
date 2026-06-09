@extends('admin.layout')

@section('title', 'Dashboard')
@section('subtitle', 'საერთო მდგომარეობა და მთავარი მაჩვენებლები')

@section('content')
<div x-data="dashboardPage()" x-init="load()">
    <!-- Emergency Stop banner -->
    <div class="card mb-4 p-4 border-l-4 flex items-center gap-4"
         :class="autoReplyEnabled ? 'border-emerald-400 bg-emerald-50' : 'border-red-400 bg-red-50'">
        <div class="shrink-0">
            <template x-if="autoReplyEnabled">
                <div class="w-10 h-10 rounded-full bg-emerald-100 grid place-items-center">
                    <svg class="w-5 h-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.091 3.091z"/></svg>
                </div>
            </template>
            <template x-if="!autoReplyEnabled">
                <div class="w-10 h-10 rounded-full bg-red-100 grid place-items-center">
                    <svg class="w-5 h-5 text-red-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
            </template>
        </div>
        <div class="flex-1">
            <div class="font-semibold text-slate-900">
                <span x-show="autoReplyEnabled">AI Auto-reply: <span class="text-emerald-700">ON</span></span>
                <span x-show="!autoReplyEnabled">AI Auto-reply: <span class="text-red-700">EMERGENCY STOP</span></span>
            </div>
            <div class="text-xs text-slate-600">
                <span x-show="autoReplyEnabled">ბოტი ავტომატურად პასუხობს Messenger DM-ებზე (configured channels). WhatsApp/Instagram untouched.</span>
                <span x-show="!autoReplyEnabled">ბოტი არცერთ message-ს ავტო-პასუხს არ უგზავნის. Manual reply მუშაობს.</span>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button @click="toggleSafeMode()" :disabled="togglingSafeMode"
                    :class="safeMode ? 'btn-primary' : 'btn-secondary'"
                    class="btn" title="Safe Mode: disable auto-reply but keep inbox + manual reply">
                <span x-show="!togglingSafeMode" x-text="safeMode ? '🟢 Safe Mode-დან გამოსვლა' : '🟠 Safe Mode'"></span>
                <span x-show="togglingSafeMode" x-cloak>…</span>
            </button>
            <button @click="toggleAutoReply()" :disabled="togglingAutoReply || safeMode"
                    :class="autoReplyEnabled ? 'btn-danger' : 'btn-primary'"
                    class="btn" :title="safeMode ? 'Safe Mode active — exit Safe Mode first' : ''">
                <span x-show="!togglingAutoReply" x-text="autoReplyEnabled ? '⛔ Emergency Stop' : '▶ Auto-reply ჩართე'"></span>
                <span x-show="togglingAutoReply" x-cloak>ვცვლი…</span>
            </button>
        </div>
    </div>
    <div x-show="safeMode" x-cloak class="card mb-4 p-3 bg-amber-50 border border-amber-200 text-sm text-amber-900">
        🟠 <strong>Safe Mode ON</strong> — ბოტი ავტო-პასუხს არ უგზავნის, მაგრამ inbox იღებს მესიჯებს და manual reply მუშაობს. AUTO_REPLY_ENABLED უცვლელია — Safe Mode-დან გამოსვლისას ძველი მდგომარეობა აღდგება.
    </div>

    <!-- Rollout mode banner -->
    <div x-show="rolloutMode" x-cloak class="card mb-4 p-3 flex items-center gap-3 text-sm"
         :class="rolloutMode === 'public_product_only' ? 'bg-brand-50 border border-brand-200 text-brand-900'
                : (rolloutMode === 'public_receive_only' ? 'bg-slate-100 border border-slate-200 text-slate-700'
                : 'bg-indigo-50 border border-indigo-200 text-indigo-900')">
        <span class="text-lg" x-text="rolloutMode === 'public_product_only' ? '🟢' : (rolloutMode === 'public_receive_only' ? '📥' : '🧪')"></span>
        <div class="flex-1">
            <span x-show="rolloutMode === 'public_product_only'"><strong>Public product-only mode is active</strong> — ბოტი ავტო-პასუხობს მხოლოდ ცხად, WC-grounded პროდუქტის კითხვებს. ყველა სხვა (მისალმება, ჩივილი, გაურკვეველი, პროდუქტი ვერ მოიძებნა) → ადამიანს გადაეცემა pinned note-ით.</span>
            <span x-show="rolloutMode === 'public_receive_only'"><strong>Public receive-only mode</strong> — ბოტი მესიჯებს იღებს, მაგრამ ავტო-პასუხს არ უგზავნის. ყველაფერი ადამიანს გადაეცემა.</span>
            <span x-show="rolloutMode === 'beta'"><strong>Beta mode</strong> — auto-reply product + general (internal testing). Public rollout-ისთვის გადაერთე public_product_only-ზე.</span>
        </div>
    </div>
    <!-- Top stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <template x-for="card in cards" :key="card.label">
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider" x-text="card.label"></span>
                    <span :class="card.tone === 'good' ? 'bg-emerald-50 text-emerald-700' : (card.tone === 'warn' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600')"
                          class="badge" x-text="card.delta || ''"></span>
                </div>
                <div class="text-3xl font-bold text-slate-900" x-text="card.value"></div>
                <div class="text-xs text-slate-500 mt-1" x-text="card.sub || ''"></div>
            </div>
        </template>
    </div>

    <!-- Quick actions + recent activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-900">ბოლო Conversations</h3>
                <a href="/admin/inbox" class="text-sm text-brand-600 hover:underline">ყველა →</a>
            </div>
            <div x-show="loading" x-cloak class="text-center py-10 text-sm text-slate-500">
                <div class="w-6 h-6 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
                იტვირთება…
            </div>
            <div x-show="!loading && conversations.length === 0" x-cloak class="text-center py-10 text-sm text-slate-500">
                ჯერ კონვერსაცია არ არის.
            </div>
            <div x-show="!loading && conversations.length > 0" x-cloak class="divide-y divide-slate-100">
                <template x-for="c in conversations" :key="c.id">
                    <a :href="'/admin/inbox#' + c.id" class="flex items-center gap-3 py-3 hover:bg-slate-50 -mx-2 px-2 rounded">
                        <span :class="platformColor(c.platform)" class="w-2 h-2 rounded-full shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-900 truncate" x-text="c.customer?.name || c.customer?.handle || c.thread_id || '—'"></div>
                            <div class="text-xs text-slate-500 truncate" x-text="c.last_message?.body || c.platform"></div>
                        </div>
                        <span class="badge bg-slate-100 text-slate-600" x-text="c.lead_status || 'new'"></span>
                    </a>
                </template>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">სწრაფი ნაბიჯები</h3>
                <div class="space-y-2 text-sm">
                    <a href="/admin/setup-checklist" class="flex items-center justify-between text-slate-700 hover:text-brand-700 group">
                        <span>Setup checklist</span>
                        <span class="text-slate-400 group-hover:translate-x-0.5 transition">→</span>
                    </a>
                    <a href="/admin/integrations" class="flex items-center justify-between text-slate-700 hover:text-brand-700 group">
                        <span>Integrations</span>
                        <span class="text-slate-400 group-hover:translate-x-0.5 transition">→</span>
                    </a>
                    <a href="/admin/ai-settings" class="flex items-center justify-between text-slate-700 hover:text-brand-700 group">
                        <span>AI prompt</span>
                        <span class="text-slate-400 group-hover:translate-x-0.5 transition">→</span>
                    </a>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-slate-900">Bot Health</h3>
                    <a href="/admin/health" class="text-xs text-brand-600 hover:underline">details →</a>
                </div>
                <div x-show="!health" x-cloak class="text-xs text-slate-400">loading…</div>
                <div x-show="health" x-cloak class="space-y-1.5 text-sm">
                    <template x-for="c in (health?.checks || [])" :key="c.key">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600" x-text="c.title"></span>
                            <span :class="{
                              'bg-emerald-50 text-emerald-700': c.status === 'ok',
                              'bg-amber-50 text-amber-700': c.status === 'warn',
                              'bg-red-50 text-red-700': c.status === 'fail',
                              'bg-slate-100 text-slate-500': c.status === 'pending',
                            }" class="badge truncate max-w-[180px]" :title="c.message" x-text="c.message"></span>
                        </div>
                    </template>
                    <button @click="loadHealth()" class="text-xs text-brand-600 hover:underline mt-2">↻ refresh</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function dashboardPage() {
    return {
        loading: true,
        cards: [
            { label: 'Open conversations', value: '—', sub: 'inbox', tone: 'neutral' },
            { label: 'Escalations', value: '—', sub: 'requires attention', tone: 'neutral' },
            { label: 'New orders', value: '—', sub: 'today', tone: 'neutral' },
            { label: 'AI replies', value: '—', sub: '24h', tone: 'neutral' },
        ],
        conversations: [],
        autoReplyEnabled: false,
        togglingAutoReply: false,
        safeMode: false,
        togglingSafeMode: false,
        rolloutMode: '',
        health: null,

        async toggleSafeMode() {
            const shell = Alpine.$data(document.body);
            if (! this.safeMode && ! window.confirm('Safe Mode — ბოტი ავტო-პასუხს შეაჩერებს (inbox + manual reply მუშაობს). დადასტურდი.')) return;
            this.togglingSafeMode = true;
            try {
                const j = await shell.api('/health/safe-mode', { method: 'POST', body: { enabled: ! this.safeMode } });
                this.safeMode = j.safe_mode;
                shell.toast(j.safe_mode ? '🟠 Safe Mode ჩართულია' : '🟢 Safe Mode გამოირთო', j.safe_mode ? 'warn' : 'success');
                this.loadHealth();
            } catch (e) {
                shell.toast('ჩავარდა: ' + (e?.message || 'unknown'), 'error');
            } finally { this.togglingSafeMode = false; }
        },

        async toggleAutoReply() {
            const shell = Alpine.$data(document.body);
            if (this.autoReplyEnabled && ! window.confirm('Emergency Stop — ბოტი არცერთ AI რეპლეი არ გაუგზავნის ვინმეს. დადასტურდი.')) return;
            this.togglingAutoReply = true;
            try {
                const j = await shell.api('/health/master-toggle', { method: 'POST', body: { enabled: ! this.autoReplyEnabled } });
                this.autoReplyEnabled = j.enabled;
                shell.toast(j.enabled ? '✓ Auto-reply ჩართულია' : '⛔ Auto-reply გათიშულია', j.enabled ? 'success' : 'warn');
                this.loadHealth();
            } catch (e) {
                shell.toast('ჩავარდა: ' + (e?.message || 'unknown'), 'error');
            } finally { this.togglingAutoReply = false; }
        },

        async loadHealth() {
            try {
                const shell = Alpine.$data(document.body);
                const j = await shell.api('/health');
                this.health = j;
                const master = (j.checks || []).find(c => c.key === 'master_toggle');
                this.autoReplyEnabled = master?.status === 'ok';
                const safe = (j.checks || []).find(c => c.key === 'safe_mode');
                this.safeMode = safe?.status === 'warn'; // warn = Safe Mode ON
                const rollout = (j.checks || []).find(c => c.key === 'rollout_mode');
                if (rollout) {
                    const m = rollout.message || '';
                    this.rolloutMode = m.includes('PRODUCT-ONLY') ? 'public_product_only'
                        : (m.includes('RECEIVE-ONLY') ? 'public_receive_only' : 'beta');
                }
            } catch (e) {}
        },

        async load() {
            this.loadHealth();
            try {
                const shell = Alpine.$data(document.body);
                const dash = await shell.api('/dashboard').catch(() => null);
                if (dash) {
                    this.cards = [
                        { label: 'Open conversations', value: dash.open_conversations ?? 0, sub: 'inbox', tone: 'neutral' },
                        { label: 'Escalations', value: dash.open_escalations ?? 0, sub: 'requires attention', tone: (dash.open_escalations ?? 0) > 0 ? 'warn' : 'good' },
                        { label: 'New orders', value: dash.orders_today ?? 0, sub: 'today', tone: 'neutral' },
                        { label: 'AI replies', value: dash.ai_replies_24h ?? 0, sub: '24h', tone: 'good' },
                    ];
                }
                const inbox = await shell.api('/inbox').catch(() => null);
                // Backend returns {data: [...]} (Laravel resource format)
                const list = inbox?.data ?? inbox?.conversations ?? [];
                this.conversations = list.slice(0, 6);
            } finally { this.loading = false; }
        },
        platformColor(p) {
            return { whatsapp:'bg-emerald-500', messenger:'bg-blue-500', instagram:'bg-pink-500', facebook:'bg-blue-600' }[p] || 'bg-slate-400';
        },
    };
}
</script>
@endpush
@endsection

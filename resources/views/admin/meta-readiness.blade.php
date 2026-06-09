@extends('admin.layout')

@section('title', 'Meta Readiness')
@section('subtitle', 'Messenger App Review preparation — read-only diagnostics')

@section('content')
<div x-data="metaReadiness()" x-init="load()">

    <!-- Readiness banner -->
    <div class="card mb-4 p-4 border-l-4 flex items-center gap-4"
         :class="data?.ready_for_review ? 'border-emerald-400 bg-emerald-50' : 'border-amber-400 bg-amber-50'">
        <div class="flex-1">
            <div class="font-semibold text-slate-900">
                <span x-show="data?.ready_for_review">✓ Core requirements met — ready to prepare App Review submission</span>
                <span x-show="data && !data.ready_for_review">⚠ Missing core requirements — see checklist below</span>
                <span x-show="!data">Loading…</span>
            </div>
            <div class="text-xs text-slate-600 mt-1">Webhook: <code>https://bot.gadget.ge/webhooks/messenger</code></div>
        </div>
        <button @click="load()" :disabled="loading" class="btn btn-secondary !py-1.5 text-xs">↻ Re-probe</button>
    </div>

    <div x-show="loading" x-cloak class="card p-8 text-center text-sm text-slate-500">
        <div class="w-6 h-6 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
        Graph API-ს ვამოწმებთ (read-only)…
    </div>

    <div x-show="!loading && data" x-cloak class="space-y-6">

        <!-- App mode note -->
        <div class="card p-4 bg-slate-50 text-xs text-slate-600">
            <strong>App mode:</strong> <span x-text="data.app_mode_note"></span>
        </div>

        <!-- Checklist -->
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 font-semibold text-slate-900">App Review Checklist</div>
            <div class="divide-y divide-slate-100">
                <template x-for="c in data.checklist" :key="c.key">
                    <div class="flex items-start gap-3 px-5 py-3">
                        <span :class="iconBg(c.status)" class="w-6 h-6 rounded-full grid place-items-center shrink-0 text-xs font-bold"
                              x-text="icon(c.status)"></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-900" x-text="c.title"></div>
                            <div class="text-xs text-slate-500" x-text="c.message"></div>
                        </div>
                        <span :class="badge(c.status)" class="badge text-[10px]" x-text="c.status"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Permissions detail -->
        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3">Permission probes (live Graph GET)</h3>
            <div class="space-y-2 text-sm">
                <template x-for="(p, name) in data.permissions" :key="name">
                    <div class="flex items-center justify-between gap-3">
                        <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded" x-text="name"></code>
                        <span class="flex-1 text-xs text-slate-500 truncate" x-text="p.message"></span>
                        <span :class="badge(p.status)" class="badge text-[10px]" x-text="p.status"></span>
                    </div>
                </template>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Webhook subscription</span>
                    <span :class="badge(data.subscription.status)" class="badge text-[10px]" x-text="data.subscription.status"></span>
                </div>
                <div class="text-xs text-slate-500 mt-1" x-text="data.subscription.message"></div>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 grid sm:grid-cols-2 gap-2 text-xs">
                <div>Last inbound POST: <span class="text-slate-900" x-text="data.last_inbound_post ? (data.last_inbound_post.ts + ' · PSID ' + data.last_inbound_post.psid) : '—'"></span></div>
                <div>Last outbound send: <span class="text-slate-900" x-text="data.last_outbound_send ? (data.last_outbound_send.ts + ' · ' + data.last_outbound_send.kind) : '—'"></span></div>
            </div>
        </div>

        <!-- Tester onboarding -->
        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3">👥 Tester onboarding (Development mode)</h3>
            <ol class="list-decimal list-inside space-y-2 text-sm text-slate-700">
                <li><a href="https://developers.facebook.com/apps/" target="_blank" class="text-brand-600 hover:underline">developers.facebook.com/apps</a> → შენი App</li>
                <li><strong>App Roles → Roles</strong> → <strong>Add People</strong></li>
                <li>აირჩიე როლი: <code>Tester</code> (ან Developer/Admin)</li>
                <li>შეიყვანე testerის Facebook account (email ან name)</li>
                <li>Tester იღებს notification-ს — <strong>developers.facebook.com/requests</strong>-ზე უნდა <strong>Accept</strong> გააკეთოს</li>
                <li>Accept-ის შემდეგ, tester-მა გვერდს Messenger-ში მისწეროს (მაგ. "გამარჯობა", "აიფონ 15-ის ქეისი მინდა")</li>
                <li>~10 წამში შემოვა <code>/admin/inbox</code>-ში + ბოტი ავტო-პასუხდება (თუ Auto-reply ON)</li>
            </ol>
            <div class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                Development mode-ში მხოლოდ App Roles users-ის DM მუშაობს. Live mode-ში გადასვლა App Review-ს მოითხოვს pages_messaging permission-ისთვის.
            </div>
        </div>

        <!-- App Review notes generator -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-900">📝 App Review submission notes</h3>
                <button @click="copyAll()" class="btn btn-secondary !py-1 text-xs">Copy all</button>
            </div>
            <p class="text-xs text-slate-500 mb-3">გადააკოპირე ეს ტექსტი Meta App Review-ის submission ფორმაში. ნუ submit-ავ ავტომატურად — შენ თვითონ გადახედე.</p>
            <pre class="bg-slate-900 text-slate-100 text-xs p-4 rounded-lg overflow-x-auto whitespace-pre-wrap" x-ref="reviewNotes" x-text="reviewNotes"></pre>
        </div>

        <!-- Meta Live Go-Live checklist -->
        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3">🚀 Meta Live (Go-Live) checklist</h3>
            <p class="text-xs text-slate-500 mb-3">Live mode-ში გადასვლამდე დაადასტურე. ზოგი ცოცხლად მოწმდება, ზოგი ხელით.</p>
            <div class="space-y-2">
                <!-- live-derived rows -->
                <div class="flex items-center gap-2 text-sm">
                    <span :class="liveCheck('webhook') ? 'text-emerald-600' : 'text-slate-300'" x-text="liveCheck('webhook') ? '✓' : '○'"></span>
                    <span class="text-slate-700">Webhook working (verify + signature)</span>
                    <span class="text-[10px] text-slate-400 ml-auto">auto</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span :class="liveCheck('messaging') ? 'text-emerald-600' : 'text-slate-300'" x-text="liveCheck('messaging') ? '✓' : '○'"></span>
                    <span class="text-slate-700">pages_messaging granted</span>
                    <span class="text-[10px] text-slate-400 ml-auto">auto</span>
                </div>
                <!-- manual rows -->
                <template x-for="item in liveChecklist" :key="item.key">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" :checked="liveChecked[item.key]" @change="toggleLive(item.key)"
                               class="rounded border-slate-300 text-brand-600">
                        <span :class="liveChecked[item.key] ? 'line-through text-slate-400' : 'text-slate-700'" x-text="item.label"></span>
                        <span class="text-[10px] text-slate-400 ml-auto">manual</span>
                    </label>
                </template>
            </div>
        </div>

        <!-- Beta readiness checklist (localStorage) -->
        <div class="card p-5">
            <h3 class="font-semibold text-slate-900 mb-3">✅ Beta readiness checklist (manual)</h3>
            <div class="space-y-2">
                <template x-for="item in betaChecklist" :key="item.key">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" :checked="checked[item.key]" @change="toggle(item.key)"
                               class="rounded border-slate-300 text-brand-600">
                        <span :class="checked[item.key] ? 'line-through text-slate-400' : 'text-slate-700'" x-text="item.label"></span>
                    </label>
                </template>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 text-sm">
                <span x-text="Object.values(checked).filter(Boolean).length"></span> / <span x-text="betaChecklist.length"></span> done
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function metaReadiness() {
    return {
        loading: false,
        data: null,
        checked: JSON.parse(localStorage.getItem('beta_checklist') || '{}'),
        liveChecked: JSON.parse(localStorage.getItem('live_checklist') || '{}'),
        liveChecklist: [
            { key: 'app_live', label: 'App mode switched to Live in Meta dashboard' },
            { key: 'testers_done', label: 'Beta testers completed all scenarios' },
            { key: 'perms_requested', label: 'App Review submitted for pages_messaging (+ metadata/read_engagement/show_list)' },
            { key: 'estop_tested', label: 'Emergency Stop + Safe Mode tested' },
            { key: 'rollout_product_only', label: 'Rollout mode = public_product_only confirmed' },
        ],
        liveCheck(which) {
            if (!this.data) return false;
            if (which === 'webhook') {
                const verify = (this.data.checklist || []).find(c => c.key === 'webhook_verify');
                const sig = (this.data.checklist || []).find(c => c.key === 'app_secret');
                return verify?.status === 'ok' && sig?.status === 'ok';
            }
            if (which === 'messaging') {
                return this.data.permissions?.pages_messaging?.status === 'ok';
            }
            return false;
        },
        toggleLive(key) {
            this.liveChecked[key] = !this.liveChecked[key];
            localStorage.setItem('live_checklist', JSON.stringify(this.liveChecked));
        },
        betaChecklist: [
            { key: 'testers_3', label: '3 tester accounts added & tested' },
            { key: 'product_20', label: '20 product messages tested (real WC products returned)' },
            { key: 'noproduct_5', label: '5 no-product messages tested (no invented products)' },
            { key: 'complaint_5', label: '5 complaint/warranty messages tested (escalated to human)' },
            { key: 'no_dup', label: 'No duplicate replies observed' },
            { key: 'no_hallucination', label: 'No hallucinated products observed' },
            { key: 'estop', label: 'Emergency stop tested (replies halt instantly)' },
            { key: 'takeover', label: 'Human takeover tested (AI silences on takeover)' },
        ],
        get reviewNotes() {
            const url = this.data?.webhook_url || 'https://bot.gadget.ge/webhooks/messenger';
            const pageId = this.data?.page_id || '<PAGE_ID>';
            return `=== Gadget AI — Messenger Bot · App Review Notes ===

WHAT THE APP DOES
Gadget AI is an omnichannel sales assistant for gadget.ge (electronics retail, Georgia).
On Facebook Messenger it answers customer product questions in Georgian, recommends
products that exist in the gadget.ge WooCommerce catalog, and hands off to a human
agent for complaints, warranty, refunds, or anything the bot is unsure about.

WHY EACH PERMISSION IS NEEDED
• pages_messaging — receive customer DMs to the Page and send replies (core function).
• pages_manage_metadata — subscribe the Page to the messages webhook so inbound DMs reach our server.
• pages_read_engagement — read the Page's own name/metadata to confirm the connected Page during setup.
• pages_show_list — let the admin pick which Page to connect during onboarding.

WEBHOOK
Callback URL: ${url}
Verify token: configured (GET handshake returns hub.challenge).
Signature: every POST is HMAC-SHA256 verified with the App Secret (X-Hub-Signature-256).
Subscribed field: messages.

TEST INSTRUCTIONS FOR REVIEWER
1. Message the connected Page (id ${pageId}) on Messenger: "Hello".
   → Bot replies with a Georgian greeting within ~10 seconds.
2. Send: "I want an iPhone 15 case" (or "აიფონ 15-ის ქეისი მინდა").
   → Bot replies with a real product from the WooCommerce catalog (name, price, stock) + product photo.
3. Send: "I want a refund" / "warranty problem".
   → Bot does NOT auto-reply; conversation is escalated to a human agent.

SAFETY & PRIVACY
• The bot only recommends products returned by the WooCommerce REST API — it never invents products, prices, or stock.
• Complaints / warranty / refund / legal / angry messages are never auto-answered — a human handles them.
• Admins have an Emergency Stop that halts all AI replies instantly.
• All bot actions are audit-logged. No secrets are exposed to end users.
• We store only the data needed to run the conversation (PSID, message text, conversation state).

CONTACT
Page owner / admin available for review questions.`;
        },
        async load() {
            this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                this.data = await shell.api('/meta-readiness');
            } catch (e) {} finally { this.loading = false; }
        },
        toggle(key) {
            this.checked[key] = !this.checked[key];
            localStorage.setItem('beta_checklist', JSON.stringify(this.checked));
        },
        copyAll() {
            navigator.clipboard.writeText(this.reviewNotes);
            const shell = Alpine.$data(document.body);
            shell.toast('App Review notes copied', 'success');
        },
        icon(s) { return ({ok:'✓',warn:'!',fail:'✕',pending:'·'})[s] || '?'; },
        iconBg(s) { return ({ok:'bg-emerald-100 text-emerald-700',warn:'bg-amber-100 text-amber-700',fail:'bg-red-100 text-red-700',pending:'bg-slate-100 text-slate-500'})[s] || 'bg-slate-100'; },
        badge(s) { return ({ok:'bg-emerald-100 text-emerald-700',warn:'bg-amber-100 text-amber-700',fail:'bg-red-100 text-red-700',pending:'bg-slate-100 text-slate-500'})[s] || 'bg-slate-100 text-slate-500'; },
    };
}
</script>
@endpush
@endsection

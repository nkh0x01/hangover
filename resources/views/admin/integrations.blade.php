@extends('admin.layout')

@section('title', 'Integrations')
@section('subtitle', 'შეუერთე WhatsApp, Messenger, Instagram, WooCommerce, Payment და AI')

@section('content')
<div x-data="integrationsPage()" x-init="load()">

    <!-- Tab navigation -->
    <div class="card mb-6 overflow-hidden">
        <div class="flex overflow-x-auto border-b border-slate-200 bg-slate-50/60">
            <template x-for="t in tabs" :key="t.key">
                <button @click="active = t.key"
                        :class="active === t.key ? 'border-brand-600 text-brand-700 bg-white' : 'border-transparent text-slate-600 hover:bg-white'"
                        class="px-5 py-3 text-sm font-medium border-b-2 whitespace-nowrap flex items-center gap-2">
                    <span x-text="t.label"></span>
                    <span :class="statusBadge(t.key)" class="badge text-[10px]" x-text="statusLabel(t.key)"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" x-cloak class="card p-10 text-center text-sm text-slate-500">
        <div class="w-6 h-6 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
        იტვირთება…
    </div>

    <div x-show="!loading" x-cloak>

        <!-- 0) Auto Reply (master safety toggle) -->
        <section x-show="active === 'auto_reply'" x-cloak>
            <div class="card mb-4 p-5 border-l-4 border-amber-400">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Auto Reply · 🤖 ავტომატური პასუხები
                </h3>
                <p class="text-sm text-slate-600 mt-2">
                    ბოტი ავტომატურად პასუხობს კლიენტებს მხოლოდ მაშინ, როცა <strong>AUTO_REPLY_ENABLED = true</strong>
                    და კონკრეტული channel-ის toggle ჩართულია.
                    Default — <strong class="text-red-700">ყველაფერი გათიშულია</strong> სანამ ხელით არ ჩართე.
                </p>
                <div class="mt-3 bg-slate-50 rounded-lg p-3 text-xs text-slate-700">
                    <div class="font-semibold mb-2">წესები (boolean: <code>true</code> / <code>false</code>):</div>
                    <ul class="space-y-1 list-disc pl-4">
                        <li><code>AUTO_REPLY_ENABLED</code> — მთავარი toggle</li>
                        <li><code>AUTO_REPLY_MESSENGER_ENABLED</code> — Messenger ცალკე</li>
                        <li><code>AUTO_REPLY_WHATSAPP_ENABLED</code> — WhatsApp (ჯერ არ ვცადო)</li>
                        <li><code>AUTO_REPLY_INSTAGRAM_ENABLED</code> — Instagram (ჯერ არ ვცადო)</li>
                        <li><code>AUTO_REPLY_DELAY_SECONDS</code> — დაყოვნება (default 10)</li>
                        <li><code>AUTO_REPLY_MAX_PER_HOUR</code> — max AI რეპლეი თითო conv-ში (default 10)</li>
                        <li><code>AUTO_REPLY_BUSINESS_HOURS_ONLY</code> — true თუ მხოლოდ სამუშაო საათებში</li>
                        <li><code>AUTO_REPLY_BUSINESS_HOURS_START</code> — საათი 0-23 (default 10)</li>
                        <li><code>AUTO_REPLY_BUSINESS_HOURS_END</code> — საათი 0-23 (default 22)</li>
                    </ul>
                </div>
                <div class="mt-3 bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-900">
                    <strong>⚠ რა ვერ მუშაობს auto-reply-ის გავლის გარეშე:</strong>
                    AI confidence დაბალია · ხელით takeover (ai_paused) · ესკალაცია · employee-ს assigned · spam customer · rate limit · sensitive intent (complaint/order_status) · WC-ში პროდუქტი ვერ მოიძებნა.
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'auto_reply'])
        </section>

        <!-- A) WhatsApp -->
        <section x-show="active === 'whatsapp'" x-cloak>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    WhatsApp Business
                </h3>
                <p class="text-sm text-slate-600 mt-2">WhatsApp Cloud API-ით ვიღებთ შემოსულ მესიჯებს და ვაგზავნით პასუხებს.</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Webhook URL</div>
                        <div class="font-mono text-slate-900 break-all">https://bot.gadget.ge/webhooks/whatsapp</div>
                        <button @click="copy('https://bot.gadget.ge/webhooks/whatsapp')" class="text-brand-600 hover:underline mt-1">კოპირება</button>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Help</div>
                        <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" class="text-brand-600 hover:underline">Meta Business → WhatsApp → API Setup</a><br>
                        Permissions: <code>whatsapp_business_messaging</code>, <code>whatsapp_business_management</code>
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'whatsapp'])
        </section>

        <!-- B) Messenger -->
        <section x-show="active === 'messenger'" x-cloak>
            <div class="card mb-4 p-4 border-l-4 border-amber-400 bg-amber-50">
                <div class="flex items-start gap-3 text-sm">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <div class="text-amber-900">
                        <strong>Meta App Development Mode notice.</strong>
                        Messenger webhook events მხოლოდ App Roles-ში დამატებული მომხმარებლებისთვის მუშაობს, სანამ App live mode-ში არ გადადის.
                        Diagnostic: <code class="bg-amber-100 px-1.5 py-0.5 rounded text-[11px]">php artisan messenger:recent-webhooks</code> — გაგვაჩვენებს ნახული PSID-ების სიას.
                        <a href="https://developers.facebook.com/apps/" target="_blank" class="underline ml-1">developers.facebook.com/apps</a>
                    </div>
                </div>
            </div>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    Facebook Messenger
                </h3>
                <p class="text-sm text-slate-600 mt-2">FB Page-ის DM-ები და Inbox.</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Webhook URL</div>
                        <div class="font-mono text-slate-900 break-all">https://bot.gadget.ge/webhooks/messenger</div>
                        <button @click="copy('https://bot.gadget.ge/webhooks/messenger')" class="text-brand-600 hover:underline mt-1">კოპირება</button>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Help</div>
                        <a href="https://developers.facebook.com/docs/messenger-platform/getting-started" target="_blank" class="text-brand-600 hover:underline">FB App → Messenger Settings</a><br>
                        Subscribe to: <code>messages, messaging_postbacks</code>
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'messenger'])
        </section>

        <!-- C) Instagram -->
        <section x-show="active === 'instagram'" x-cloak>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-pink-500"></span>
                    Instagram
                </h3>
                <p class="text-sm text-slate-600 mt-2">IG DM-ები და კომენტარები. Instagram Business Account-ი დაკავშირებული უნდა იყოს FB Page-სთან.</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Webhook URL</div>
                        <div class="font-mono text-slate-900 break-all">https://bot.gadget.ge/webhooks/instagram</div>
                        <button @click="copy('https://bot.gadget.ge/webhooks/instagram')" class="text-brand-600 hover:underline mt-1">კოპირება</button>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Help</div>
                        <a href="https://developers.facebook.com/docs/instagram-api/getting-started" target="_blank" class="text-brand-600 hover:underline">Meta Business Suite → Instagram → Integration</a><br>
                        Subscribe to: <code>messages, comments</code>
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'instagram'])
        </section>

        <!-- D) WooCommerce -->
        <section x-show="active === 'woocommerce'" x-cloak>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                    WooCommerce · gadget.ge
                </h3>
                <p class="text-sm text-slate-600 mt-2">პროდუქტების, stock-ის, კუპონების და ორდერების სინქრო gadget.ge-დან.</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Webhook URL (Woo → us)</div>
                        <div class="font-mono text-slate-900 break-all">https://bot.gadget.ge/webhooks/gadget</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Help</div>
                        gadget.ge WP Admin → WooCommerce → Settings → Advanced → REST API → Create key (Read/Write)
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'woocommerce'])
        </section>

        <!-- E) Payment (BOG) -->
        <section x-show="active === 'payment'" x-cloak>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    BOG / Card Payment
                </h3>
                <p class="text-sm text-slate-600 mt-2">გადახდის ლინკის ავტო-გენერაცია checkout-ისთვის.</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Callback URL</div>
                        <div class="font-mono text-slate-900 break-all">https://bot.gadget.ge/payments/callback</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Help</div>
                        <a href="https://api.bog.ge/docs/payments" target="_blank" class="text-brand-600 hover:underline">BOG developer portal</a> · ეცადე გადახდა მცირე თანხით სანამ live-ზე გადახვალ
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'payment'])
        </section>

        <!-- F) AI Provider -->
        <section x-show="active === 'ai'" x-cloak>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                    AI · Anthropic Claude
                </h3>
                <p class="text-sm text-slate-600 mt-2">AI პასუხების და გადაწყვეტილებების engine. Opus 4.7 (primary) + Haiku 4.5 (light).</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Help</div>
                        <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-brand-600 hover:underline">console.anthropic.com → API Keys → Create</a>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">System prompt</div>
                        <a href="/admin/ai-settings" class="text-brand-600 hover:underline">AI Settings page-ზე →</a>
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'ai'])
        </section>

        <!-- G) Escalation -->
        <section x-show="active === 'escalation'" x-cloak>
            <div class="card mb-4 p-5">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    Escalation · WhatsApp ალერტი
                </h3>
                <p class="text-sm text-slate-600 mt-2">როცა AI ვერ ხედავს გადაწყვეტას ან კლიენტი მძაფრე ემოციით წერს, შენ მიიღებ WhatsApp ალერტს.</p>
                <div class="mt-3 grid sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Format</div>
                        E.164 (მაგ. <code>995599123456</code>) · ქვეყნის კოდი + ნომერი ნულის გარეშე
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <div class="font-medium text-slate-700 mb-1">Test</div>
                        Test ღილაკი გამოგზავნის რეალურ WhatsApp test message-ს (WhatsApp credentials საჭიროა)
                    </div>
                </div>
            </div>
            @include('admin.partials.integration-form', ['group' => 'escalation'])
        </section>

    </div>
</div>

@push('scripts')
<script>
function integrationsPage() {
    return {
        loading: true,
        active: 'whatsapp',
        all: {},
        tabs: [
            { key: 'auto_reply',  label: '🤖 Auto Reply' },
            { key: 'whatsapp',    label: 'WhatsApp' },
            { key: 'messenger',   label: 'Messenger' },
            { key: 'instagram',   label: 'Instagram' },
            { key: 'woocommerce', label: 'WooCommerce' },
            { key: 'payment',     label: 'Payment' },
            { key: 'ai',          label: 'AI' },
            { key: 'escalation',  label: 'Escalation' },
        ],
        // Initialize all groups upfront so Alpine reactivity tracks them
        forms: {
            whatsapp: {}, messenger: {}, instagram: {}, woocommerce: {},
            payment: {}, ai: {}, escalation: {},
        },
        testing: {},
        saving: {},

        ensureForm(group, key) {
            if (!this.forms[group]) this.forms[group] = {};
        },

        // Reseed forms from the server snapshot:
        // - non-secrets: pre-fill with current value (so the field shows the
        //   saved value, can be edited or cleared)
        // - secrets: leave the input empty. Empty input on save = "keep
        //   existing". The masked current value is shown below the input.
        syncFormsFrom(snapshot) {
            for (const g in snapshot) {
                if (!this.forms[g]) this.forms[g] = {};
                for (const k in snapshot[g]) {
                    const item = snapshot[g][k];
                    this.forms[g][k] = item.is_secret ? '' : (item.value ?? '');
                }
            }
        },

        async load() {
            this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                const j = await shell.api('/integrations');
                this.all = j.settings ?? {};
                this.syncFormsFrom(this.all);
                const hash = window.location.hash.slice(1);
                if (this.tabs.some(t => t.key === hash)) this.active = hash;
            } finally { this.loading = false; }
        },

        statusLabel(group) {
            const items = this.all[group] || {};
            const total = Object.keys(items).length;
            const set = Object.values(items).filter(i => i.is_set).length;
            if (total === 0) return '—';
            if (set === 0) return 'not set';
            if (set < total) return set + '/' + total;
            return 'configured';
        },
        statusBadge(group) {
            const items = this.all[group] || {};
            const total = Object.keys(items).length;
            const set = Object.values(items).filter(i => i.is_set).length;
            if (set === 0) return 'bg-slate-100 text-slate-500';
            if (set < total) return 'bg-amber-100 text-amber-700';
            return 'bg-emerald-100 text-emerald-700';
        },

        async save(group) {
            const shell = Alpine.$data(document.body);
            const values = {};
            for (const k in (this.forms[group] || {})) {
                const v = this.forms[group][k];
                const item = (this.all[group] || {})[k] || {};
                const trimmed = (v === null || v === undefined) ? '' : String(v).trim();

                // Non-secrets: always send if filled (overwrites DB)
                // Secrets: only send if user typed something (empty = keep existing)
                if (trimmed !== '') {
                    values[k] = trimmed;
                }
            }
            if (Object.keys(values).length === 0) {
                shell.toast('არცერთი ცვლილება არ შემოგვაქვს', 'warn');
                return;
            }
            this.saving[group] = true;
            try {
                const j = await shell.api('/integrations', { method: 'POST', body: { group, values } });
                this.all[group] = j.settings;
                // Sync forms with server state — non-secrets get the saved value,
                // secret input boxes are cleared so the user can see "saved" badge
                this.syncFormsFrom({ [group]: j.settings });
                const written = j.written ?? 0;
                const sent = Object.keys(values).length;
                if (written === sent) {
                    shell.toast(`✓ შენახულია (${written} ფილდი)`, 'success');
                } else {
                    shell.toast(`⚠️ ${written}/${sent} შენახულია — შემოწმე audit log`, 'warn');
                    console.warn('save audit:', j.audit);
                }
            } catch (e) {
                shell.toast('შენახვა ჩავარდა: ' + (e?.message || 'unknown error'), 'error');
            } finally {
                this.saving[group] = false;
            }
        },

        async remove(group, key) {
            const shell = Alpine.$data(document.body);
            if (! window.confirm(`წავშალო '${this.keyLabel(key)}' DB-დან? (env fallback ისევ მუშაობს)`)) return;
            try {
                const j = await shell.api(`/integrations/${group}/${key}`, { method: 'DELETE' });
                this.all[group] = j.settings;
                this.forms[group][key] = '';
                shell.toast('✓ წაიშალა', 'success');
            } catch (e) {
                shell.toast('წაშლა ჩავარდა: ' + (e?.message || 'unknown error'), 'error');
            }
        },

        async test(type) {
            const shell = Alpine.$data(document.body);
            this.testing[type] = true;
            try {
                const j = await shell.api('/integrations/' + type + '/test', { method: 'POST', body: {} });
                shell.toast((j.success ? '✓ ' : '✗ ') + j.message, j.success ? 'success' : 'error');
            } catch (e) {
            } finally { this.testing[type] = false; }
        },

        copy(text) {
            navigator.clipboard.writeText(text);
            const shell = Alpine.$data(document.body);
            shell.toast('კოპირდა clipboard-ში', 'success');
        },

        keyLabel(key) {
            const labels = {
                AUTO_REPLY_ENABLED: 'Master toggle (true/false)',
                AUTO_REPLY_MESSENGER_ENABLED: 'Messenger ჩართულია (true/false)',
                AUTO_REPLY_WHATSAPP_ENABLED: 'WhatsApp ჩართულია (true/false)',
                AUTO_REPLY_INSTAGRAM_ENABLED: 'Instagram ჩართულია (true/false)',
                AUTO_REPLY_DELAY_SECONDS: 'დაყოვნება, წამები (default 10)',
                AUTO_REPLY_MAX_PER_HOUR: 'Max AI რეპლეი / საათში / conv (default 10)',
                AUTO_REPLY_BUSINESS_HOURS_ONLY: 'მხოლოდ სამუშაო საათებში (true/false)',
                AUTO_REPLY_BUSINESS_HOURS_START: 'სამუშაო საათების დასაწყისი 0-23 (default 10)',
                AUTO_REPLY_BUSINESS_HOURS_END: 'სამუშაო საათების დასასრული 0-23 (default 22)',
                WHATSAPP_PHONE_NUMBER_ID: 'Phone Number ID',
                WHATSAPP_BUSINESS_ACCOUNT_ID: 'Business Account ID',
                WHATSAPP_ACCESS_TOKEN: 'Access Token',
                WHATSAPP_APP_SECRET: 'App Secret',
                WHATSAPP_VERIFY_TOKEN: 'Verify Token',
                MESSENGER_PAGE_ID: 'Page ID',
                MESSENGER_PAGE_ACCESS_TOKEN: 'Page Access Token',
                MESSENGER_APP_SECRET: 'App Secret',
                MESSENGER_VERIFY_TOKEN: 'Verify Token',
                INSTAGRAM_ACCOUNT_ID: 'IG Business Account ID',
                INSTAGRAM_ACCESS_TOKEN: 'Access Token',
                INSTAGRAM_APP_SECRET: 'App Secret',
                INSTAGRAM_VERIFY_TOKEN: 'Verify Token',
                GADGET_WC_BASE_URL: 'WooCommerce Base URL',
                GADGET_WC_CONSUMER_KEY: 'Consumer Key',
                GADGET_WC_CONSUMER_SECRET: 'Consumer Secret',
                GADGET_WC_WEBHOOK_SECRET: 'Webhook Secret',
                PAYMENT_PROVIDER: 'Provider (bog)',
                PAYMENT_API_KEY: 'API Key',
                PAYMENT_API_SECRET: 'API Secret',
                PAYMENT_CALLBACK_URL: 'Callback URL',
                ANTHROPIC_API_KEY: 'Anthropic API Key',
                ANTHROPIC_MODEL_PRIMARY: 'Primary Model',
                ANTHROPIC_MODEL_LIGHT: 'Light Model',
                ANTHROPIC_MAX_TOKENS: 'Max Tokens',
                ESCALATION_WHATSAPP_TO: 'WhatsApp ნომერი (E.164)',
                ESCALATION_ENABLED: 'Enabled (true/false)',
            };
            return labels[key] || key;
        },
    };
}
</script>
@endpush
@endsection

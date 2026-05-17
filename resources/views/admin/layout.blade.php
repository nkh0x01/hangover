<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gadget AI · Inbox</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Noto Sans Georgian", sans-serif; }
        .badge { @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-slate-50 text-slate-800">

<div x-data="inbox()" x-init="boot()" class="flex h-full">

    <aside class="w-80 border-r bg-white flex flex-col">
        <div class="p-4 border-b">
            <div class="text-lg font-semibold">Gadget · Unified Inbox</div>
            <div class="text-xs text-slate-500 mt-1" x-text="me?.name ? me.name + ' · ' + me.role : 'არ ხართ შესული'"></div>
        </div>

        <div class="p-2 flex gap-1 border-b text-xs">
            <button class="px-2 py-1 rounded" :class="filters.platform === '' ? 'bg-slate-200' : ''" @click="filters.platform = ''; load()">ყველა</button>
            <template x-for="p in ['whatsapp','messenger','instagram','facebook']">
                <button class="px-2 py-1 rounded capitalize"
                        :class="filters.platform === p ? 'bg-slate-200' : ''"
                        @click="filters.platform = p; load()" x-text="p"></button>
            </template>
        </div>

        <div class="overflow-y-auto flex-1">
            <template x-for="c in items" :key="c.id">
                <div class="p-3 border-b hover:bg-slate-50 cursor-pointer"
                     :class="active?.id === c.id ? 'bg-blue-50' : ''"
                     @click="open(c.id)">
                    <div class="flex items-center justify-between">
                        <div class="font-medium text-sm truncate" x-text="c.customer.name || c.customer.handle"></div>
                        <span class="badge"
                              :class="{
                                'bg-rose-100 text-rose-700':   c.escalated,
                                'bg-amber-100 text-amber-700': c.ai_paused && !c.escalated,
                                'bg-emerald-100 text-emerald-700': !c.ai_paused && !c.escalated,
                              }"
                              x-text="c.escalated ? 'escalated' : (c.ai_paused ? 'human' : 'AI')"></span>
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-2">
                        <span class="uppercase" x-text="c.platform"></span>
                        <span>·</span>
                        <span x-text="c.lead_status"></span>
                    </div>
                    <div class="text-xs text-slate-600 mt-1 truncate" x-text="c.last_message?.body || ''"></div>
                </div>
            </template>
            <div class="p-6 text-center text-sm text-slate-400" x-show="!loading && items.length === 0">
                ცარიელია 🙌
            </div>
        </div>

        <div class="p-3 border-t text-xs text-slate-500" x-show="!me">
            <input x-model="login.email" placeholder="email" class="border rounded w-full px-2 py-1 mb-1">
            <input x-model="login.password" type="password" placeholder="password" class="border rounded w-full px-2 py-1 mb-1">
            <button class="bg-slate-800 text-white rounded w-full py-1" @click="signin()">შესვლა</button>
        </div>
    </aside>

    <main class="flex-1 flex flex-col">
        <div x-show="!active" class="flex-1 flex items-center justify-center text-slate-400">
            აირჩიე ჩატი მარცხნივ
        </div>

        <template x-if="active">
            <div class="flex-1 flex flex-col">
                <header class="p-4 border-b bg-white flex items-center justify-between">
                    <div>
                        <div class="font-semibold" x-text="active.customer?.display_name || active.customer?.platform_user_id"></div>
                        <div class="text-xs text-slate-500" x-text="active.conversation.platform.toUpperCase() + ' · ' + active.conversation.lead_status"></div>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-3 py-1 text-sm rounded border" @click="takeover()" x-show="!active.conversation.ai_paused">Take over</button>
                        <button class="px-3 py-1 text-sm rounded border" @click="release()"  x-show="active.conversation.ai_paused || active.conversation.escalated">Release</button>
                        <button class="px-3 py-1 text-sm rounded border text-rose-700" @click="spam()">Spam</button>
                    </div>
                </header>

                <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50" x-ref="messages">
                    <template x-for="m in active.messages" :key="m.id">
                        <div class="flex" :class="m.direction === 'inbound' ? 'justify-start' : 'justify-end'">
                            <div class="max-w-lg rounded-lg px-3 py-2 text-sm shadow-sm"
                                 :class="m.direction === 'inbound'
                                    ? 'bg-white border'
                                    : (m.is_ai ? 'bg-emerald-100' : 'bg-blue-100')">
                                <div x-text="m.body || (m.media?.url ? '[media] ' + m.media.url : '')"></div>
                                <div class="text-[10px] text-slate-500 mt-1">
                                    <span x-text="new Date(m.created_at).toLocaleTimeString('ka-GE')"></span>
                                    <span x-show="m.is_ai">· AI</span>
                                    <span x-show="m.author?.name" x-text="'· ' + m.author.name"></span>
                                    <span x-show="m.confidence" x-text="'· conf ' + Number(m.confidence).toFixed(2)"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <footer class="border-t bg-white p-3">
                    <form @submit.prevent="send()" class="flex gap-2">
                        <input x-model="draft" placeholder="დაწერეთ პასუხი..." class="flex-1 border rounded px-3 py-2 text-sm">
                        <button class="bg-slate-800 text-white rounded px-4 py-2 text-sm">გაგზავნა</button>
                    </form>
                </footer>
            </div>
        </template>
    </main>

    <aside class="w-80 border-l bg-white p-4 overflow-y-auto" x-show="active">
        <h3 class="text-sm font-semibold text-slate-500 uppercase mb-2">Customer</h3>
        <pre class="text-xs bg-slate-50 rounded p-2 overflow-x-auto" x-text="JSON.stringify(active?.customer, null, 2)"></pre>

        <h3 class="text-sm font-semibold text-slate-500 uppercase mt-4 mb-2">Memory</h3>
        <pre class="text-xs bg-slate-50 rounded p-2 overflow-x-auto" x-text="JSON.stringify(active?.customer?.profile_json || {}, null, 2)"></pre>
    </aside>
</div>

<script>
function inbox() {
    return {
        token: localStorage.getItem('admin_token') || null,
        me: null,
        items: [],
        active: null,
        loading: false,
        filters: { platform: '' },
        draft: '',
        login: { email: '', password: '' },

        async boot() {
            if (this.token) {
                try {
                    this.me = await this.api('GET', '/api/admin/auth/me');
                    await this.load();
                    setInterval(() => this.load(), 30000);
                    setInterval(() => this.active && this.refreshActive(), 5000);
                } catch (e) { this.token = null; localStorage.removeItem('admin_token'); }
            }
        },

        async signin() {
            const r = await fetch('/api/admin/auth/login', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(this.login),
            });
            if (!r.ok) return alert('არასწორი მონაცემები');
            const data = await r.json();
            this.token = data.token; this.me = data.user;
            localStorage.setItem('admin_token', this.token);
            await this.load();
        },

        async api(method, url, body) {
            const r = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + this.token,
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            if (!r.ok) throw new Error('http ' + r.status);
            return r.json();
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.filters.platform) params.set('platform', this.filters.platform);
                const data = await this.api('GET', '/api/admin/inbox?' + params);
                this.items = data.data || [];
            } finally { this.loading = false; }
        },

        async open(id) {
            this.active = await this.api('GET', '/api/admin/inbox/' + id);
            this.$nextTick(() => this.$refs.messages?.scrollTo(0, 1e9));
        },
        async refreshActive() {
            const fresh = await this.api('GET', '/api/admin/inbox/' + this.active.conversation.id);
            this.active = fresh;
        },
        async send() {
            if (!this.draft.trim()) return;
            await this.api('POST', '/api/admin/inbox/' + this.active.conversation.id + '/reply', { body: this.draft });
            this.draft = '';
            await this.refreshActive();
        },
        async takeover() {
            await this.api('POST', '/api/admin/inbox/' + this.active.conversation.id + '/takeover');
            await this.refreshActive();
        },
        async release() {
            await this.api('POST', '/api/admin/inbox/' + this.active.conversation.id + '/release');
            await this.refreshActive();
        },
        async spam() {
            if (!confirm('Mark as spam?')) return;
            await this.api('POST', '/api/admin/inbox/' + this.active.conversation.id + '/spam');
            await this.load();
        },
    };
}
</script>
</body>
</html>

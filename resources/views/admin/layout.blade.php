<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Gadget AI</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif', 'Noto Sans Georgian', 'Apple Color Emoji', 'Segoe UI Emoji'],
            },
            colors: {
              brand: {
                50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                800: '#3730a3', 900: '#312e81', 950: '#1e1b4b',
              },
            },
          },
        },
      };
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
      body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Noto Sans Georgian', sans-serif; }
      [x-cloak] { display: none !important; }
      .nav-link { display:flex; align-items:center; gap:.75rem; padding:.5rem .75rem; border-radius:.5rem; font-size:.875rem; color:rgb(71 85 105); transition: all .15s; }
      .nav-link:hover { background:rgb(241 245 249); color:rgb(15 23 42); }
      .nav-link.active { background:rgb(238 242 255); color:rgb(67 56 202); font-weight:500; }
      .nav-link svg { width:1.25rem; height:1.25rem; flex-shrink:0; }
      .card { background:white; border-radius:.75rem; border:1px solid rgb(226 232 240); box-shadow: 0 1px 2px rgb(0 0 0 / 0.05); }
      .btn { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem .75rem; border-radius:.5rem; font-size:.875rem; font-weight:500; transition: all .15s; cursor: pointer; }
      .btn:disabled { opacity:.5; cursor:not-allowed; }
      .btn-primary { background:rgb(79 70 229); color:white; }
      .btn-primary:hover:not(:disabled) { background:rgb(67 56 202); }
      .btn-secondary { background:white; border:1px solid rgb(226 232 240); color:rgb(51 65 85); }
      .btn-secondary:hover:not(:disabled) { background:rgb(248 250 252); }
      .btn-danger { background:rgb(220 38 38); color:white; }
      .btn-danger:hover:not(:disabled) { background:rgb(185 28 28); }
      .badge { display:inline-flex; align-items:center; padding:.125rem .5rem; border-radius:.25rem; font-size:.75rem; font-weight:500; }
      .field-label { display:block; font-size:.75rem; font-weight:500; color:rgb(71 85 105); margin-bottom:.25rem; }
      .field-input { width:100%; padding:.5rem .75rem; border-radius:.5rem; border:1px solid rgb(226 232 240); background:white; font-size:.875rem; outline:none; }
      .field-input:focus { border-color:rgb(99 102 241); box-shadow: 0 0 0 1px rgb(99 102 241); }
      .field-input[readonly] { background:rgb(248 250 252); color:rgb(100 116 139); }
    </style>
    @stack('head')
</head>
<body class="bg-slate-50 text-slate-800 antialiased" x-data="adminShell()" x-init="boot()" x-cloak>

<div class="min-h-screen flex" x-show="ready">
    @include('admin.partials.sidebar')

    <main class="flex-1 min-w-0 flex flex-col">
        @include('admin.partials.topbar')

        @include('admin.partials.demo-banner')

        <div class="flex-1 p-6 max-w-[1400px] w-full mx-auto">
            @yield('content')
        </div>
    </main>
</div>

<!-- Boot-time loader -->
<div x-show="!ready" class="min-h-screen grid place-items-center bg-slate-50">
    <div class="text-center">
        <div class="w-10 h-10 mx-auto mb-3 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
        <div class="text-sm text-slate-500">იტვირთება…</div>
    </div>
</div>

<!-- Toast container -->
<div class="fixed bottom-6 right-6 space-y-2 z-50" x-data="{ toasts: [] }"
     x-init="window.addEventListener('toast', e => { const t = { id: Date.now(), ...e.detail }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 4500); })">
    <template x-for="t in toasts" :key="t.id">
        <div :class="t.type === 'error' ? 'bg-red-600' : (t.type === 'warn' ? 'bg-amber-600' : (t.type === 'success' ? 'bg-emerald-600' : 'bg-slate-900'))"
             class="text-white px-4 py-2 rounded-lg shadow-lg text-sm max-w-md">
            <span x-text="t.message"></span>
        </div>
    </template>
</div>

<script>
function adminShell() {
    return {
        ready: false,
        me: null,
        token: localStorage.getItem('admin_token') || null,
        currentPath: window.location.pathname,

        async boot() {
            if (!this.token) {
                window.location.href = '/admin/login';
                return;
            }
            try {
                const r = await fetch('/api/admin/auth/me', {
                    headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json' },
                });
                if (!r.ok) throw new Error('unauthenticated');
                const j = await r.json();
                this.me = j.user ?? j;
                this.ready = true;
            } catch (e) {
                localStorage.removeItem('admin_token');
                window.location.href = '/admin/login';
            }
        },

        async logout() {
            try {
                await fetch('/api/admin/auth/logout', {
                    method: 'POST',
                    headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json' },
                });
            } catch (e) {}
            localStorage.removeItem('admin_token');
            window.location.href = '/admin/login';
        },

        isActive(path) {
            return this.currentPath === path || this.currentPath.startsWith(path + '/');
        },

        toast(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },

        async api(path, opts = {}) {
            const r = await fetch('/api/admin' + path, {
                ...opts,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.token,
                    ...(opts.headers || {}),
                },
                body: opts.body ? JSON.stringify(opts.body) : undefined,
            });
            if (r.status === 401) {
                localStorage.removeItem('admin_token');
                window.location.href = '/admin/login';
                return;
            }
            const j = await r.json().catch(() => ({}));
            if (!r.ok) {
                this.toast(j.message || j.error || ('Error ' + r.status), 'error');
                throw new Error(j.message || j.error || ('HTTP ' + r.status));
            }
            return j;
        },
    };
}
window.adminShell = adminShell;
</script>

@stack('scripts')
</body>
</html>

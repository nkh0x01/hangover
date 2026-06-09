<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>შესვლა · Gadget AI</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
      tailwind.config = {
        theme: { extend: {
          fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif','Noto Sans Georgian','Apple Color Emoji','Segoe UI Emoji'] },
          colors: { brand: { 50:'#eef2ff',100:'#e0e7ff',500:'#6366f1',600:'#4f46e5',700:'#4338ca' } },
        }}
      };
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>body{font-family:'Inter',ui-sans-serif,'Noto Sans Georgian',system-ui,sans-serif}[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-brand-50 grid place-items-center p-4">

<div class="w-full max-w-md" x-data="loginPage()" x-init="onMount()">
    <!-- Brand -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-600 text-white text-2xl font-bold shadow-lg mb-4">G</div>
        <h1 class="text-2xl font-bold text-slate-900">Gadget AI</h1>
        <p class="text-sm text-slate-500">Omnichannel AI sales · Admin panel</p>
    </div>

    <!-- Form card -->
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
        <h2 class="text-xl font-semibold text-slate-900 mb-1">შესვლა</h2>
        <p class="text-sm text-slate-500 mb-6">შეიყვანე შენი credentials მენეჯმენტ პანელში მისახვედრად</p>

        <form @submit.prevent="signin()" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">ელფოსტა</label>
                <input x-model="form.email" type="email" required autofocus
                       placeholder="owner@gadget.ge"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">პაროლი</label>
                <input x-model="form.password" type="password" required
                       placeholder="••••••••"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
            </div>

            <div x-show="error" x-cloak class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2" x-text="error"></div>

            <button type="submit" :disabled="busy"
                    class="w-full bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 rounded-lg transition disabled:opacity-50 flex items-center justify-center gap-2">
                <svg x-show="busy" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20" fill="none"/></svg>
                <span x-text="busy ? 'ვშედივართ…' : 'შესვლა'"></span>
            </button>
        </form>

        <!-- Demo credentials hint -->
        <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-500 bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <div>
                    <strong>Demo credentials:</strong> <code>owner@gadget.ge</code> / <code>password</code><br>
                    <span class="text-amber-700">პროდუქშენში გადასვლამდე შეცვალე password!</span>
                </div>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">
        © {{ date('Y') }} Gadget AI · <a href="https://bot.gadget.ge/up" class="hover:underline">status</a>
    </p>
</div>

<script>
function loginPage() {
    return {
        form: { email: '', password: '' },
        busy: false,
        error: '',
        onMount() {
            if (localStorage.getItem('admin_token')) {
                window.location.href = '/admin/dashboard';
            }
        },
        async signin() {
            this.busy = true; this.error = '';
            try {
                const r = await fetch('/api/admin/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) {
                    this.error = j.message || j.error || 'არასწორი მონაცემები';
                    return;
                }
                localStorage.setItem('admin_token', j.token);
                window.location.href = '/admin/dashboard';
            } catch (e) {
                this.error = 'ქსელის შეცდომა: ' + e.message;
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>

</body>
</html>

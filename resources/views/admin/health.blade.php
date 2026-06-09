@extends('admin.layout')

@section('title', 'Bot Health')
@section('subtitle', 'Live diagnostic — Messenger beta readiness')

@section('content')
<div x-data="healthPage()" x-init="boot()">

    <div class="flex items-center gap-3 mb-4">
        <div class="card px-4 py-3 flex items-center gap-3 flex-1">
            <div class="w-3 h-3 rounded-full"
                 :class="{
                    'bg-emerald-500': data?.status === 'ok',
                    'bg-amber-500': data?.status === 'warn',
                    'bg-red-500': data?.status === 'fail',
                    'bg-slate-300': !data,
                 }"></div>
            <div class="flex-1">
                <div class="font-semibold text-slate-900">
                    <span x-show="!data" x-cloak>—</span>
                    <span x-show="data?.status === 'ok'" x-cloak class="text-emerald-700">ALL SYSTEMS GO</span>
                    <span x-show="data?.status === 'warn'" x-cloak class="text-amber-700">SOME WARNINGS</span>
                    <span x-show="data?.status === 'fail'" x-cloak class="text-red-700">FAILURES DETECTED</span>
                </div>
                <div class="text-xs text-slate-400" x-text="data?.computed_at"></div>
            </div>
        </div>
        <button @click="run()" :disabled="loading" class="btn btn-primary">
            <svg :class="loading && 'animate-spin'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span x-text="loading ? 'ვამოწმებთ…' : 'Run Health Check'"></span>
        </button>
    </div>

    <div x-show="data?.checks" x-cloak class="card divide-y divide-slate-100">
        <template x-for="c in (data?.checks || [])" :key="c.key">
            <div class="flex items-start gap-4 p-4">
                <div :class="{
                  'bg-emerald-50 text-emerald-700': c.status === 'ok',
                  'bg-amber-50 text-amber-700': c.status === 'warn',
                  'bg-red-50 text-red-700': c.status === 'fail',
                  'bg-slate-100 text-slate-500': c.status === 'pending',
                }" class="w-8 h-8 rounded-full grid place-items-center shrink-0 text-xs font-bold">
                    <span x-show="c.status === 'ok'" x-cloak>✓</span>
                    <span x-show="c.status === 'warn'" x-cloak>!</span>
                    <span x-show="c.status === 'fail'" x-cloak>✕</span>
                    <span x-show="c.status === 'pending'" x-cloak>·</span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-semibold text-slate-900" x-text="c.title"></h4>
                        <span :class="{
                            'bg-emerald-100 text-emerald-700': c.status === 'ok',
                            'bg-amber-100 text-amber-700': c.status === 'warn',
                            'bg-red-100 text-red-700': c.status === 'fail',
                            'bg-slate-100 text-slate-500': c.status === 'pending',
                        }" class="badge text-[10px]" x-text="c.status"></span>
                    </div>
                    <p class="text-sm text-slate-600 mt-0.5" x-text="c.message"></p>
                    <p x-show="c.hint" class="text-xs text-slate-400 mt-1" x-text="c.hint"></p>
                </div>
            </div>
        </template>
    </div>

    <div class="card mt-4 p-4 bg-slate-50 text-xs text-slate-600">
        <div class="font-semibold text-slate-700 mb-1">Pre-deploy safety</div>
        <p>ნებისმიერი deploy-ის წინ/შემდეგ გაუშვი:
            <code class="bg-slate-200 px-1.5 py-0.5 rounded">php artisan bot:preflight</code>
            — ამოწმებს syntax, duplicate imports, route/config load, /up, webhook verify.
        </p>
        <p class="mt-1">Recovery checklist: <code class="bg-slate-200 px-1.5 py-0.5 rounded">storage/app/bot_docs/beta_recovery_checklist.md</code></p>
    </div>
</div>

@push('scripts')
<script>
function healthPage() {
    return {
        loading: false,
        data: null,
        boot() { this.run(); setInterval(() => this.run(false), 30000); },
        async run(showLoading = true) {
            if (showLoading) this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                this.data = await shell.api('/health');
            } catch (e) {} finally { this.loading = false; }
        },
    };
}
</script>
@endpush
@endsection

@extends('admin.layout')

@section('title', 'Setup Checklist')
@section('subtitle', 'ცოცხალი მდგომარეობა — ყველაფერი მზად არის თუ რა აკლია')

@section('content')
<div x-data="checklistPage()" x-init="load()">

    <div class="card mb-4 p-4 flex items-center gap-3">
        <button @click="load()" :disabled="loading"
                class="btn btn-secondary !py-1.5 text-xs">
            <svg :class="loading && 'animate-spin'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            განახლება
        </button>
        <div class="flex-1 text-sm text-slate-600">
            <span x-text="items.length ? (items.filter(i => i.status === 'ok').length + '/' + items.length + ' შესრულებული') : '—'"></span>
        </div>
        <div class="flex gap-1.5">
            <span class="badge bg-emerald-100 text-emerald-700">ok</span>
            <span class="badge bg-amber-100 text-amber-700">warn</span>
            <span class="badge bg-slate-100 text-slate-500">pending</span>
            <span class="badge bg-red-100 text-red-700">fail</span>
        </div>
    </div>

    <div x-show="loading && items.length === 0" x-cloak class="card p-10 text-center text-sm text-slate-500">
        <div class="w-6 h-6 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
        ვამოწმებთ მდგომარეობას…
    </div>

    <div class="card divide-y divide-slate-100" x-show="items.length > 0" x-cloak>
        <template x-for="item in items" :key="item.key">
            <div class="flex items-start gap-4 p-4">
                <div :class="iconBg(item.status)" class="w-8 h-8 rounded-full grid place-items-center shrink-0">
                    <template x-if="item.status === 'ok'">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    </template>
                    <template x-if="item.status === 'warn'">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </template>
                    <template x-if="item.status === 'fail'">
                        <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </template>
                    <template x-if="item.status === 'pending'">
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                    </template>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-semibold text-slate-900" x-text="item.title"></h4>
                        <span :class="badge(item.status)" class="badge" x-text="item.status"></span>
                    </div>
                    <p class="text-sm text-slate-600 mt-0.5" x-text="item.message"></p>
                    <p x-show="item.hint" class="text-xs text-slate-400 mt-1" x-text="item.hint"></p>
                </div>
                <a x-show="actionLink(item.key)" :href="actionLink(item.key)" class="text-sm text-brand-600 hover:underline shrink-0">გადასვლა →</a>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
function checklistPage() {
    return {
        loading: false,
        items: [],
        async load() {
            this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                const j = await shell.api('/setup-checklist');
                this.items = j.items ?? [];
            } finally { this.loading = false; }
        },
        iconBg(s) { return {ok:'bg-emerald-50',warn:'bg-amber-50',fail:'bg-red-50',pending:'bg-slate-100'}[s] || 'bg-slate-100'; },
        badge(s) { return {ok:'bg-emerald-100 text-emerald-700',warn:'bg-amber-100 text-amber-700',fail:'bg-red-100 text-red-700',pending:'bg-slate-100 text-slate-500'}[s] || 'bg-slate-100 text-slate-500'; },
        actionLink(key) {
            return {
                whatsapp: '/admin/integrations#whatsapp',
                messenger: '/admin/integrations#messenger',
                instagram: '/admin/integrations#instagram',
                woocommerce: '/admin/integrations#woocommerce',
                ai: '/admin/integrations#ai',
                payment: '/admin/integrations#payment',
                escalation: '/admin/integrations#escalation',
                webhooks: '/admin/integrations',
            }[key] || null;
        },
    };
}
</script>
@endpush
@endsection

@extends('admin.layout')

@section('title', 'AI Settings')
@section('subtitle', 'Claude-ის ქცევა, system prompt, model არჩევანი')

@section('content')
<div x-data="aiPage()" x-init="load()">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-slate-900">System Prompt</h3>
                    <button @click="loadPrompts()" class="btn btn-secondary !py-1 text-xs">Refresh</button>
                </div>

                <div x-show="active" x-cloak>
                    <div class="text-xs text-slate-500 mb-2">
                        Slug: <code x-text="active?.slug"></code> ·
                        v<span x-text="active?.version"></span> ·
                        <span x-text="active?.is_active ? 'ACTIVE' : 'draft'"
                              :class="active?.is_active ? 'text-emerald-600 font-medium' : 'text-slate-500'"></span>
                    </div>
                    <textarea x-model="draft" rows="14"
                              class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-mono outline-none focus:border-brand-500"></textarea>
                    <div class="flex justify-between items-center mt-3">
                        <div class="text-xs text-slate-500" x-text="(draft || '').length + ' chars'"></div>
                        <button @click="save()" class="btn btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            შენახვა (ახალი version)
                        </button>
                    </div>
                </div>

                <div x-show="!active" x-cloak class="text-center py-10 text-sm text-slate-500">
                    Loading…
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Model</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Primary</span><span class="font-mono text-slate-900">claude-opus-4-7</span></div>
                    <div class="flex justify-between"><span class="text-slate-600">Light</span><span class="font-mono text-slate-900">claude-haiku-4-5</span></div>
                </div>
                <a href="/admin/integrations#ai" class="text-xs text-brand-600 hover:underline mt-3 inline-block">შეცვლა Integrations → AI</a>
            </div>

            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 mb-3">Prompt versions</h3>
                <div class="space-y-1 text-sm">
                    <template x-for="p in prompts" :key="p.id">
                        <button @click="select(p)"
                                :class="active?.id === p.id && 'bg-brand-50 text-brand-700'"
                                class="w-full text-left px-2 py-1.5 rounded hover:bg-slate-50 flex justify-between text-xs">
                            <span class="truncate"><span x-text="p.slug"></span> · v<span x-text="p.version"></span></span>
                            <span x-show="p.is_active" class="text-emerald-600">active</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function aiPage() {
    return {
        prompts: [],
        active: null,
        draft: '',
        async load() { await this.loadPrompts(); },
        async loadPrompts() {
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/settings/prompts');
                this.prompts = j.prompts ?? j ?? [];
                const a = this.prompts.find(p => p.is_active) || this.prompts[0];
                if (a) this.select(a);
            } catch (e) {}
        },
        select(p) { this.active = p; this.draft = p.body || ''; },
        async save() {
            if (!this.draft.trim()) return;
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/settings/prompts', { method: 'POST', body: { slug: this.active.slug, body: this.draft } });
                shell.toast('✓ ახალი version შენახულია', 'success');
                this.loadPrompts();
            } catch (e) {}
        },
    };
}
</script>
@endpush
@endsection

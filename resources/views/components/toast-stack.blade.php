{{--
    App-wide toast stack. Listens for window events `toast` from anywhere
    (Livewire $this->dispatch('toast', tone: ..., message: ...)).
    Tones: ok | warn | error | info
--}}
<div
    x-data="{
        toasts: [],
        push(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, tone: detail.tone || 'ok', message: detail.message || '' });
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 3500);
        },
        toneClass(tone) {
            return {
                ok:    'bg-emerald-600',
                warn:  'bg-amber-600',
                error: 'bg-red-600',
                info:  'bg-slate-800',
            }[tone] || 'bg-slate-800';
        },
    }"
    @toast.window="push($event.detail[0] || $event.detail)"
    class="fixed bottom-6 right-6 z-[60] flex flex-col gap-2"
>
    <template x-for="t in toasts" :key="t.id">
        <div
            x-transition:enter="transform transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="toneClass(t.tone)"
            class="min-w-[220px] max-w-sm rounded-md px-4 py-2.5 text-sm font-medium text-white shadow-lg"
            x-text="t.message"
        ></div>
    </template>
</div>

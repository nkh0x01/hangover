{{--
    Global keyboard-shortcuts help overlay. Press "?" anywhere to open.
    Listed shortcuts are best-effort hints; pages wire their own handlers.
--}}
<div x-data="{ open: false }"
     @keydown.window="if ((event.key === '?' || (event.shiftKey && event.key === '/')) && event.target.tagName === 'BODY') { open = true; event.preventDefault(); }"
     @keydown.window.escape="open = false">

    {{-- Listen for a custom event so the topbar button can open the overlay --}}
    <div @open-keyboard-help.window="open = true" class="hidden"></div>

    {{-- overlay --}}
    <div x-show="open" x-cloak
         x-transition.opacity
         @click="open = false"
         class="fixed inset-0 z-[55] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.stop
             class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Keyboard shortcuts') }}</h3>
                <button @click="open = false" aria-label="{{ __('Close') }}" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Work without leaving the keyboard.') }}</p>

            <dl class="mt-4 grid grid-cols-1 gap-y-2 text-sm sm:grid-cols-[auto,1fr] sm:gap-x-4">
                @php
                    $shortcuts = [
                        ['?', __('Open this help')],
                        ['Esc', __('Close any modal')],
                        ['C', __('Check in (reservation page)')],
                        ['X', __('Check out (reservation page)')],
                        ['P', __('Record payment (reservation page)')],
                        ['Enter', __('Submit form / modal')],
                    ];
                @endphp
                @foreach ($shortcuts as [$key, $label])
                    <dt class="font-mono"><kbd class="rounded bg-slate-100 px-2 py-0.5 text-xs ring-1 ring-slate-200">{{ $key }}</kbd></dt>
                    <dd class="text-slate-700">{{ $label }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
</div>

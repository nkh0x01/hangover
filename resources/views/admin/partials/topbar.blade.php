<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 shrink-0 sticky top-0 z-30">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">@yield('title', 'Dashboard')</h1>
        <p class="text-xs text-slate-500">@yield('subtitle', '')</p>
    </div>

    <div class="flex items-center gap-3">
        <a href="https://bot.gadget.ge" target="_blank" rel="noopener" class="text-xs text-slate-500 hover:text-slate-900 hidden md:block">
            bot.gadget.ge ↗
        </a>

        <div class="flex items-center gap-3 pl-4 border-l border-slate-200">
            <div class="text-right hidden md:block">
                <div class="text-sm font-medium text-slate-900" x-text="me?.name || ''"></div>
                <div class="text-xs text-slate-500" x-text="me?.role || ''"></div>
            </div>
            <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 grid place-items-center font-semibold"
                 x-text="(me?.name || '?').charAt(0).toUpperCase()"></div>
            <button @click="logout()" class="btn btn-secondary !py-1.5" title="Logout">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                <span class="hidden md:inline">გასვლა</span>
            </button>
        </div>
    </div>
</header>

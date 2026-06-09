<div x-data="{ show: localStorage.getItem('demo_banner_dismissed') !== '1' }" x-show="show" x-cloak
     class="bg-amber-50 border-b border-amber-200 px-6 py-2.5 flex items-center gap-3 text-sm">
    <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    <div class="flex-1 text-amber-900">
        <strong>Default demo password is active.</strong>
        პროდუქშენში გადასვლამდე აუცილებლად შეცვალე
        <code class="bg-amber-100 px-1.5 py-0.5 rounded text-xs">owner@gadget.ge / password</code>.
    </div>
    <a href="/admin/settings" class="text-sm text-amber-900 underline hover:no-underline">შეცვლა →</a>
    <button @click="show = false; localStorage.setItem('demo_banner_dismissed', '1')" class="text-amber-700 hover:text-amber-900" title="დახურვა">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

@extends('admin.layout')

@section('title', 'Unified Inbox')
@section('subtitle', 'WhatsApp · Messenger · Instagram · Facebook ერთ ადგილზე')

@section('content')
<!-- Meta Development Mode banner (dismissable) -->
<div x-data="{ show: localStorage.getItem('meta_devmode_banner_dismissed') !== '1' }" x-show="show" x-cloak
     class="mb-3 bg-amber-50 border border-amber-300 rounded-lg p-3 flex items-start gap-3 text-sm">
    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    <div class="flex-1 text-amber-900">
        <strong>⚠ Meta App-ი შესაძლოა Development Mode-ში იყოს.</strong>
        Development mode-ში Messenger Page-ს მესიჯს მხოლოდ <strong>App Roles</strong>-ში დამატებული users-ი (Admin / Developer / Tester) გვერდს გადასცემს ბოტს — სხვა მომხმარებლების DM-ი webhook-ი არ მოვა.
        <div class="mt-1 text-xs">
            <strong>გადასაჭრელად:</strong>
            <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener" class="underline hover:no-underline">developers.facebook.com/apps</a>
            → შენი App → <code class="bg-amber-100 px-1 py-0.5 rounded">App Roles → Roles</code>
            → Add People → Tester / Developer / Admin → დაამატე ვინც გვინდა რომ ცადოს.
            ან: App Review → Publish app to Live (production-ისთვის).
        </div>
    </div>
    <button @click="show = false; localStorage.setItem('meta_devmode_banner_dismissed', '1')" class="text-amber-700 hover:text-amber-900" title="დახურვა">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

<div x-data="inboxPage()" x-init="boot()" class="card overflow-hidden" style="height: calc(100vh - 240px); min-height: 600px;">
    <div class="flex h-full">

        <!-- ============= LEFT: Conversation list ============= -->
        <aside class="w-72 shrink-0 border-r border-slate-200 flex flex-col"
               :class="active && 'hidden md:flex'">

            <!-- Debug counter + refresh -->
            <div class="px-3 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-600 flex items-center justify-between">
                <span>Loaded <strong x-text="conversations.length"></strong> conversations</span>
                <div class="flex items-center gap-2">
                    <span x-show="autoRefreshing" x-cloak class="text-brand-600">•</span>
                    <button @click="load(true)" :disabled="loading" class="text-brand-600 hover:underline">↻</button>
                </div>
            </div>

            <!-- Search -->
            <div class="p-2 border-b border-slate-100">
                <input x-model="filters.q" @input.debounce.400ms="load()" placeholder="ძიება…"
                       class="w-full px-2.5 py-1.5 text-sm rounded-md border border-slate-200 outline-none focus:border-brand-500">
            </div>

            <!-- Platform filter -->
            <div class="px-2 pt-2 flex gap-1 flex-wrap text-xs">
                <template x-for="p in [{k:'',l:'ყველა'},{k:'whatsapp',l:'WA'},{k:'messenger',l:'FB'},{k:'instagram',l:'IG'},{k:'facebook',l:'Page'}]">
                    <button @click="filters.platform = p.k; load()"
                            :class="filters.platform === p.k ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'"
                            class="px-2 py-1 rounded font-medium" x-text="p.l"></button>
                </template>
            </div>

            <!-- Quick toggles -->
            <div class="px-2 py-2 flex gap-1 border-b border-slate-100 text-xs flex-wrap">
                <button @click="toggleFilter('unread')"
                        :class="filters.unread ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-2 py-1 rounded">● Unread</button>
                <button @click="toggleFilter('escalated')"
                        :class="filters.escalated ? 'bg-red-50 text-red-700' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-2 py-1 rounded">⚠ Escalated</button>
                <button @click="toggleFilter('unanswered')"
                        :class="filters.unanswered ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-100'"
                        class="px-2 py-1 rounded">⏱ Unanswered</button>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto">
                <div x-show="loading && conversations.length === 0" x-cloak class="p-6 text-center text-sm text-slate-500">
                    <div class="w-5 h-5 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
                    იტვირთება…
                </div>
                <div x-show="!loading && conversations.length === 0" x-cloak class="p-6 text-center text-sm text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75"/></svg>
                    ფილტრებში არცერთი არ ჯდება.
                </div>
                <template x-for="c in conversations" :key="c.id">
                    <button @click="open(c.id)"
                            :class="active?.id === c.id ? 'bg-brand-50' : (c.unread ? 'bg-blue-50/30' : '')"
                            class="w-full text-left px-3 py-2.5 border-b border-slate-100 hover:bg-slate-50 flex items-start gap-2.5 relative">
                        <!-- Unread dot -->
                        <span x-show="c.unread && active?.id !== c.id" x-cloak
                              class="absolute left-1 top-3 w-1.5 h-1.5 rounded-full bg-brand-600"></span>
                        <!-- Avatar -->
                        <div class="shrink-0 relative">
                            <template x-if="c.customer?.profile_pic">
                                <img :src="c.customer.profile_pic" :alt="c.customer?.name || c.thread_id"
                                     class="w-9 h-9 rounded-full object-cover">
                            </template>
                            <template x-if="!c.customer?.profile_pic">
                                <div :class="platformBg(c.platform)" class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-semibold"
                                     x-text="(c.customer?.name || c.thread_id || '?').charAt(0).toUpperCase()"></div>
                            </template>
                            <span :class="platformColor(c.platform)" class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white" :title="c.platform"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-slate-900 truncate"
                                      :class="c.unread && 'font-semibold'"
                                      x-text="c.customer?.name || c.customer?.handle || c.thread_id || '—'"></span>
                                <span class="text-[10px] text-slate-400 ml-auto shrink-0" x-text="formatTime(c.last_inbound)"></span>
                            </div>
                            <div class="text-xs text-slate-500 truncate" x-text="c.last_message?.body || c.platform"></div>
                            <div class="flex items-center gap-1 mt-1 flex-wrap">
                                <span class="badge bg-slate-100 text-slate-600 text-[10px]" x-text="c.lead_status || 'new'"></span>
                                <span x-show="c.ai_paused" class="badge bg-amber-100 text-amber-700 text-[10px]">paused</span>
                                <span x-show="c.escalated" class="badge bg-red-100 text-red-700 text-[10px]">escalated</span>
                                <span x-show="c.note_count > 0" class="badge bg-purple-100 text-purple-700 text-[10px]" x-text="'📝 ' + c.note_count"></span>
                                <span x-show="c.assigned" class="badge bg-emerald-100 text-emerald-700 text-[10px]" x-text="c.assigned?.name"></span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </aside>

        <!-- ============= CENTER: Thread ============= -->
        <section class="flex-1 flex flex-col min-w-0"
                 :class="!active && 'hidden md:flex'">

            <!-- Empty state -->
            <div x-show="!active" x-cloak class="flex-1 grid place-items-center text-slate-400 p-6 text-center">
                <div>
                    <svg class="w-16 h-16 mx-auto mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512"/></svg>
                    აირჩიე კონვერსაცია მარცხნივ
                </div>
            </div>

            <template x-if="active">
                <div class="flex flex-col h-full">
                    <!-- Header -->
                    <div class="px-4 py-2.5 border-b border-slate-200 flex items-center gap-3">
                        <button @click="active = null" class="md:hidden p-1 hover:bg-slate-100 rounded" title="უკან">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        </button>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900 truncate" x-text="customerLabel()"></div>
                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                <span :class="platformColor(active.platform)" class="w-1.5 h-1.5 rounded-full"></span>
                                <span x-text="active.platform + ' · ' + (active.lead_status || 'new')"></span>
                                <span x-show="active.ai_paused" class="badge bg-amber-100 text-amber-700 text-[10px]">AI paused</span>
                                <span x-show="active.escalated" class="badge bg-red-100 text-red-700 text-[10px]">escalated</span>
                                <template x-if="active.auto_reply?.enabled">
                                    <span class="badge bg-emerald-100 text-emerald-700 text-[10px]" title="Auto-reply will fire for new inbound messages">🤖 Auto ON</span>
                                </template>
                                <template x-if="active.auto_reply && !active.auto_reply.enabled">
                                    <button @click="showWhyNoReply = !showWhyNoReply"
                                            class="badge bg-slate-100 text-slate-600 text-[10px] hover:bg-slate-200 cursor-pointer"
                                            :title="'Click to see why bot didn\'t reply'"
                                            x-text="'🤖 Auto OFF · ' + active.auto_reply.reason"></button>
                                </template>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="markUnread()" class="btn btn-secondary !py-1 !px-2 text-xs" title="Mark unread">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25"/></svg>
                            </button>
                            <button @click="toggleSidebar()" class="btn btn-secondary !py-1 !px-2 text-xs lg:hidden" title="Details">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- "Why didn't bot reply?" detail panel -->
                    <div x-show="showWhyNoReply && active.auto_reply && !active.auto_reply.enabled" x-cloak
                         class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-xs">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            <div class="flex-1">
                                <div class="font-semibold text-amber-900 mb-1">რატომ არ უპასუხა ბოტმა?</div>
                                <div class="text-amber-900 mb-2">
                                    Auto-reply blocked because:
                                    <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono" x-text="active.auto_reply.reason"></code>
                                </div>
                                <div class="text-amber-800 text-[11px]" x-html="whyNoReplyExplanation(active.auto_reply.reason)"></div>
                                <template x-if="active.auto_reply.last_log">
                                    <div class="mt-2 pt-2 border-t border-amber-200 text-amber-800 text-[11px]">
                                        Last auto-reply event:
                                        <code class="font-mono" x-text="active.auto_reply.last_log.action"></code>
                                        <span x-text="active.auto_reply.last_log.ts"></span>
                                        <template x-if="active.auto_reply.last_log.reason">
                                            <span>· reason=<code x-text="active.auto_reply.last_log.reason"></code></span>
                                        </template>
                                        <template x-if="active.auto_reply.last_log.error">
                                            <span class="block">error: <code x-text="active.auto_reply.last_log.error"></code></span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="active.auto_reply.last_decision">
                                    <div class="mt-2 pt-2 border-t border-amber-200 text-amber-800 text-[11px]">
                                        <div class="font-semibold text-amber-900 mb-1">AI Decision (last):</div>
                                        <div>
                                            action=<code x-text="active.auto_reply.last_decision.action"></code>
                                            · <span x-text="active.auto_reply.last_decision.ts"></span>
                                        </div>
                                        <template x-if="active.auto_reply.last_decision.meta?.reason">
                                            <div>reason: <code class="font-mono" x-text="active.auto_reply.last_decision.meta.reason"></code></div>
                                        </template>
                                        <template x-if="active.auto_reply.last_decision.meta?.intent">
                                            <div>intent: <code x-text="active.auto_reply.last_decision.meta.intent"></code></div>
                                        </template>
                                        <template x-if="active.auto_reply.last_decision.meta?.query">
                                            <div>query: <code x-text="active.auto_reply.last_decision.meta.query"></code></div>
                                        </template>
                                        <template x-if="active.auto_reply.last_decision.meta?.queries_tried?.length">
                                            <div>variants tried: <code class="text-[10px]" x-text="(active.auto_reply.last_decision.meta.queries_tried || []).join(' · ')"></code></div>
                                        </template>
                                        <template x-if="active.auto_reply.last_decision.meta?.customer_message">
                                            <div class="mt-1">
                                                <div class="text-amber-700">customer said:</div>
                                                <blockquote class="bg-amber-100 px-2 py-1 rounded italic" x-text="active.auto_reply.last_decision.meta.customer_message"></blockquote>
                                            </div>
                                        </template>
                                        <template x-if="active.auto_reply.last_decision.meta?.source">
                                            <div>source: <code x-text="active.auto_reply.last_decision.meta.source"></code></div>
                                        </template>
                                        <template x-if="typeof active.auto_reply.last_decision.meta?.product_count === 'number'">
                                            <div>result count: <strong x-text="active.auto_reply.last_decision.meta.product_count"></strong></div>
                                        </template>
                                        <template x-if="active.auto_reply.last_decision.meta?.product_ids?.length">
                                            <div>products considered: <code x-text="active.auto_reply.last_decision.meta.product_ids.join(', ')"></code></div>
                                        </template>
                                    </div>
                                </template>
                                <div class="mt-2 flex gap-2 flex-wrap">
                                    <button x-show="active.ai_paused" @click="release()" class="btn btn-primary !py-1 text-[10px]">Release (ჩართე AI)</button>
                                    <button x-show="active.escalated" @click="release()" class="btn btn-secondary !py-1 text-[10px]">Clear escalation</button>
                                    <button x-show="active.assigned" @click="setAssigned('')" class="btn btn-secondary !py-1 text-[10px]">Unassign</button>
                                </div>
                            </div>
                            <button @click="showWhyNoReply = false" class="text-amber-600 hover:text-amber-900">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50" x-ref="messageList">
                        <template x-for="m in messages" :key="m.id">
                            <div :class="m.direction === 'outbound' ? 'flex-row-reverse' : ''" class="flex gap-2">
                                <div :class="m.direction === 'outbound' ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200'"
                                     class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm">
                                    <div class="whitespace-pre-wrap break-words" x-text="m.body"></div>
                                    <div :class="m.direction === 'outbound' ? 'text-brand-200' : 'text-slate-400'"
                                         class="text-[10px] mt-1 flex items-center gap-1.5">
                                        <span x-text="formatTime(m.created_at)"></span>
                                        <span x-show="m.is_ai" class="opacity-75">· AI</span>
                                        <span x-show="m.author?.name" class="opacity-75" x-text="'· ' + m.author?.name"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Reply composer -->
                    <div class="border-t border-slate-200 p-3 bg-white">
                        <!-- AI suggestion banner -->
                        <div x-show="aiSuggestError" x-cloak class="mb-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5"
                             x-text="aiSuggestError"></div>

                        <textarea x-model="draft" @focus="pauseAutoRefresh()" @blur="resumeAutoRefresh()"
                                  rows="2"
                                  placeholder="ჩაწერე პასუხი..."
                                  class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm resize-none outline-none focus:border-brand-500"></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <div class="flex items-center gap-1">
                                <button @click="generateAiSuggestion()"
                                        :disabled="aiSuggesting"
                                        class="btn btn-secondary !py-1.5 text-xs"
                                        title="AI suggestion (will not auto-send)">
                                    <svg x-show="!aiSuggesting" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.091 3.091z"/></svg>
                                    <svg x-show="aiSuggesting" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
                                    <span x-text="aiSuggesting ? 'ვამზადებთ…' : 'AI Suggestion'"></span>
                                </button>
                                <button x-show="draft" @click="draft = ''" x-cloak class="btn btn-secondary !py-1.5 text-xs">გასუფთავება</button>
                            </div>
                            <button @click="reply()"
                                    :disabled="sending || !draft.trim()"
                                    class="btn btn-primary !py-1.5 text-xs">
                                <svg x-show="!sending" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5"/></svg>
                                <svg x-show="sending" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
                                <span x-text="sending ? 'იგზავნება…' : 'გაგზავნა'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <!-- ============= RIGHT: Customer + status + notes + products ============= -->
        <aside class="w-80 shrink-0 border-l border-slate-200 overflow-y-auto bg-white flex flex-col"
               x-show="active && sidebarOpen"
               x-cloak
               :class="!active && 'hidden'">

            <!-- Tab switcher -->
            <div class="flex border-b border-slate-200 bg-slate-50 sticky top-0 z-10">
                <button @click="rightTab='info'"
                        :class="rightTab==='info' ? 'bg-white text-brand-700 border-b-2 border-brand-600' : 'text-slate-600 hover:bg-white'"
                        class="flex-1 py-2.5 text-xs font-medium">👤 Info</button>
                <button @click="rightTab='products'; if (!productsLoaded) searchProducts()"
                        :class="rightTab==='products' ? 'bg-white text-brand-700 border-b-2 border-brand-600' : 'text-slate-600 hover:bg-white'"
                        class="flex-1 py-2.5 text-xs font-medium">📦 Products</button>
            </div>

            <!-- PRODUCTS TAB -->
            <div x-show="rightTab==='products'" x-cloak class="flex-1 overflow-y-auto p-4 space-y-3">
                <div class="flex gap-2">
                    <input x-model="productQuery" @keyup.enter="searchProducts()"
                           placeholder="ძიება (e.g. iphone case)…"
                           class="flex-1 px-2.5 py-1.5 text-sm rounded-md border border-slate-200 outline-none focus:border-brand-500">
                    <button @click="searchProducts()" :disabled="productSearching" class="btn btn-secondary !py-1.5 text-xs">↻</button>
                </div>

                <button @click="aiRecommend()" :disabled="aiRecommending"
                        class="btn btn-primary w-full justify-center text-xs">
                    <svg x-show="!aiRecommending" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.091 3.091z"/></svg>
                    <svg x-show="aiRecommending" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
                    <span x-text="aiRecommending ? 'AI ვამზადებთ…' : 'AI Recommend'"></span>
                </button>

                <!-- AI draft text + source badge -->
                <div x-show="aiDraftText" x-cloak class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs">
                    <div class="font-semibold text-amber-900 mb-1 flex items-center gap-1.5">
                        <span>AI შემოთავაზება</span>
                        <span x-show="aiSource" :class="sourceBadgeClass(aiSource)" class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded font-normal" x-text="sourceLabel(aiSource)"></span>
                    </div>
                    <div class="whitespace-pre-wrap text-amber-900" x-text="aiDraftText"></div>
                    <div class="mt-2 flex gap-2">
                        <button @click="draft = aiDraftText; rightTab='info'; $root.toast('Draft → reply box', 'success')"
                                class="text-amber-900 underline hover:no-underline">→ Reply-ში გადატანა</button>
                        <button @click="aiDraftText = ''; aiSource = ''" class="text-amber-700 hover:underline ml-auto">დახურვა</button>
                    </div>
                </div>

                <!-- WC connection status badge -->
                <div x-show="productsLoaded || productSearching" x-cloak class="text-[10px] text-slate-500 flex items-center justify-between">
                    <span>
                        <span :class="wcStatusBadgeClass(productStatus)" class="inline-flex items-center px-1.5 py-0.5 rounded font-medium" x-text="'WC: ' + (productStatus || '...')"></span>
                        <span x-show="productLastQuery" x-cloak class="ml-1.5" x-text="'“' + productLastQuery + '” · ' + products.length"></span>
                    </span>
                    <span x-show="productQueriesTried && productQueriesTried.length > 1" :title="productQueriesTried.join(' · ')" class="cursor-help text-slate-400">×<span x-text="productQueriesTried.length"></span> tried</span>
                </div>

                <!-- Loading -->
                <div x-show="productSearching" x-cloak class="text-center py-6 text-xs text-slate-500">
                    <div class="w-5 h-5 mx-auto mb-2 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></div>
                    ვტვირთავთ…
                </div>

                <!-- Empty -->
                <div x-show="!productSearching && products.length === 0 && productsLoaded" x-cloak class="text-center py-6 text-xs text-slate-500">
                    <span x-show="productStatus === 'auth_failed'" class="text-red-600 block mb-1 font-medium">⚠ WooCommerce auth failed — შემოწმე keys /admin/integrations → WooCommerce</span>
                    <span x-show="productStatus === 'blocked'" class="text-red-600 block mb-1 font-medium">⚠ WC API blocked (Cloudflare/WAF) — admin საჭიროა</span>
                    <span x-show="productStatus === 'error' && productError" class="text-red-600 block mb-1" x-text="productError"></span>
                    <span x-show="productStatus === 'no_products' || (!productStatus)">WooCommerce-ში ამ მოთხოვნაზე პროდუქტი ვერ მოიძებნა.</span>
                </div>

                <!-- Product cards -->
                <template x-for="p in products" :key="p.id">
                    <div class="border border-slate-200 rounded-lg overflow-hidden hover:shadow-md transition">
                        <template x-if="p.image">
                            <img :src="p.image" :alt="p.name" class="w-full h-32 object-cover bg-slate-100">
                        </template>
                        <template x-if="!p.image">
                            <div class="w-full h-32 bg-slate-100 grid place-items-center text-slate-400 text-xs">no image</div>
                        </template>
                        <div class="p-2.5">
                            <div class="text-xs font-medium text-slate-900 line-clamp-2" x-text="p.name"></div>
                            <div class="flex items-center justify-between mt-1.5">
                                <span class="text-sm font-bold text-slate-900" x-text="p.price + '₾'"></span>
                                <span :class="p.stock_status === 'instock' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                      class="badge text-[10px]" x-text="p.stock_status === 'instock' ? 'მარაგშია' : 'არ არის'"></span>
                            </div>
                            <button @click="sendProduct(p)" :disabled="sendingProduct === p.id"
                                    class="btn btn-primary w-full justify-center text-xs mt-2 !py-1.5">
                                <svg x-show="sendingProduct !== p.id" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5"/></svg>
                                <svg x-show="sendingProduct === p.id" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
                                <span x-text="sendingProduct === p.id ? 'იგზავნება…' : 'ჩათში გაგზავნა'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- INFO TAB -->
            <template x-if="active && rightTab==='info'">
                <div class="p-4 space-y-5">
                    <!-- Customer card -->
                    <div>
                        <div class="flex items-center gap-3 mb-3">
                            <template x-if="active.customer?.profile_pic">
                                <img :src="active.customer.profile_pic" alt="" class="w-14 h-14 rounded-full object-cover">
                            </template>
                            <template x-if="!active.customer?.profile_pic">
                                <div :class="platformBg(active.platform)" class="w-14 h-14 rounded-full grid place-items-center text-white text-xl font-semibold"
                                     x-text="(active.customer?.display_name || active.customer?.platform_user_id || '?').charAt(0).toUpperCase()"></div>
                            </template>
                            <div class="flex-1 min-w-0">
                                <template x-if="!editingCustomer">
                                    <div>
                                        <div class="font-medium text-slate-900 truncate flex items-center gap-1">
                                            <span x-text="active.customer?.display_name || '(no name)'"></span>
                                            <button @click="startEditCustomer()" class="text-slate-400 hover:text-brand-600 text-xs" title="Edit">✎</button>
                                        </div>
                                        <div class="text-xs text-slate-500 truncate" x-text="active.customer?.platform_user_id"></div>
                                    </div>
                                </template>
                                <template x-if="editingCustomer">
                                    <div class="flex flex-col gap-1">
                                        <input x-model="customerDraft.display_name" placeholder="სახელი"
                                               class="text-sm px-2 py-1 rounded border border-slate-200 focus:border-brand-500 outline-none">
                                        <input x-model="customerDraft.phone" placeholder="ტელ. (optional)"
                                               class="text-xs px-2 py-1 rounded border border-slate-200 focus:border-brand-500 outline-none">
                                        <div class="flex gap-1 mt-0.5">
                                            <button @click="saveCustomer()" class="btn btn-primary !py-0.5 text-xs">შენახვა</button>
                                            <button @click="editingCustomer = false" class="btn btn-secondary !py-0.5 text-xs">გაუქმება</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <button @click="fetchProfile()" :disabled="fetchingProfile" class="btn btn-secondary w-full justify-center text-xs">
                            <svg x-show="!fetchingProfile" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7"/></svg>
                            <svg x-show="fetchingProfile" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20"/></svg>
                            <span x-text="fetchingProfile ? 'ვტვირთავთ…' : 'Profile-ის განახლება Meta-დან'"></span>
                        </button>
                    </div>

                    <!-- Status -->
                    <div>
                        <div class="text-xs font-semibold text-slate-700 mb-2">Lead status</div>
                        <select x-model="active.lead_status" @change="setStatus($event.target.value)"
                                class="w-full px-2.5 py-1.5 text-sm rounded-lg border border-slate-200 bg-white outline-none focus:border-brand-500">
                            <option value="new">new</option>
                            <option value="interested">interested</option>
                            <option value="product_recommended">product_recommended</option>
                            <option value="waiting">waiting_customer</option>
                            <option value="payment_pending">payment_pending</option>
                            <option value="order_created">order_created</option>
                            <option value="converted">converted</option>
                            <option value="escalated">escalated</option>
                            <option value="lost">lost</option>
                        </select>
                    </div>

                    <!-- Assigned -->
                    <div>
                        <div class="text-xs font-semibold text-slate-700 mb-2">თანამშრომელი</div>
                        <select :value="active.assigned?.id || ''" @change="setAssigned($event.target.value)"
                                class="w-full px-2.5 py-1.5 text-sm rounded-lg border border-slate-200 bg-white outline-none focus:border-brand-500">
                            <option value="">— unassigned —</option>
                            <template x-for="e in employees" :key="e.id">
                                <option :value="e.id" x-text="e.name + ' · ' + e.role"></option>
                            </template>
                        </select>
                    </div>

                    <!-- AI control -->
                    <div>
                        <div class="text-xs font-semibold text-slate-700 mb-2">AI control</div>
                        <div class="flex gap-2">
                            <button @click="takeover()" :disabled="active.ai_paused" class="btn btn-secondary !py-1.5 text-xs flex-1 justify-center">Takeover</button>
                            <button @click="release()" :disabled="!active.ai_paused && !active.escalated" class="btn btn-secondary !py-1.5 text-xs flex-1 justify-center">Release</button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <div class="text-xs font-semibold text-slate-700 mb-2 flex items-center justify-between">
                            <span>Internal notes</span>
                            <span class="text-slate-400" x-text="notes.length"></span>
                        </div>
                        <div class="space-y-2 mb-2">
                            <template x-for="n in notes" :key="n.id">
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2 text-xs">
                                    <div class="whitespace-pre-wrap" x-text="n.body"></div>
                                    <div class="flex items-center justify-between mt-1 text-[10px] text-slate-500">
                                        <span x-text="(n.employee?.name || 'unknown') + ' · ' + formatTime(n.created_at)"></span>
                                        <button @click="removeNote(n.id)" class="text-red-600 hover:underline">წაშლა</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <textarea x-model="noteDraft" placeholder="ახალი note…" rows="2"
                                  class="w-full px-2.5 py-1.5 text-sm rounded-lg border border-slate-200 resize-none outline-none focus:border-brand-500"></textarea>
                        <button @click="addNote()" :disabled="!noteDraft.trim()" class="btn btn-secondary w-full justify-center text-xs mt-1">Note-ის დამატება</button>
                    </div>

                    <!-- Danger zone -->
                    <div class="pt-3 border-t border-slate-100">
                        <button @click="flagSpam()" class="text-xs text-red-600 hover:underline">⚠ Spam-ად მონიშვნა</button>
                    </div>
                </div>
            </template>
        </aside>
    </div>
</div>

@push('scripts')
<script>
function inboxPage() {
    return {
        loading: false,
        autoRefreshing: false,
        autoRefreshHandle: null,
        autoRefreshPaused: false,
        conversations: [],
        filters: { platform: '', q: '', escalated: false, unanswered: false, unread: false },
        active: null,
        messages: [],
        notes: [],
        employees: [],
        draft: '',
        noteDraft: '',
        sending: false,
        aiSuggesting: false,
        aiSuggestError: '',
        fetchingProfile: false,
        sidebarOpen: true,
        rightTab: 'info',
        // Products panel state
        productQuery: '',
        products: [],
        productsLoaded: false,
        productSearching: false,
        productError: '',
        productStatus: '',
        productLastQuery: '',
        productQueriesTried: [],
        sendingProduct: null,
        aiRecommending: false,
        aiDraftText: '',
        aiSource: '',
        showWhyNoReply: false,
        editingCustomer: false,
        customerDraft: { display_name: '', phone: '' },

        boot() {
            this.load();
            this.loadEmployees();
            this.autoRefreshHandle = setInterval(() => {
                if (!this.autoRefreshPaused) this.tick();
            }, 15000);
            // Auto-open from URL hash
            if (window.location.hash) {
                const id = parseInt(window.location.hash.slice(1));
                if (id) setTimeout(() => this.open(id), 300);
            }
        },

        async tick() {
            this.autoRefreshing = true;
            try {
                await this.load(false);
                if (this.active) await this.refreshActive();
            } finally {
                setTimeout(() => this.autoRefreshing = false, 600);
            }
        },

        pauseAutoRefresh() { this.autoRefreshPaused = true; },
        resumeAutoRefresh() { this.autoRefreshPaused = false; },

        async load(showLoading = true) {
            if (showLoading) this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v && v !== '') params.set(k, v === true ? '1' : v);
                });
                const j = await shell.api('/inbox?' + params.toString());
                this.conversations = j.data ?? [];
                console.debug('[inbox] loaded', this.conversations.length, 'conversations');
            } catch (e) {
                console.error('[inbox] load failed', e);
            } finally { this.loading = false; }
        },

        async loadEmployees() {
            try {
                const shell = Alpine.$data(document.body);
                const j = await shell.api('/inbox/employees');
                this.employees = j.data ?? [];
            } catch (e) {}
        },

        toggleFilter(key) {
            this.filters[key] = !this.filters[key];
            this.load();
        },

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },

        async open(id) {
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + id);
                this.active = { ...(j.conversation ?? {}), customer: j.customer ?? null, assigned: j.assigned ?? null };
                this.messages = j.messages ?? [];
                this.notes = j.notes ?? [];
                window.location.hash = String(id);
                this.scrollMessagesToBottom();
                // Mark as read in the list immediately (server already did it)
                const i = this.conversations.findIndex(c => c.id === id);
                if (i >= 0) this.conversations[i].unread = false;
            } catch (e) {
                shell.toast('თემის გახსნა ჩავარდა', 'error');
            }
        },

        async refreshActive() {
            if (!this.active) return;
            try {
                const shell = Alpine.$data(document.body);
                const j = await shell.api('/inbox/' + this.active.id);
                this.active = { ...(j.conversation ?? {}), customer: j.customer ?? null, assigned: j.assigned ?? null };
                const oldLen = this.messages.length;
                this.messages = j.messages ?? [];
                this.notes = j.notes ?? [];
                if (this.messages.length > oldLen) this.scrollMessagesToBottom();
            } catch (e) {}
        },

        scrollMessagesToBottom() {
            this.$nextTick(() => {
                if (this.$refs.messageList) this.$refs.messageList.scrollTop = this.$refs.messageList.scrollHeight;
            });
        },

        async reply() {
            if (!this.draft.trim() || this.sending) return;
            this.sending = true;
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/reply', {
                    method: 'POST',
                    body: { body: this.draft.trim() },
                });
                if (j.ok && j.message) {
                    this.messages.push(j.message);
                    this.scrollMessagesToBottom();
                    this.draft = '';
                    shell.toast('✓ გაიგზავნა', 'success');
                    this.load(false);
                } else {
                    shell.toast('გაგზავნა ჩავარდა: ' + (j.error || 'unknown'), 'error');
                }
            } catch (e) {
                shell.toast('გაგზავნა ჩავარდა: ' + (e?.message || 'unknown'), 'error');
            } finally {
                this.sending = false;
            }
        },

        async generateAiSuggestion() {
            if (this.aiSuggesting) return;
            this.aiSuggesting = true;
            this.aiSuggestError = '';
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/ai-suggest', { method: 'POST', body: {} });
                if (j.ok && j.suggestion) {
                    this.draft = j.suggestion;
                    shell.toast('💡 AI suggestion მზადაა — გადახედე და გაგზავნე', 'success');
                } else {
                    this.aiSuggestError = j.error || 'unknown error';
                }
            } catch (e) {
                this.aiSuggestError = e?.message || 'unknown error';
            } finally {
                this.aiSuggesting = false;
            }
        },

        async setStatus(status) {
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/inbox/' + this.active.id + '/status', { method: 'POST', body: { status } });
                shell.toast('✓ status: ' + status, 'success');
                this.load(false);
            } catch (e) {}
        },

        async setAssigned(employeeId) {
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/assign', {
                    method: 'POST',
                    body: { employee_id: employeeId ? parseInt(employeeId) : null },
                });
                this.active.assigned = j.assigned;
                shell.toast('✓ მინიჭდა', 'success');
                this.load(false);
            } catch (e) {}
        },

        async markUnread() {
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/inbox/' + this.active.id + '/unread', { method: 'POST', body: {} });
                shell.toast('Mark as unread', 'success');
                this.active = null;
                this.load(false);
            } catch (e) {}
        },

        async takeover() {
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/inbox/' + this.active.id + '/takeover', { method: 'POST', body: {} });
                shell.toast('Takeover — AI გათიშულია', 'success');
                this.refreshActive();
                this.load(false);
            } catch (e) {}
        },

        async release() {
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/inbox/' + this.active.id + '/release', { method: 'POST', body: {} });
                shell.toast('Released — AI კვლავ აქტიური', 'success');
                this.refreshActive();
                this.load(false);
            } catch (e) {}
        },

        async addNote() {
            if (!this.noteDraft.trim()) return;
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/notes', {
                    method: 'POST',
                    body: { body: this.noteDraft.trim() },
                });
                this.notes.unshift(j.note);
                this.noteDraft = '';
                shell.toast('✓ note დაემატა', 'success');
                this.load(false);
            } catch (e) {}
        },

        async removeNote(noteId) {
            if (! window.confirm('წაიშალოს note?')) return;
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/inbox/' + this.active.id + '/notes/' + noteId, { method: 'DELETE' });
                this.notes = this.notes.filter(n => n.id !== noteId);
                this.load(false);
            } catch (e) {}
        },

        startEditCustomer() {
            this.customerDraft = {
                display_name: this.active?.customer?.display_name || '',
                phone: this.active?.customer?.phone || '',
            };
            this.editingCustomer = true;
        },

        async saveCustomer() {
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/customer', {
                    method: 'POST',
                    body: { display_name: this.customerDraft.display_name, phone: this.customerDraft.phone },
                });
                this.active.customer = { ...this.active.customer, ...j.customer };
                this.editingCustomer = false;
                shell.toast('✓ შენახულია', 'success');
                this.load(false);
            } catch (e) {
                shell.toast('შენახვა ჩავარდა', 'error');
            }
        },

        async fetchProfile() {
            this.fetchingProfile = true;
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/fetch-profile', { method: 'POST', body: {} });
                if (j.ok) {
                    this.active.customer = j.customer;
                    shell.toast('✓ profile განახლდა', 'success');
                    this.load(false);
                } else {
                    shell.toast('Profile fetch ჩავარდა: ' + (j.error || 'permission denied'), 'warn');
                }
            } catch (e) {
                shell.toast('Profile fetch ჩავარდა: ' + (e?.message || 'unknown'), 'error');
            } finally {
                this.fetchingProfile = false;
            }
        },

        async searchProducts() {
            this.productSearching = true;
            this.productError = '';
            const shell = Alpine.$data(document.body);
            this.productLastQuery = this.productQuery || '';
            try {
                const q = encodeURIComponent(this.productQuery || '');
                const j = await shell.api('/products/search?q=' + q + '&limit=20');
                this.products = j.items || [];
                this.productStatus = j.status || (j.ok ? 'connected' : 'error');
                this.productQueriesTried = j.queries_tried || [];
                this.productsLoaded = true;
                if (! j.ok && j.error) {
                    this.productError = j.error;
                }
            } catch (e) {
                this.productError = e?.message || 'unknown error';
                this.products = [];
                this.productStatus = 'error';
            } finally {
                this.productSearching = false;
            }
        },

        wcStatusBadgeClass(s) {
            return ({
                connected: 'bg-emerald-100 text-emerald-700',
                no_products: 'bg-slate-200 text-slate-700',
                auth_failed: 'bg-red-100 text-red-700',
                blocked: 'bg-red-100 text-red-700',
                error: 'bg-red-100 text-red-700',
            })[s] || 'bg-slate-100 text-slate-500';
        },

        async aiRecommend() {
            if (!this.active) return;
            this.aiRecommending = true;
            this.aiDraftText = '';
            this.aiSource = '';
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/recommend', { method: 'POST', body: {} });
                if (j.ok) {
                    this.products = j.products || [];
                    this.productsLoaded = true;
                    this.productQuery = j.query || '';
                    this.aiDraftText = j.draft_text || '';
                    this.aiSource = j.source || '';
                    const count = j.products?.length || 0;
                    if (j.source === 'wc_grounded') {
                        shell.toast('✓ AI გვაჩვენებს ' + count + ' WC პროდუქტს', 'success');
                    } else if (j.source === 'no_products_fallback') {
                        shell.toast('⚠ WC-ში არ ვიპოვე — fallback draft', 'warn');
                    } else if (j.source === 'validator_rejected') {
                        shell.toast('⚠ AI გაიქცა, fallback გადავცე', 'warn');
                    } else {
                        shell.toast('AI შემოთავაზება მზადაა', 'success');
                    }
                } else {
                    shell.toast('AI recommend ჩავარდა: ' + (j.error || 'unknown'), 'error');
                }
            } catch (e) {
                shell.toast('AI recommend ჩავარდა: ' + (e?.message || 'unknown'), 'error');
            } finally {
                this.aiRecommending = false;
            }
        },

        whyNoReplyExplanation(reason) {
            const map = {
                'channel_disabled': 'Auto-reply ცარიელია ამ channel-ისთვის. შემოწმე /admin/integrations → 🤖 Auto Reply, რომ AUTO_REPLY_ENABLED და AUTO_REPLY_MESSENGER_ENABLED ორივე "true" იყოს.',
                'outside_business_hours': 'სამუშაო საათების მიღმაა. AUTO_REPLY_BUSINESS_HOURS_ONLY=false-ად დადე ან საათები გადააწერე.',
                'ai_paused': 'ვინმემ ხელით აიყვანა საუბარი (Takeover). დააჭირე <strong>Release</strong> ქვემოთ, რომ AI-ი დაუბრუნდე.',
                'escalated': 'საუბარი escalated არის (ჩვეულებრივ AI თვითონ აყენებს, როცა შემოწმდება უნაცები სიტყვა / catalog ცარიელია). Release-ი ცარიელად დააფიქსირებს ფლაგებს.',
                'assigned': 'საუბარი მონიჭებულია employee-ზე. Unassign-ით ჩამოაცილე ან გადააფერე.',
                'customer_blocked': 'კლიენტი მონიშნულია spam-ად ან blocked-ად — auto-reply არ მუშაობს ასეთებზე.',
            };
            return map[reason] || 'Unknown reason. იხილე storage/logs/auto-reply.log';
        },

        sourceLabel(s) {
            return ({
                wc_grounded: 'WC',
                no_products_fallback: 'fallback',
                validator_rejected: 'rejected',
                general: 'general',
                extract_failed: 'error',
            })[s] || s;
        },

        sourceBadgeClass(s) {
            return ({
                wc_grounded: 'bg-emerald-200 text-emerald-900',
                no_products_fallback: 'bg-slate-200 text-slate-700',
                validator_rejected: 'bg-red-200 text-red-900',
                general: 'bg-slate-200 text-slate-700',
                extract_failed: 'bg-red-200 text-red-900',
            })[s] || 'bg-slate-200 text-slate-700';
        },

        async sendProduct(p) {
            if (this.sendingProduct) return;
            this.sendingProduct = p.id;
            const shell = Alpine.$data(document.body);
            try {
                const j = await shell.api('/inbox/' + this.active.id + '/send-product', {
                    method: 'POST',
                    body: { product_id: p.id },
                });
                if (j.ok && j.message) {
                    this.messages.push(j.message);
                    this.scrollMessagesToBottom();
                    shell.toast('✓ პროდუქტი გაიგზავნა', 'success');
                    this.load(false);
                } else {
                    shell.toast('გაგზავნა ჩავარდა: ' + (j.error || 'unknown'), 'error');
                }
            } catch (e) {
                shell.toast('გაგზავნა ჩავარდა: ' + (e?.message || 'unknown'), 'error');
            } finally {
                this.sendingProduct = null;
            }
        },

        async flagSpam() {
            if (!window.confirm('Spam-ად მონიშნე? კონვერსაცია დაიხურება და AI გათიშდება.')) return;
            const shell = Alpine.$data(document.body);
            try {
                await shell.api('/inbox/' + this.active.id + '/spam', { method: 'POST', body: {} });
                shell.toast('Marked as spam', 'success');
                this.active = null;
                this.load(false);
            } catch (e) {}
        },

        customerLabel() {
            if (!this.active) return '';
            return this.active.customer?.display_name
                || this.active.customer?.platform_user_id
                || this.active.thread_id
                || '—';
        },

        platformColor(p) {
            return { whatsapp:'bg-emerald-500', messenger:'bg-blue-500', instagram:'bg-pink-500', facebook:'bg-blue-600' }[p] || 'bg-slate-400';
        },
        platformBg(p) {
            return { whatsapp:'bg-emerald-500', messenger:'bg-blue-500', instagram:'bg-pink-500', facebook:'bg-blue-600' }[p] || 'bg-slate-500';
        },

        formatTime(t) {
            if (!t) return '';
            const d = new Date(t);
            const now = new Date();
            const diff = (now - d) / 1000;
            if (diff < 60) return 'now';
            if (diff < 3600) return Math.floor(diff/60) + 'm';
            if (diff < 86400) return Math.floor(diff/3600) + 'h';
            if (diff < 604800) return Math.floor(diff/86400) + 'd';
            return d.toLocaleDateString();
        },
    };
}
</script>
@endpush
@endsection

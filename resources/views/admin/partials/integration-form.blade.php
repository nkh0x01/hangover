<div class="card p-5">
    <h4 class="font-semibold text-slate-900 mb-4">Configuration</h4>

    <div class="space-y-4">
        <template x-for="(item, key) in all['{{ $group }}'] || {}" :key="key">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-medium text-slate-600 flex items-center gap-2">
                        <span x-text="keyLabel(key)"></span>
                        <span x-show="item.is_secret" class="text-[10px] uppercase tracking-wider text-amber-600">secret</span>
                        <template x-if="item.is_set">
                            <span :class="item.source === 'db' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                  class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded"
                                  x-text="item.source === 'db' ? 'saved' : 'env'"></span>
                        </template>
                        <template x-if="!item.is_set">
                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-400">not set</span>
                        </template>
                    </label>
                    <button x-show="item.is_set && item.source === 'db'"
                            @click.prevent="remove('{{ $group }}', key)"
                            type="button"
                            class="text-[11px] text-red-600 hover:underline">
                        Remove
                    </button>
                </div>

                <!-- Non-secret field: editable, pre-filled with saved value -->
                <template x-if="!item.is_secret">
                    <input :value="forms['{{ $group }}']?.[key] ?? ''"
                           @input="ensureForm('{{ $group }}', key); forms['{{ $group }}'][key] = $event.target.value"
                           class="field-input"
                           :placeholder="keyLabel(key)">
                </template>

                <!-- Secret field: empty input + masked-value display below -->
                <template x-if="item.is_secret">
                    <div>
                        <input :value="forms['{{ $group }}']?.[key] ?? ''"
                               @input="ensureForm('{{ $group }}', key); forms['{{ $group }}'][key] = $event.target.value"
                               type="password"
                               autocomplete="off"
                               class="field-input font-mono"
                               :placeholder="item.is_set ? '••• keep current · type to replace' : 'არ არის შენახული'">
                        <p x-show="item.is_set" class="text-[11px] text-slate-400 mt-1">
                            Current: <span class="font-mono" x-text="item.masked"></span>
                        </p>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-100">
        <button @click.prevent="test('{{ $group }}')"
                :disabled="testing['{{ $group }}']"
                class="btn btn-secondary">
            <svg x-show="testing['{{ $group }}']" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20" fill="none"/></svg>
            <svg x-show="!testing['{{ $group }}']" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63"/></svg>
            <span x-text="testing['{{ $group }}'] ? 'ვამოწმებთ…' : 'Test connection'"></span>
        </button>
        <button @click.prevent="save('{{ $group }}')"
                :disabled="saving['{{ $group }}']"
                class="btn btn-primary">
            <svg x-show="saving['{{ $group }}']" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="20" fill="none"/></svg>
            <svg x-show="!saving['{{ $group }}']" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            <span x-text="saving['{{ $group }}'] ? 'ვინახავთ…' : 'შენახვა'"></span>
        </button>
    </div>
</div>

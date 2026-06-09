@extends('admin.layout')

@section('title', 'Orders & Leads')
@section('subtitle', 'ბოტის მიერ შექმნილი ორდერები და გადახდის ლინკები')

@section('content')
<div x-data="ordersPage()" x-init="load()">

    <div class="card mb-4 p-3 flex items-center gap-2">
        <select x-model="status" @change="load()" class="px-3 py-1.5 rounded-md border border-slate-200 text-sm">
            <option value="">ყველა სტატუსი</option>
            <option value="pending">pending</option>
            <option value="confirmed">confirmed</option>
            <option value="paid">paid</option>
            <option value="cancelled">cancelled</option>
        </select>
        <button @click="load()" class="btn btn-secondary !py-1.5 text-xs">Refresh</button>
    </div>

    <div x-show="loading" x-cloak class="card p-10 text-center text-sm text-slate-500">
        იტვირთება…
    </div>
    <div x-show="!loading && orders.length === 0" x-cloak class="card p-10 text-center text-sm text-slate-500">
        ჯერ ორდერი არ არის.
    </div>
    <div x-show="!loading && orders.length > 0" x-cloak class="card overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="text-left px-4 py-2">#</th>
                    <th class="text-left px-4 py-2">Customer</th>
                    <th class="text-left px-4 py-2">Items</th>
                    <th class="text-left px-4 py-2">Total</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <template x-for="o in orders" :key="o.id">
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs" x-text="o.id"></td>
                        <td class="px-4 py-3" x-text="o.customer?.name || o.customer?.phone || '—'"></td>
                        <td class="px-4 py-3 text-xs text-slate-500" x-text="(o.items?.length || 0) + ' item(s)'"></td>
                        <td class="px-4 py-3 font-medium" x-text="o.total + ' ₾'"></td>
                        <td class="px-4 py-3"><span class="badge bg-slate-100 text-slate-700" x-text="o.status"></span></td>
                        <td class="px-4 py-3 text-xs text-slate-500" x-text="o.created_at"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
function ordersPage() {
    return {
        loading: true,
        orders: [],
        status: '',
        async load() {
            this.loading = true;
            try {
                const shell = Alpine.$data(document.body);
                const q = this.status ? '?status=' + this.status : '';
                const j = await shell.api('/orders' + q);
                this.orders = j.orders ?? j ?? [];
            } catch (e) { this.orders = []; }
            finally { this.loading = false; }
        },
    };
}
</script>
@endpush
@endsection

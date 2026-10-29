<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Orderhistorie</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('orders.index') }}" class="px-3 py-1 rounded {{ $status ? 'bg-slate-800 text-slate-400' : 'bg-emerald-600 text-white' }}">Alle</a>
                @foreach (\App\Enums\OrderStatus::cases() as $case)
                    <a href="{{ route('orders.index', ['status' => $case->value]) }}" class="px-3 py-1 rounded {{ $status === $case ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400' }}">{{ $case->label() }}</a>
                @endforeach
            </div>

            <x-card>
                <x-orders-table :orders="$orders" cancellable />
                <div class="mt-4">{{ $orders->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>

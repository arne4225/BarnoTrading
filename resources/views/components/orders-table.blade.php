@props(['orders', 'cancellable' => false])

<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="py-2 pe-4">Symbool</th><th class="pe-4">Zijde</th><th class="pe-4">Type</th>
                <th class="pe-4 text-right">Aantal</th><th class="pe-4 text-right">Prijzen</th><th class="pe-4">Status</th>
                <th class="pe-4">Tijd (ET)</th><th></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
            @forelse ($orders as $order)
                <tr class="text-slate-300">
                    <td class="py-2 pe-4 font-medium"><a class="text-emerald-400 hover:underline" href="{{ route('stocks.show', $order->symbol) }}">{{ $order->symbol }}</a></td>
                    <td class="pe-4 {{ $order->side->value === 'buy' ? 'text-emerald-400' : 'text-red-400' }}">
                        {{ $order->is_short ? 'Short' : $order->side->label() }}
                    </td>
                    <td class="pe-4">
                        {{ $order->type->label() }}
                        @if ($order->bracket_role) <span class="text-xs text-slate-500">({{ $order->bracket_role->label() }})</span> @endif
                    </td>
                    <td class="pe-4 text-right tabular-nums">{{ $order->quantity }}</td>
                    <td class="pe-4 text-right tabular-nums text-xs">
                        @if ($order->limit_price_cents) limiet <x-money :cents="$order->limit_price_cents" /><br> @endif
                        @if ($order->stop_price_cents) stop <x-money :cents="$order->stop_price_cents" /><br> @endif
                        @if ($order->trail_amount_cents) trail <x-money :cents="$order->trail_amount_cents" /><br> @endif
                        @if ($order->trail_percent_bps) trail {{ number_format($order->trail_percent_bps / 100, 2) }}%<br> @endif
                        @if ($order->fill_price_cents) gevuld <x-money :cents="$order->fill_price_cents" /> @endif
                    </td>
                    <td class="pe-4">
                        <x-order-status-badge :status="$order->status" />
                        @if ($order->reject_reason) <div class="text-xs text-red-400 max-w-xs">{{ $order->reject_reason }}</div> @endif
                    </td>
                    <td class="pe-4 whitespace-nowrap text-xs text-slate-500">{{ $order->created_at->timezone(config('trading.timezone'))->format('d M H:i:s') }}</td>
                    <td>
                        @if ($cancellable && $order->isActive())
                            <form method="POST" action="{{ route('orders.destroy', $order) }}">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-300">Annuleer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-slate-500">Geen orders.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

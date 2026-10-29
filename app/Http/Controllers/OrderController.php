<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\MarketData\MarketDataService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        return view('orders.index', [
            'orders' => $request->user()->orders()
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orders, MarketDataService $marketData): RedirectResponse
    {
        try {
            $order = $orders->place($request->user(), $request->toData());
        } catch (InvalidOrderException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = match ($order->status) {
            OrderStatus::Filled => "Order uitgevoerd: {$order->quantity} {$order->symbol}.",
            OrderStatus::Rejected => 'Order afgewezen: '.$order->reject_reason,
            default => $marketData->isMarketOpen()
                ? 'Order geplaatst en wacht op de voorwaarden.'
                : 'Beurs is gesloten: de order blijft open tot de opening.',
        };

        return back()->with($order->status === OrderStatus::Rejected ? 'error' : 'status', $message);
    }

    public function destroy(Order $order, OrderService $orders): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $orders->cancel($order);

        return back()->with('status', 'Order geannuleerd.');
    }
}

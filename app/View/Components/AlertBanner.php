<?php

namespace App\View\Components;

use App\Models\PriceAlert;
use App\Services\AlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

/**
 * Controleert bij elke paginaweergave de actieve alerts van de gebruiker en toont
 * de nog niet weggeklikte meldingen.
 */
class AlertBanner extends Component
{
    /** @var Collection<int, PriceAlert> */
    public Collection $alerts;

    public function __construct(AlertService $alertService)
    {
        $user = auth()->user();

        if ($user) {
            $alertService->check($user);
            $this->alerts = $alertService->unacknowledged($user);
        } else {
            $this->alerts = new Collection;
        }
    }

    public function render(): View
    {
        return view('components.alert-banner');
    }
}

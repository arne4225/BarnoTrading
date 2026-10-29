<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Trade journal</h2>
            <a href="{{ route('journal.export') }}" class="text-sm text-slate-300 hover:text-white">⬇ Export (CSV)</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <datalist id="known-tags">
                @foreach (collect(['breakout', 'pullback', 'reversal', 'news', 'FOMO', 'revenge', 'plan'])->map(fn ($t) => mb_strtolower($t))->merge($knownTags)->unique() as $tag)
                    <option value="{{ $tag }}">
                @endforeach
            </datalist>

            @forelse ($trades as $trade)
                <x-card x-data="{ open: false }">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-slate-300">
                        <span class="font-semibold text-slate-100">{{ $trade->symbol }}</span>
                        <span class="{{ $trade->isShort() ? 'text-red-400' : 'text-emerald-400' }}">{{ $trade->isShort() ? 'Short' : 'Long' }} {{ $trade->peak_quantity }}</span>
                        <span>in <x-money :cents="$trade->averageEntryCents()" />
                            @if ($trade->isClosed()) → uit <x-money :cents="$trade->averageExitCents()" /> @endif</span>
                        @if ($trade->isClosed())
                            <span><x-money :cents="$trade->realized_pnl_cents" signed colored /></span>
                        @else
                            <span class="text-sky-300 text-xs">open</span>
                        @endif
                        <span class="text-xs text-slate-500">{{ $trade->opened_at->timezone(config('trading.timezone'))->format('d M H:i') }} ET</span>
                        @if ($trade->self_rating) <span class="text-amber-400" title="Zelfbeoordeling">{{ str_repeat('★', $trade->self_rating) }}{{ str_repeat('☆', 5 - $trade->self_rating) }}</span> @endif
                        @foreach ($trade->tags ?? [] as $tag) <span class="rounded bg-slate-800 px-2 py-0.5 text-xs text-slate-300">{{ $tag }}</span> @endforeach
                        <button type="button" @click="open = ! open" class="ms-auto text-xs text-emerald-400 hover:underline" x-text="open ? 'Sluiten' : 'Bewerken'"></button>
                    </div>
                    @if ($trade->entry_reason || $trade->note)
                        <div class="mt-2 text-sm text-slate-400 space-y-1" x-show="! open">
                            @if ($trade->entry_reason) <p><span class="text-slate-500">Reden:</span> {{ $trade->entry_reason }}</p> @endif
                            @if ($trade->note) <p><span class="text-slate-500">Notitie:</span> {{ $trade->note }}</p> @endif
                        </div>
                    @endif

                    <form method="POST" action="{{ route('journal.update', $trade) }}" x-show="open" x-cloak class="mt-4 grid md:grid-cols-2 gap-4">
                        @csrf @method('PATCH')
                        <div>
                            <x-input-label :for="'reason'.$trade->id" value="Reden van instap" />
                            <textarea id="reason{{ $trade->id }}" name="entry_reason" rows="3" class="mt-1 block w-full rounded-md border-slate-700 bg-slate-800 text-slate-100">{{ $trade->entry_reason }}</textarea>
                        </div>
                        <div>
                            <x-input-label :for="'note'.$trade->id" value="Notitie / evaluatie" />
                            <textarea id="note{{ $trade->id }}" name="note" rows="3" class="mt-1 block w-full rounded-md border-slate-700 bg-slate-800 text-slate-100">{{ $trade->note }}</textarea>
                        </div>
                        <div>
                            <x-input-label :for="'tags'.$trade->id" value="Tags (komma-gescheiden)" />
                            <x-text-input :id="'tags'.$trade->id" name="tags" list="known-tags" class="mt-1 block w-full" :value="implode(', ', $trade->tags ?? [])" placeholder="breakout, FOMO" />
                        </div>
                        <div>
                            <x-input-label :for="'rating'.$trade->id" value="Zelfbeoordeling uitvoering (1–5)" />
                            <select id="rating{{ $trade->id }}" name="self_rating" class="mt-1 block w-full rounded-md border-slate-700 bg-slate-800 text-slate-100">
                                <option value="">—</option>
                                @foreach (range(1, 5) as $n) <option value="{{ $n }}" @selected($trade->self_rating === $n)>{{ $n }}</option> @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2"><x-primary-button>Opslaan</x-primary-button></div>
                    </form>
                </x-card>
            @empty
                <x-card><p class="py-6 text-center text-slate-500">Nog geen trades. Zodra je een positie opent verschijnt die hier.</p></x-card>
            @endforelse

            {{ $trades->links() }}
        </div>
    </div>
</x-app-layout>

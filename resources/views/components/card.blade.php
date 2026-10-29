@props(['title' => null])

<section {{ $attributes->class(['bg-slate-900 border border-slate-800 rounded-lg p-5']) }}>
    @if ($title)
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-3">{{ $title }}</h3>
    @endif
    {{ $slot }}
</section>

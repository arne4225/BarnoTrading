@props(['status'])

@php
    $class = match ($status->value) {
        'filled' => 'bg-emerald-900/50 text-emerald-300',
        'pending' => 'bg-sky-900/50 text-sky-300',
        'held' => 'bg-slate-800 text-slate-400',
        'rejected' => 'bg-red-900/50 text-red-300',
        default => 'bg-slate-800 text-slate-400',
    };
@endphp

<span {{ $attributes->class(['inline-block rounded px-2 py-0.5 text-xs font-medium', $class]) }}>{{ $status->label() }}</span>

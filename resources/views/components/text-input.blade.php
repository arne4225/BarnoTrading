@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-700 bg-slate-800 text-slate-100 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm']) }}>

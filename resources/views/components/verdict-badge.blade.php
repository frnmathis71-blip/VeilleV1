@props(['verdict'])
@php($relevant = $verdict === \App\Enums\RelevanceVerdict::Relevant)
<span {{ $attributes->class(['rounded-full px-2.5 py-1 text-sm font-medium', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $relevant, 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $relevant]) }}>{{ $relevant ? 'Pertinent' : 'Impertinent' }}</span>

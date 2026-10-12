@props(['article'])
@php
    $score = $article->analysis?->score;
    // Inline colours: the tile stays readable even before the CSS is rebuilt.
    $scoreStyle = match (true) {
        $score === null => 'background:#f4f4f5;color:#3f3f46',
        $score >= 70 => 'background:#059669;color:#fff',
        $score >= 40 => 'background:#d97706;color:#fff',
        default => 'background:#3f3f46;color:#fff',
    };
    // Illustration used when the feed gives no image, or when the publisher refuses to serve it.
    $category = $article->categories->first();
    $palettes = [
        ['#2563eb', '#4338ca'], ['#0891b2', '#1d4ed8'], ['#7c3aed', '#a21caf'], ['#059669', '#0f766e'],
        ['#f59e0b', '#ea580c'], ['#e11d48', '#be185d'], ['#0ea5e9', '#0e7490'], ['#475569', '#27272a'],
    ];
    $palette = $palettes[crc32($category->slug ?? (string) $article->rss_source_id) % count($palettes)];
    $label = $category->name ?? $article->source?->name ?? 'Veille';
    $url = route('articles.show', $article);
    // Personal review of the current user, when the list loaded it.
    $review = $article->relationLoaded('reviews') ? $article->reviews->firstWhere('user_id', auth()->id()) : null;
    $when = fn ($date) => $date->timezone('Europe/Paris')->format('d/m/Y \à H:i');
@endphp
<article {{ $attributes->class('group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-700') }}>
    <a href="{{ $url }}" wire:navigate class="relative block overflow-hidden bg-zinc-100 dark:bg-zinc-800" style="aspect-ratio:16/9" tabindex="-1" aria-hidden="true"
       x-data="{ broken: {{ $article->image_url ? 'false' : 'true' }} }">
        @if ($article->image_url)
            <img src="{{ $article->image_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-show="! broken" x-on:error="broken = true"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105" style="width:100%;height:100%;object-fit:cover" />
        @endif
        <div x-show="broken" @if ($article->image_url) x-cloak @endif class="flex h-full w-full flex-col justify-end p-4" style="height:100%;background:linear-gradient(135deg, {{ $palette[0] }}, {{ $palette[1] }})">
            <span class="text-4xl font-semibold" style="color:rgba(255,255,255,.35)">{{ mb_strtoupper(mb_substr($article->source?->name ?? 'V', 0, 1)) }}</span>
            <span class="mt-1 line-clamp-1 text-xs font-medium uppercase tracking-wide" style="color:rgba(255,255,255,.9)">{{ $label }}</span>
        </div>
        <span class="absolute right-2 top-2 rounded-full px-2.5 py-1 text-sm font-semibold shadow-sm" style="position:absolute;top:.5rem;right:.5rem;{{ $scoreStyle }}" title="{{ $score === null ? 'Analyse en attente' : 'Note attribuée par l’analyse' }}">
            {{ $score === null ? 'En attente' : $score.'/100' }}
        </span>
    </a>
    <div class="flex flex-1 flex-col gap-3 p-4">
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $article->source?->name }} · {{ $article->published_at?->timezone('Europe/Paris')->format('d/m/Y') ?? 'Date inconnue' }}</p>
        <h2 class="line-clamp-3 font-semibold leading-snug" title="{{ $article->isTitleTranslated() ? 'Titre original : '.$article->title : $article->title }}">
            <a href="{{ $url }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">{{ $article->displayTitle() }}</a>
        </h2>
        <ul class="space-y-0.5 text-xs text-zinc-500 dark:text-zinc-400">
            <li>{{ $article->analysis?->analyzed_at ? 'Traité le '.$when($article->analysis->analyzed_at) : 'Analyse en attente' }}</li>
            @if ($review?->decision && $review->decided_at)
                <li class="font-medium {{ $review->decision->value === 'relevant' ? 'text-emerald-700 dark:text-emerald-400' : '' }}">{{ $review->decision->value === 'relevant' ? 'Ajouté à la bibliothèque' : 'Marqué non pertinent' }} le {{ $when($review->decided_at) }}</li>
            @endif
        </ul>
        <flux:button class="mt-auto w-full" size="sm" :href="$url" wire:navigate>Consulter</flux:button>
    </div>
</article>

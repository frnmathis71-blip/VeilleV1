<x-layouts::app title="Tableau de bord">
    <div class="mx-auto w-full max-w-7xl space-y-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-sm font-medium text-blue-600 dark:text-blue-400">VOTRE OBSERVATOIRE TECHNOLOGIQUE</p>
                <h1 class="text-3xl font-semibold tracking-tight">Tableau de bord</h1>
                <p class="mt-2 text-zinc-500 dark:text-zinc-400">Collecter, comprendre et construire votre veille.</p>
            </div>
            <span class="rounded-full border border-zinc-200 px-3 py-1.5 text-sm dark:border-zinc-700">{{ now()->timezone('Europe/Paris')->translatedFormat('d F Y') }}</span>
        </header>
        <section aria-label="Indicateurs de veille" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['Articles collectés' => $collected, 'Articles analysés' => $analyzed, 'Pertinents selon l’IA' => $stats['interesting'], 'Dans ma bibliothèque' => $relevant] as $label => $value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    <p class="mt-4 text-4xl font-semibold tracking-tight">{{ number_format($value, 0, ',', ' ') }}</p>
                    <div class="mt-4 h-1 w-10 rounded-full bg-blue-500"></div>
                </div>
            @endforeach
        </section>
        <section class="rounded-xl border border-zinc-200 bg-white p-6 md:p-8 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="max-w-2xl">
                <span class="inline-flex rounded-lg bg-blue-50 p-3 text-blue-600 dark:bg-blue-950 dark:text-blue-300"><flux:icon.book-open-text /></span>
                <h2 class="mt-5 text-xl font-semibold">{{ $collected === 0 ? 'Votre veille commence ici' : 'Votre espace de veille' }}</h2>
                <p class="mt-3 leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $collected === 0 ? 'Aucun article collecté pour le moment. Vos prochaines lectures et vos sélections personnelles apparaîtront ici.' : 'Les indicateurs reflètent les articles enregistrés et vos validations personnelles.' }}</p>
                <p class="mt-3 text-sm text-zinc-500">{{ $stats['today'] }} collectés aujourd’hui · {{ $stats['week'] }} cette semaine · {{ $stats['pending'] }} en attente d’analyse · {{ $stats['unread'] }} articles pertinents non lus · {{ $stats['irrelevant'] }} écartés par l’IA</p>
                <div class="mt-6 flex flex-wrap gap-2"><flux:button variant="primary" :href="route('articles.interesting')" wire:navigate>Voir les articles pertinents</flux:button>@can('manage-watch')<flux:button :href="route('history')" wire:navigate>Lancer une collecte</flux:button>@endcan</div>
            </div>
        </section>
        <section class="space-y-4">
            <h2 class="text-lg font-semibold">Derniers articles jugés pertinents</h2>
            <div class="tile-grid">
                @forelse($recent as $article)
                    <x-article-tile :article="$article" />
                @empty
                    <p class="panel text-zinc-500" style="grid-column:1/-1">Aucun article n’a encore été jugé pertinent.</p>
                @endforelse
            </div>
        </section>
        <section>
            <h2 class="text-lg font-semibold">Domaines de veille</h2>
            <p class="mt-1 text-sm text-zinc-500">Le référentiel de votre veille technologique.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($categories as $category)
                    <span class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">{{ $category->name }}</span>
                @empty
                    <p class="text-sm text-zinc-500">Aucune catégorie configurée.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>

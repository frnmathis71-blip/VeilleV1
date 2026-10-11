<x-layouts::app title="Statistiques">
    <div class="space-y-6">
        <header><h1 class="text-3xl font-semibold">Statistiques</h1><p class="mt-2 text-zinc-500">Comprendre votre veille et la qualité des recommandations.</p></header>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach(['Articles collectés'=>$stats['collected'],'Analysés'=>$stats['analyzed'],'Pertinents (IA)'=>$stats['interesting'],'Impertinents (IA)'=>$stats['irrelevant'],'Validés par vous'=>$stats['relevant']] as $label=>$value)<div class="panel"><p class="text-sm text-zinc-500">{{ $label }}</p><p class="mt-3 text-3xl font-semibold">{{ $value }}</p></div>@endforeach
        </div>
        <section class="panel"><h2 class="text-xl font-semibold">Les 30 derniers jours</h2><p class="mt-2 text-sm text-zinc-500">Collecte, analyse et validation selon leur date respective, heure de Paris.</p><div class="mt-5 h-80" x-data="watchChart(@js($stats['series']))" wire:ignore><canvas x-ref="canvas" aria-label="Articles collectés, jugés pertinents par l’IA et validés sur trente jours" role="img"></canvas></div></section>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel"><h2 class="text-xl font-semibold">Répartition par catégorie</h2><p class="mt-2 text-xs text-zinc-500">Un article peut appartenir à plusieurs catégories.</p><ul class="mt-4 space-y-3">@foreach($stats['categories'] as $category)<li class="flex justify-between gap-4 text-sm"><span>{{ $category->name }}</span><strong>{{ $category->articles_count }}</strong></li>@endforeach</ul></section>
            <section class="panel"><h2 class="text-xl font-semibold">Sources les plus productives</h2><ol class="mt-4 space-y-3">@forelse($stats['sources'] as $source)<li class="flex justify-between gap-4"><span>{{ $source->name }}</span><strong>{{ $source->articles_count }}</strong></li>@empty<li class="text-zinc-500">Aucune source.</li>@endforelse</ol>
                <h2 class="mt-8 text-xl font-semibold">Qualité des recommandations</h2><p class="mt-3 text-3xl font-semibold">{{ $stats['quality_rate'] !== null ? $stats['quality_rate'].' %' : 'Pas encore de données' }}</p><p class="mt-2 text-sm text-zinc-500">{{ $stats['accepted'] }} recommandations validées · {{ $stats['declined'] }} rejetées. Part des articles jugés pertinents par l’IA que vous avez ensuite validés. L’IA n’apprend pas automatiquement de vos choix : ajustez le profil de veille dans les paramètres.</p>
            </section>
        </div>
    </div>
</x-layouts::app>

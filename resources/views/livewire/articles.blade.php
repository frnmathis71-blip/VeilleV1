<div class="space-y-6">
    <header><p class="text-sm font-medium text-blue-600">VOTRE VEILLE</p><h1 class="mt-1 text-2xl font-semibold sm:text-3xl">{{ $title }}</h1><p class="mt-2 text-zinc-500">{{ match($mode) { 'library' => 'Les articles que vous avez personnellement validés.', 'interesting' => 'Les articles que l’IA a jugés pertinents pour votre veille.', 'irrelevant' => 'Les articles que l’IA a écartés. Ouvrez-en un et marquez-le « Pertinent » pour le repêcher dans votre bibliothèque. Ils sont supprimés automatiquement après la durée de conservation configurée.', default => 'Explorez, lisez et sélectionnez ce qui compte pour vous.' } }}</p></header>
    {{-- On phones the filters are folded behind a button; they are always open on larger screens. --}}
    <div x-data="{ open: window.matchMedia('(min-width: 768px)').matches }">
    <flux:button class="w-full md:hidden" icon="funnel" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="article-filters">
        <span x-text="open ? 'Masquer les filtres' : 'Filtres'">Filtres</span>
    </flux:button>
    <section id="article-filters" x-show="open" x-cloak class="panel mt-3 grid gap-4 sm:grid-cols-2 md:mt-0 xl:grid-cols-4" aria-label="Filtres des articles">
        <flux:input wire:model.live.debounce.400ms="search" label="Rechercher" placeholder="Un sujet, une technologie…" />
        <flux:select wire:model.live="category" label="Catégorie"><option value="">Toutes</option>@foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="source" label="Source"><option value="">Toutes</option>@foreach($sources as $rss)<option value="{{ $rss->id }}">{{ $rss->name }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="sort" label="Trier par"><option value="newest">Plus récents</option><option value="oldest">Plus anciens</option><option value="score">Meilleur score</option></flux:select>
        <flux:input wire:model.live="from" label="Publié depuis" type="date" />
        <flux:input wire:model.live="to" label="Publié jusqu’au" type="date" />
        <flux:input wire:model.live.debounce.400ms="score" label="Score minimum" type="number" min="0" max="100" />
        <flux:select wire:model.live="reading" label="Lecture"><option value="">Tous</option><option value="unread">Non lus</option><option value="read">Lus</option><option value="later">À lire plus tard</option></flux:select>
        @if($mode !== 'library')<flux:select wire:model.live="decision" label="Décision"><option value="">Toutes</option><option value="pending">En attente</option><option value="relevant">Pertinents</option><option value="rejected">Rejetés</option></flux:select>@endif
    </section>
    </div>
    @if($mode === 'library')
        <form method="GET" action="{{ route('articles.export') }}" class="panel flex flex-wrap items-end gap-4">
            <flux:input name="from" type="date" label="Export : publication depuis" />
            <flux:input name="to" type="date" label="Jusqu’au" />
            <flux:button type="submit" variant="primary">Exporter le dossier PDF</flux:button>
            <p class="w-full text-xs text-zinc-500">Tous vos articles pertinents sur cette période, sans les autres filtres de la liste. Maximum 100 articles.</p>
        </form>
    @endif
    <p class="text-sm text-zinc-500">{{ $articles->total() }} article(s)</p>
    <div class="tile-grid">
        @forelse($articles as $article)
            <x-article-tile :article="$article" wire:key="article-{{ $article->id }}" />
        @empty
            <div class="panel text-center" style="grid-column:1/-1"><h2 class="text-lg font-semibold">Aucun article à afficher</h2><p class="mt-2 text-zinc-500">{{ $mode === 'irrelevant' ? 'Aucun article écarté par l’IA pour ces filtres.' : 'Modifiez les filtres ou lancez une collecte depuis l’historique.' }}</p></div>
        @endforelse
    </div>
    {{ $articles->links() }}
</div>

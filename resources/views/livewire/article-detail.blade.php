<div class="mx-auto max-w-5xl space-y-6">
    <flux:button :href="route('articles.all')" wire:navigate>Retour aux articles</flux:button>
    @if($message)<p class="notice" role="status">{{ $message }}</p>@endif
    <article class="panel space-y-5">
        <p class="text-sm text-zinc-500">{{ $article->source?->name }} · {{ $article->published_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'Date inconnue' }}</p>
        <h1 class="text-3xl font-semibold leading-tight">{{ $article->displayTitle() }}</h1>
        @if ($article->isTitleTranslated())<p class="text-sm text-zinc-500" lang="{{ $article->language ?: 'en' }}">Titre original : {{ $article->title }}</p>@endif
        <div class="flex flex-wrap gap-2">@if($article->analysis?->verdict)<x-verdict-badge :verdict="$article->analysis->verdict" />@endif<span class="rounded bg-blue-50 px-3 py-1 font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">Score : {{ $article->analysis?->score ?? 'En attente' }}</span>@foreach($article->categories as $cat)<span class="rounded bg-zinc-100 px-3 py-1 text-sm dark:bg-zinc-800">{{ $cat->name }}</span>@endforeach</div>
        @if($article->image_url)<img src="{{ $article->image_url }}" alt="" referrerpolicy="no-referrer" class="max-h-80 w-full rounded-lg object-cover" style="max-height:20rem;width:100%;object-fit:cover" />@endif
        @php($byAi = $article->analysis?->method === 'gemini')
        <section><h2 class="text-lg font-semibold">{{ $byAi ? 'Résumé' : 'Extrait de l’article' }}</h2><p class="mt-2 whitespace-pre-line leading-relaxed">{{ $article->analysis?->summary ?? $article->description }}</p><p class="mt-2 text-xs text-zinc-500">{{ $byAi ? 'Résumé rédigé par Gemini à partir du seul extrait fourni par le flux ; vérifiez sur l’article original.' : 'Extrait du flux dans sa langue d’origine ; aucune traduction automatique n’est effectuée.' }}</p></section>
        @if($article->analysis_status->value !== 'completed' && $article->analysis_error)<p class="notice" role="status">Analyse en attente : {{ $article->analysis_error }}</p>@endif
        @if($article->analysis)
            <section><h2 class="text-lg font-semibold">{{ $byAi ? 'Pourquoi ce verdict ?' : 'Pourquoi ce score ?' }}</h2><p class="mt-2 text-sm text-zinc-500">{{ $article->analysis->explanation }}</p>
                <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach($article->analysis->criteria_scores as $key => $value)<div class="flex justify-between rounded bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800"><dt>{{ ['novelty'=>'Nouveauté','importance'=>'Importance','impact'=>'Impact estimé','usefulness'=>'Utilité','originality'=>'Originalité (neutre)','reliability'=>'Fiabilité','freshness'=>'Actualité'][$key] ?? $key }}</dt><dd>{{ $value }}/100</dd></div>@endforeach
                </dl><p class="mt-3 text-xs text-zinc-500">{{ $byAi ? 'Analyse IA' : 'Analyse locale' }} · {{ $article->analysis->rules_version }} · {{ $article->analysis->analyzed_at->timezone('Europe/Paris')->format('d/m/Y H:i') }}</p>
            </section>
        @endif
        <a class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" href="{{ $article->url }}" target="_blank" rel="noopener noreferrer">Lire l’article original <flux:icon.arrow-top-right-on-square variant="micro" aria-hidden="true" /></a>
        <p class="text-xs text-zinc-500">Le contenu complet reste sur le site de l’éditeur.</p>
    </article>
    <section class="panel space-y-4">
        <h2 class="text-xl font-semibold">Votre décision</h2>
        <p class="text-sm text-zinc-500">Le verdict de l’IA est indépendant de votre avis : marquer un article « Pertinent » l’ajoute à votre bibliothèque, même si l’IA l’a écarté.</p>
        <p>État : {{ match($review->decision?->value) { 'relevant' => 'Pertinent', 'rejected' => 'Non pertinent', default => 'En attente de décision' } }} · {{ $review->read_at ? 'Lu' : 'Non lu' }}</p>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" wire:click="decide('relevant')">Pertinent</flux:button>
            <flux:button wire:click="decide('rejected')">Non pertinent</flux:button>
            @if($review->decision)<flux:button wire:click="decide('clear')">Retirer ma décision</flux:button>@endif
            <flux:button wire:click="toggleLater">{{ $review->saved_for_later_at ? 'Retirer de ma liste de lecture' : 'À lire plus tard' }}</flux:button>
            @unless($review->read_at)<flux:button wire:click="markRead">Marquer comme lu</flux:button>@endunless
        </div>
        <form wire:submit="saveNote" class="space-y-3"><flux:textarea wire:model="note" label="Note personnelle" rows="4" /><flux:button type="submit">Enregistrer ma note</flux:button></form>
    </section>
</div>

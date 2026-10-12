<div class="mx-auto max-w-5xl space-y-6">
    <h1 class="text-3xl font-semibold">Paramètres de veille</h1>
    @if($message)<p class="notice" role="status">{{ $message }}</p>@endif
    <form wire:submit="save" class="space-y-6">
        <section class="panel grid gap-4 sm:grid-cols-2"><h2 class="text-xl font-semibold sm:col-span-2">Collecte et sélection</h2>

            <flux:input wire:model="settings.batch_size" label="Articles maximum par lot" type="number" min="1" max="100" />
            <flux:select wire:model="settings.collect_hour" label="Heure de la collecte automatique" description="Chaque jour, heure de Paris.">
                @foreach (range(0, 23) as $hour)<option value="{{ $hour }}">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</option>@endforeach
            </flux:select>
            <flux:input wire:model="settings.max_run_minutes" label="Durée maximale d’une collecte (minutes)" type="number" min="5" max="600" description="Passé ce délai, la collecte se termine ; les articles non analysés le seront à la collecte suivante." />
            <flux:input wire:model="settings.max_age_days" label="Âge maximal d’un article (jours)" type="number" min="0" max="365" description="Les articles plus anciens sont écartés sans appel à l’IA. 0 : aucune limite." />
            <flux:input wire:model="settings.retention_days" label="Conservation des articles non retenus (jours)" type="number" min="30" max="3650" />
            <flux:input wire:model="settings.irrelevant_retention_days" label="Conservation des articles impertinents (jours)" type="number" min="1" max="3650" />
            <flux:checkbox wire:model="settings.enabled" label="Activer les collectes planifiées" />
            <p class="text-sm text-zinc-500 sm:col-span-2">
                Prochaine collecte automatique : <strong>{{ $nextCollection }}</strong>.
                Sur un hébergement OVH (tâche planifiée toutes les heures), elle démarre au passage du cron qui suit cette heure, puis tout est traité dans l’heure. Désactiver la planification n’annule pas une collecte déjà demandée.
            </p>
        </section>
        <section class="panel space-y-4"><h2 class="text-xl font-semibold">Tri par l’IA (Gemini)</h2>
            @if($analyzer === 'gemini')
                <p class="text-sm {{ $hasKey ? 'text-zinc-500' : 'text-red-600' }}">{{ $hasKey ? 'Analyseur actif : Gemini, modèle '.$model.'.' : 'Analyseur Gemini actif mais GEMINI_API_KEY est vide dans le fichier .env : les articles resteront en attente.' }} Seuls le titre et l’extrait du flux sont envoyés à Google.</p>
            @else
                <p class="text-sm text-zinc-500">Analyseur actif : local (WATCH_ANALYZER=local). Passez WATCH_ANALYZER=gemini dans le fichier .env pour activer le tri par l’IA.</p>
            @endif
            <flux:textarea wire:model="settings.ai_profile" rows="6" label="Ce qui est pertinent pour ma veille" description="L’IA lit ce texte avant chaque article pour décider s’il est pertinent ou impertinent. Décrivez ce que vous voulez garder et ce que vous voulez écarter." />
        </section>
        <section class="panel space-y-4"><h2 class="text-xl font-semibold">Mots-clés et analyse locale</h2><p class="text-sm text-zinc-500">Les mots-clés guident l’IA ; un article contenant un mot-clé exclu est écarté sans appel à l’IA. Avec Gemini, le score final est la moyenne de la note de l’IA et des critères pondérés ci-dessous : un article n’est retenu que si l’IA le juge pertinent, qu’il n’a pas déjà été couvert, et que ce score atteint le minimum.</p>
            <flux:input wire:model="settings.threshold" label="Score minimum pour être retenu" type="number" min="0" max="100" /><flux:textarea wire:model="settings.priority_keywords" label="Mots-clés prioritaires, séparés par des virgules" /><flux:textarea wire:model="settings.excluded_keywords" label="Mots-clés exclus, séparés par des virgules" />
            <div class="grid gap-4 sm:grid-cols-2">@foreach(['novelty'=>'Nouveauté','importance'=>'Importance','impact'=>'Impact','usefulness'=>'Utilité','originality'=>'Originalité','reliability'=>'Fiabilité','freshness'=>'Actualité'] as $key=>$label)<flux:input wire:model="settings.weights.{{ $key }}" :label="$label.' (%)'" type="number" min="0" max="100" />@endforeach</div>
            @error('settings.weights')<p class="text-red-600">{{ $message }}</p>@enderror
            <p class="text-sm text-zinc-500">Total attendu : 100 %. L’originalité est notée par l’IA (valeur neutre de 50/100 en analyse locale).</p>
        </section>
        <section class="panel space-y-3"><h2 class="text-xl font-semibold">Catégories surveillées</h2><div class="grid gap-3 sm:grid-cols-2">@foreach($categories as $category)<flux:checkbox wire:model="monitored" :value="$category->id" :label="$category->name" />@endforeach</div></section>
        <flux:button type="submit" variant="primary">Enregistrer les paramètres</flux:button>
    </form>
</div>

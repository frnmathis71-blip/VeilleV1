<div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-3xl font-semibold">Sources RSS</h1><p class="mt-2 text-zinc-500">Les points de départ de votre veille.</p></div><flux:button :href="route('history')" wire:navigate>Collectes et historique</flux:button></header>
    @if ($message)<p role="status" class="notice">{{ $message }}</p>@endif
    <form wire:submit="save" class="panel grid gap-4 md:grid-cols-2">
        <h2 class="text-lg font-semibold md:col-span-2">{{ $editing ? 'Modifier la source' : 'Ajouter une source' }}</h2>
        <flux:input wire:model="name" label="Nom de la source" required />
        <flux:input wire:model="url" label="URL du flux RSS ou Atom" type="url" placeholder="https://exemple.fr/feed/" required />
        <flux:select wire:model="language" label="Langue"><option value="fr">Français</option><option value="en">Anglais</option><option value="">Inconnue</option></flux:select>
        <flux:input wire:model="reliability" label="Fiabilité estimée (0–100)" type="number" min="0" max="100" />
        <flux:checkbox wire:model="active" label="Source active" />
        <div class="flex gap-2 md:col-span-2"><flux:button type="submit" variant="primary">Enregistrer</flux:button>@if ($editing)<flux:button wire:click="cancel">Annuler</flux:button>@endif</div>
    </form>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($sources as $source)
            <article class="panel space-y-3" wire:key="source-{{ $source->id }}">
                <div class="flex justify-between gap-3"><h2 class="font-semibold">{{ $source->name }}</h2><span class="text-sm {{ $source->active ? 'text-green-600' : 'text-zinc-500' }}">{{ $source->active ? 'Active' : 'Désactivée' }}</span></div>
                <p class="break-all text-sm text-zinc-500">{{ $source->url }}</p>
                <p class="text-sm">{{ $source->articles_count }} articles · Dernière récupération : {{ $source->last_success_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'Jamais' }}</p>
                @if ($source->last_error)<p class="text-sm text-red-600">{{ $source->last_error }}</p>@endif
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" wire:click="edit({{ $source->id }})">Modifier</flux:button>
                    <flux:button size="sm" wire:click="testFeed({{ $source->id }})" wire:loading.attr="disabled">Tester</flux:button>
                    <flux:button size="sm" wire:click="toggle({{ $source->id }})">{{ $source->active ? 'Désactiver' : 'Activer' }}</flux:button>
                    <flux:button size="sm" wire:click="archive({{ $source->id }})" wire:confirm="Archiver cette source ? Les articles seront conservés.">Archiver</flux:button>
                </div>
            </article>
        @empty
            <p class="panel lg:col-span-2">Aucune source configurée. Ajoutez une URL de flux ci-dessus.</p>
        @endforelse
    </div>
    {{ $sources->links() }}
</div>

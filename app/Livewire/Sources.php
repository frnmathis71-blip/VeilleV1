<?php

namespace App\Livewire;

use App\Models\RssSource;
use App\Services\Feeds\FeedFetcher;
use App\Services\Feeds\FeedParser;
use App\Services\Feeds\PublicUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Sources extends Component
{
    use WithPagination;

    public ?int $editing = null;

    public string $name = '';

    public string $url = '';

    public string $language = 'fr';

    public int $reliability = 70;

    public bool $active = true;

    public string $message = '';

    public function mount(): void
    {
        Gate::authorize('manage-watch');
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-watch');
        $source = RssSource::findOrFail($id);
        $this->editing = $id;
        $this->name = $source->name;
        $this->url = $source->url;
        $this->language = $source->language ?? '';
        $this->reliability = $source->reliability;
        $this->active = $source->active;
    }

    public function cancel(): void
    {
        $this->reset('editing', 'name', 'url', 'language', 'reliability', 'active');
        $this->resetValidation();
    }

    public function save(PublicUrl $urls): void
    {
        Gate::authorize('manage-watch');
        $data = $this->validate([
            'name' => 'required|string|max:255', 'url' => 'required|url:http,https|max:2000',
            'language' => ['nullable', Rule::in(['fr', 'en', ''])],
            'reliability' => 'required|integer|min:0|max:100', 'active' => 'boolean',
        ]);
        try {
            $host = $urls->validate($this->url);
            $urls->resolve($host);
        } catch (\InvalidArgumentException $e) {
            $this->addError('url', $e->getMessage());

            return;
        }
        $hash = hash('sha256', $this->url);
        if (RssSource::withTrashed()->where('url_hash', $hash)->when($this->editing, fn ($q) => $q->where('id', '!=', $this->editing))->exists()) {
            $this->addError('url', 'Cette URL est déjà enregistrée, éventuellement dans les archives.');

            return;
        }
        $source = $this->editing ? RssSource::findOrFail($this->editing) : new RssSource;
        $source->fill([...$data, 'url_hash' => $hash])->save();
        $this->cancel();
        $this->message = 'Source enregistrée.';
    }

    public function toggle(int $id): void
    {
        Gate::authorize('manage-watch');
        $source = RssSource::findOrFail($id);
        $source->update(['active' => ! $source->active]);
    }

    public function archive(int $id): void
    {
        Gate::authorize('manage-watch');
        RssSource::findOrFail($id)->delete();
        $this->message = 'Source archivée ; ses articles sont conservés.';
    }

    public function testFeed(int $id, FeedFetcher $fetcher, FeedParser $parser): void
    {
        Gate::authorize('manage-watch');
        $source = RssSource::findOrFail($id);
        if (! Cache::add('watch-host:'.hash('sha256', (string) parse_url($source->url, PHP_URL_HOST)), true, 10)) {
            $this->message = 'Veuillez patienter dix secondes avant une nouvelle requête vers ce domaine.';

            return;
        }
        try {
            $count = count($parser->parse($fetcher->fetch($source->url)));
            $source->update(['last_error' => null]);
            $this->message = 'Flux valide : '.$count.' entrées disponibles. Aucun article importé lors du test.';
        } catch (\Throwable $e) {
            $message = $e instanceof \RuntimeException || $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Impossible de lire ce flux.';
            $source->update(['last_error' => mb_substr($message, 0, 500)]);
            $this->message = $message;
        }
    }

    public function render(): View
    {
        Gate::authorize('manage-watch');

        return view('livewire.sources', ['sources' => RssSource::withCount('articles')->orderBy('name')->paginate(15)])->layout('layouts.app', ['title' => 'Sources RSS']);
    }
}

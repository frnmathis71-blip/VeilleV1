<?php

namespace App\Livewire;

use App\Enums\RelevanceVerdict;
use App\Models\Article;
use App\Models\ArticleAnalysis;
use App\Models\Category;
use App\Models\RssSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Articles extends Component
{
    use WithPagination;

    #[Locked]
    public string $mode = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $score = '';

    #[Url]
    public string $reading = '';

    #[Url]
    public string $decision = '';

    #[Url]
    public string $sort = 'newest';

    public function mount(): void
    {
        $this->mode = match (request()->route()?->getName()) {
            'articles.interesting' => 'interesting',
            'articles.irrelevant' => 'irrelevant',
            'articles.library' => 'library',
            default => 'all',
        };
        if ($this->mode === 'interesting' && ! request()->has('decision')) {
            $this->decision = 'pending';
        }
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    /** @return Builder<Article> */
    public function query(): Builder
    {
        $userId = auth()->id();
        $query = Article::with(['source', 'analysis', 'categories', 'reviews' => fn ($q) => $q->where('user_id', $userId)]);
        if ($this->mode === 'interesting') {
            $query->withVerdict(RelevanceVerdict::Relevant);
        }
        if ($this->mode === 'irrelevant') {
            $query->withVerdict(RelevanceVerdict::Irrelevant);
        }
        if ($this->mode === 'library') {
            $query->whereHas('reviews', fn ($q) => $q->where('user_id', $userId)->where('decision', 'relevant'));
        }
        if ($this->search !== '') {
            $term = mb_substr($this->search, 0, 200);
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')
                ->orWhereHas('analysis', fn ($a) => $a->where('title_fr', 'like', '%'.$term.'%')->orWhere('summary', 'like', '%'.$term.'%')));
        }
        if (ctype_digit($this->category)) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $this->category));
        }
        if (ctype_digit($this->source)) {
            $query->where('rss_source_id', $this->source);
        }
        foreach (['from' => '>=', 'to' => '<='] as $property => $operator) {
            $value = $this->{$property};
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $query->whereDate('published_at', $operator, $value);
            }
        }
        if (ctype_digit($this->score)) {
            $query->whereHas('analysis', fn ($q) => $q->where('score', '>=', min(100, (int) $this->score)));
        }
        if ($this->reading === 'unread') {
            $query->whereDoesntHave('reviews', fn ($q) => $q->where('user_id', $userId)->whereNotNull('read_at'));
        } elseif ($this->reading === 'read' || $this->reading === 'later') {
            $query->whereHas('reviews', fn ($q) => $q->where('user_id', $userId)->whereNotNull($this->reading === 'read' ? 'read_at' : 'saved_for_later_at'));
        }
        if ($this->decision === 'pending') {
            $query->whereDoesntHave('reviews', fn ($q) => $q->where('user_id', $userId)->whereNotNull('decision'));
        } elseif (in_array($this->decision, ['relevant', 'rejected'], true)) {
            $query->whereHas('reviews', fn ($q) => $q->where('user_id', $userId)->where('decision', $this->decision));
        }
        if ($this->sort === 'score') {
            $query->orderByDesc(ArticleAnalysis::select('score')->whereColumn('article_id', 'articles.id')->limit(1));
        } else {
            $query->orderBy('published_at', $this->sort === 'oldest' ? 'asc' : 'desc');
        }

        return $query->orderByDesc('articles.id');
    }

    public function render(): View
    {
        $title = match ($this->mode) {
            'interesting' => 'Articles pertinents', 'irrelevant' => 'Articles impertinents', 'library' => 'Ma bibliothèque', default => 'Tous les articles'
        };

        return view('livewire.articles', [
            'articles' => $this->query()->paginate(12),
            'categories' => Category::orderBy('name')->get(),
            'sources' => RssSource::withTrashed()->orderBy('name')->get(),
            'title' => $title,
        ])->layout('layouts.app', ['title' => $title]);
    }
}

<?php

namespace App\Livewire;

use App\Enums\ReviewDecision;
use App\Models\Article;
use App\Models\ArticleReview;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ArticleDetail extends Component
{
    #[Locked]
    public int $articleId;

    /** Page the user came from (list with its filters), so « Retour » goes back there. */
    #[Locked]
    public string $backUrl = '';

    public string $note = '';

    public string $message = '';

    public function mount(Article $article): void
    {
        $this->articleId = $article->id;
        $previous = url()->previous();
        // Only a page of this site, and never the article itself (reload, or a form posted on it).
        $sameSite = str_starts_with($previous, rtrim(url('/'), '/').'/');
        $this->backUrl = $sameSite && strtok($previous, '?') !== route('articles.show', $article) ? $previous : route('articles.all');
        $this->note = $this->review()->personal_note ?? '';
    }

    private function review(): ArticleReview
    {
        abort_unless(auth()->check(), 403);

        return ArticleReview::firstOrNew(['article_id' => $this->articleId, 'user_id' => auth()->id()]);
    }

    public function decide(string $decision): void
    {
        abort_unless(in_array($decision, ['relevant', 'rejected', 'clear'], true), 422);
        $review = $this->review();
        $review->decision = $decision === 'clear' ? null : ReviewDecision::from($decision);
        $review->decided_at = $decision === 'clear' ? null : Carbon::now();
        $review->save();
        $this->message = 'Votre décision a été enregistrée.';
    }

    public function markRead(): void
    {
        $review = $this->review();
        $review->read_at = Carbon::now();
        $review->save();
        $this->message = 'Article marqué comme lu.';
    }

    public function toggleLater(): void
    {
        $review = $this->review();
        $review->saved_for_later_at = $review->saved_for_later_at ? null : Carbon::now();
        $review->save();
        $this->message = 'Liste de lecture mise à jour.';
    }

    public function saveNote(): void
    {
        $this->validate(['note' => 'nullable|string|max:10000']);
        $review = $this->review();
        $review->personal_note = $this->note;
        $review->save();
        $this->message = 'Note enregistrée.';
    }

    public function render(): View
    {
        $article = Article::with(['source', 'analysis', 'categories'])->findOrFail($this->articleId);

        return view('livewire.article-detail', ['article' => $article, 'review' => $this->review()])->layout('layouts.app', ['title' => $article->displayTitle()]);
    }
}

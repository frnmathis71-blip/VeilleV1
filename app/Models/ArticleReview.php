<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ReviewDecision|null $decision
 * @property Carbon|null $decided_at
 * @property Carbon|null $read_at
 * @property Carbon|null $saved_for_later_at
 */
class ArticleReview extends Model
{
    protected $fillable = ['article_id', 'user_id', 'decision', 'decided_at', 'read_at', 'saved_for_later_at', 'personal_note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'decided_at' => 'datetime',
            'read_at' => 'datetime',
            'saved_for_later_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

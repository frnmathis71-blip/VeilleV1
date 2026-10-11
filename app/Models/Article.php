<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\RelevanceVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property AnalysisStatus $analysis_status
 * @property Carbon|null $published_at
 * @property Carbon $collected_at
 */
class Article extends Model
{
    protected $fillable = ['rss_source_id', 'title', 'url', 'canonical_url_hash', 'guid', 'guid_hash', 'content_hash', 'author', 'description', 'available_content', 'image_url', 'language', 'published_at', 'collected_at', 'analysis_status', 'analysis_error', 'image_checked_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'collected_at' => 'datetime',
            'image_checked_at' => 'datetime',
            'analysis_status' => AnalysisStatus::class,
        ];
    }

    /** @return BelongsTo<RssSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(RssSource::class, 'rss_source_id')->withTrashed();
    }

    /** @return HasOne<ArticleAnalysis, $this> */
    public function analysis(): HasOne
    {
        return $this->hasOne(ArticleAnalysis::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Articles whose automatic analysis gave this verdict.
     *
     * @param  Builder<Article>  $query
     */
    public function scopeWithVerdict(Builder $query, RelevanceVerdict $verdict): void
    {
        $query->where('analysis_status', AnalysisStatus::Completed)
            ->whereHas('analysis', fn (Builder $q) => $q->where('verdict', $verdict));
    }

    /** @return HasMany<ArticleReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ArticleReview::class);
    }
}

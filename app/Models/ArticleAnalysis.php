<?php

namespace App\Models;

use App\Enums\RelevanceVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $analyzed_at
 * @property RelevanceVerdict|null $verdict
 * @property array<string, int|float> $criteria_scores
 */
class ArticleAnalysis extends Model
{
    protected $fillable = ['article_id', 'score', 'verdict', 'title_fr', 'summary', 'summary_language', 'explanation', 'criteria_scores', 'method', 'rules_version', 'configuration_hash', 'analyzed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'verdict' => RelevanceVerdict::class,
            'criteria_scores' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}

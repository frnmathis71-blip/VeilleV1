<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property AnalysisStatus $status
 * @property Carbon|null $retry_at
 */
class CollectionRunItem extends Model
{
    protected $fillable = ['collection_run_id', 'article_id', 'status', 'attempts', 'reserved_at', 'finished_at', 'retry_at', 'error'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'reserved_at' => 'datetime',
            'finished_at' => 'datetime',
            'retry_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<CollectionRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class, 'collection_run_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}

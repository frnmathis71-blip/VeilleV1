<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CollectionStatus $status
 * @property list<array<string, string|null>>|null $entries
 * @property int $cursor
 * @property int $created_count
 * @property int $duplicate_count
 * @property Carbon|null $retry_at
 */
class CollectionRunSource extends Model
{
    protected $fillable = ['collection_run_id', 'rss_source_id', 'status', 'attempts', 'started_at', 'finished_at', 'retry_at', 'discovered_count', 'created_count', 'duplicate_count', 'error', 'entries', 'cursor'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entries' => 'array',
            'cursor' => 'integer',
            'status' => CollectionStatus::class,
            'started_at' => 'datetime',
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

    /** @return BelongsTo<RssSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(RssSource::class, 'rss_source_id');
    }
}

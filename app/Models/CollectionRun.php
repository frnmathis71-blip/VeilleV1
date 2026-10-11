<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CollectionStatus $status
 * @property array<string, mixed> $configuration_snapshot
 * @property int $created_count
 * @property int $analyzed_count
 * @property int $duration_ms
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class CollectionRun extends Model
{
    protected $fillable = ['status', 'trigger', 'requested_by', 'started_at', 'finished_at', 'heartbeat_at', 'configuration_snapshot', 'sources_count', 'discovered_count', 'created_count', 'duplicate_count', 'analyzed_count', 'interesting_count', 'error_count', 'duration_ms', 'error'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CollectionStatus::class,
            'configuration_snapshot' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'heartbeat_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<CollectionRunSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(CollectionRunSource::class);
    }

    /** @return HasMany<CollectionRunItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CollectionRunItem::class);
    }
}

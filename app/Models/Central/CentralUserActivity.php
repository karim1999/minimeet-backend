<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CentralUserActivity extends Model
{
    protected $connection = 'central';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that performed this activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'user_id');
    }

    /**
     * Get the owning model for the activity.
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * Scope a query to only include recent activities.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to filter by specific action.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to order by most recent.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get the formatted action name.
     */
    public function getFormattedActionAttribute(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $this->action));
    }

    /**
     * Get a human-readable description of the activity.
     */
    public function getDescriptionAttribute(): string
    {
        $action = $this->formatted_action;

        if ($this->model_type && $this->model_id) {
            $modelName = class_basename($this->model_type);

            return "$action on $modelName #{$this->model_id}";
        }

        return $action;
    }
}

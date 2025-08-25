<?php

namespace App\Models\Tenant;

use Database\Factories\TenantUserActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TenantUserActivity extends Model
{
    use HasFactory;
    
    protected $table = 'user_activities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'model_type',
        'model_id',
        'metadata',
        'ip_address',
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
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /**
     * Get the owning model for the activity.
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * Scope a query to only include today's activities.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    /**
     * Scope a query to only include recent activities.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to filter by specific user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by specific action.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to order by most recent.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to include this week's activities.
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope a query to include this month's activities.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
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
    public function getDisplayDescriptionAttribute(): string
    {
        if ($this->description) {
            return $this->description;
        }

        $action = $this->formatted_action;

        if ($this->model_type && $this->model_id) {
            $modelName = class_basename($this->model_type);

            return "$action on $modelName #{$this->model_id}";
        }

        return $action;
    }

    /**
     * Get the time ago string for this activity.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TenantUserActivityFactory
    {
        return TenantUserActivityFactory::new();
    }

    /**
     * Get activity summary for display.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->formatted_action,
            'description' => $this->display_description,
            'time_ago' => $this->time_ago,
            'user_name' => $this->user->name,
            'ip_address' => $this->ip_address,
        ];
    }
}

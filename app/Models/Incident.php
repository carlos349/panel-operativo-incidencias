<?php

namespace App\Models;

use App\Enums\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    protected $fillable = [
        'title',
        'description',
        'status_id',
        'priority',
        'category_id',
        'requester_name',
        'assigned_to',
        'resolution_date',
        'first_response_due_at',
        'first_responded_at',
        'resolution_due_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'resolution_date' => 'immutable_datetime',
            'first_response_due_at' => 'immutable_datetime',
            'first_responded_at' => 'immutable_datetime',
            'resolution_due_at' => 'immutable_datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IncidentLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('requester_name', 'like', $like)
            ->orWhere('assigned_to', 'like', $like));
    }
}

<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['name'];

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function type(): IncidentStatus
    {
        return IncidentStatus::from($this->name);
    }

    public static function findByType(IncidentStatus $type): self
    {
        return static::query()->where('name', $type->value)->firstOrFail();
    }
}

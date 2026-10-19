<?php

namespace JoshDaugherty\HomeDashboard\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One dashboard setting. The value is wrapped as ['v' => ...] and encrypted as a
 * whole, so scalars, lists and secrets all share one storage path.
 *
 * @property string $key
 * @property array<string, mixed>|null $value
 */
class Setting extends Model
{
    protected $table = 'home_dashboard_settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted:array'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Audit
 *
 * @property int $id
 * @property string $table_name
 * @property int $record_id
 * @property string $action
 * @property string|null $old_values
 * @property string|null $new_values
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Audit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Audit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Audit query()
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereTableName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Audit whereUserId($value)
 * @mixin \Eloquent
 */
class Audit extends Model
{
    use HasFactory;

    protected $table = 'audit';

    protected $fillable = [
        'table_name', 'record_id', 'action', 'old_values', 'new_values', 'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

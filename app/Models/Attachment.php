<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Attachment
 *
 * @property int $id
 * @property string $name
 * @property string $media
 * @property string $type
 * @property int $task_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment query()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Attachment extends Model
{
    use HasFactory;

    protected $table = 'attachments'; // Ensure the correct table name is used.

    protected $fillable = [
        'name',
        'task_id', 
        'media',
        'created_at', 
        'updated_at', 
    ];

    // If you have relationships with other models, such as 'task', you can define them here.
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}

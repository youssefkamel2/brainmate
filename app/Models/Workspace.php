<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Workspace
 *
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property string $location
 * @property string $rate
 * @property string $price
 * @property string|null $phone_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $image_url
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace query()
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Workspace whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Workspace extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'image',
        'location',
        'rate',
        'price',
        'phone_number',
    ];

    /**
     * Accessors
     */

    // Get the full URL for the workspace image
    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }
}

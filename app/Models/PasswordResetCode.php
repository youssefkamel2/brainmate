<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\PasswordResetCode
 *
 * @property int $id
 * @property string $email
 * @property string $reset_code
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode query()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereResetCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetCode whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PasswordResetCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'reset_code',
        'expires_at',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}


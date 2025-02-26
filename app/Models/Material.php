<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'media',
        'team_id',
        'uploaded_by',
    ];

    // Relationship with the Team model
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    // Relationship with the User model (uploaded_by)
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
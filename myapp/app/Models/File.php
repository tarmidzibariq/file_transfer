<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'user_id',
        'original_name',
        'file_name',
        'file_size',
        'mime_type',
        'path',
        'is_public',
    ];

    // Relationships user
    public function user() {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'file_size' => 'integer',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPostMedia extends Model
{
    protected $table = 'social_post_media';

    protected $fillable = [
        'post_id', 'media_type', 'file_path', 'url',
        'alt_text', 'sort_order',
    ];

    public function post()
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }
}

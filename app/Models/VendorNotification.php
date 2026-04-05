<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'link', 'meta', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'meta'    => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeUnread($query) { return $query->where('is_read', false); }

    public function markAsRead(): void { $this->update(['is_read' => true]); }
}

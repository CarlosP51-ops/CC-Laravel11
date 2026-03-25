<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = [
        'type', 'title', 'subtitle', 'body',
        'link', 'entity_type', 'entity_id',
        'meta', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'meta'    => 'array',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Crée une notification si elle n'existe pas déjà pour la même entité
     * (évite les doublons lors du polling)
     */
    public static function createUnique(array $data): self
    {
        if (isset($data['entity_type'], $data['entity_id'])) {
            $existing = static::where('entity_type', $data['entity_type'])
                ->where('entity_id', $data['entity_id'])
                ->where('type', $data['type'])
                ->where('is_read', false)
                ->first();

            if ($existing) return $existing;
        }

        return static::create($data);
    }
}

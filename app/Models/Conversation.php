<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'participant_1_id', 'participant_2_id',
        'last_message_at', 'deleted_by_1', 'deleted_by_2',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'deleted_by_1'    => 'boolean',
        'deleted_by_2'    => 'boolean',
    ];

    public function participant1()
    {
        return $this->belongsTo(User::class, 'participant_1_id');
    }

    public function participant2()
    {
        return $this->belongsTo(User::class, 'participant_2_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function otherParticipant(int $userId): ?User
    {
        return $this->participant_1_id === $userId
            ? $this->participant2
            : $this->participant1;
    }

    /** Vérifie si la conversation est supprimée pour cet utilisateur */
    public function isDeletedFor(int $userId): bool
    {
        if ($this->participant_1_id === $userId) return (bool) $this->deleted_by_1;
        if ($this->participant_2_id === $userId) return (bool) $this->deleted_by_2;
        return false;
    }

    /** Soft-delete pour un participant */
    public function softDeleteFor(int $userId): void
    {
        if ($this->participant_1_id === $userId) {
            $this->update(['deleted_by_1' => true]);
        } elseif ($this->participant_2_id === $userId) {
            $this->update(['deleted_by_2' => true]);
        }

        // Si les deux ont supprimé → suppression physique
        $this->refresh();
        if ($this->deleted_by_1 && $this->deleted_by_2) {
            $this->messages()->delete();
            $this->delete();
        }
    }

    /** Restaure la conversation pour un participant (quand il reçoit un nouveau message) */
    public function restoreFor(int $userId): void
    {
        if ($this->participant_1_id === $userId) {
            $this->update(['deleted_by_1' => false]);
        } elseif ($this->participant_2_id === $userId) {
            $this->update(['deleted_by_2' => false]);
        }
    }

    public static function findOrCreate(int $userA, int $userB): self
    {
        $p1 = min($userA, $userB);
        $p2 = max($userA, $userB);

        $conv = self::firstOrCreate(
            ['participant_1_id' => $p1, 'participant_2_id' => $p2]
        );

        // Restaurer si l'un des participants l'avait supprimée
        $conv->restoreFor($userA);

        return $conv;
    }
}

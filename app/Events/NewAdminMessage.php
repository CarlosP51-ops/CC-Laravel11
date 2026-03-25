<?php

namespace App\Events;

use App\Models\AdminMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAdminMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AdminMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-messages.' . $this->message->to_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->message->id,
            'from_user_id' => $this->message->from_user_id,
            'to_user_id'   => $this->message->to_user_id,
            'subject'      => $this->message->subject,
            'body'         => $this->message->body,
            'is_read'      => $this->message->is_read,
            'created_at'   => $this->message->created_at->toISOString(),
            'sender'       => [
                'id'       => $this->message->sender->id,
                'fullname' => $this->message->sender->fullname,
                'role'     => $this->message->sender->role,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }
}

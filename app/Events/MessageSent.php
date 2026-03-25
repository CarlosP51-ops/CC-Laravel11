<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message, public int $recipientId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->recipientId)];
    }

    public function broadcastWith(): array
    {
        $msg = $this->message->load(['sender:id,fullname,role', 'attachments']);

        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'body'            => $msg->body,
            'is_read'         => false,
            'created_at'      => $msg->created_at->toISOString(),
            'sender'          => [
                'id'       => $msg->sender->id,
                'fullname' => $msg->sender->fullname,
                'role'     => $msg->sender->role,
            ],
            'attachments' => $msg->attachments->map(fn($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => $a->url,
                'mime_type'     => $a->mime_type,
                'size'          => $a->formatted_size,
                'is_image'      => $a->is_image,
            ]),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}

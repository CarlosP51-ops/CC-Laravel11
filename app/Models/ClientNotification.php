<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message',
        'link', 'seller_id', 'product_id', 'is_read',
    ];

    protected $casts = ['is_read' => 'boolean'];

    public function user()    { return $this->belongsTo(User::class); }
    public function seller()  { return $this->belongsTo(Seller::class); }
    public function product() { return $this->belongsTo(Product::class); }
}

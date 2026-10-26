<?php
namespace App\Models\Order;

class RefundTask extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'order_refund_tasks';
    protected $guarded = ['id'];
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Order::class); }
}

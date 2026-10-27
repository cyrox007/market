<?php
namespace App\Models\Order;
class CancellationRequest extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'order_cancellation_requests';
    protected $guarded = ['id'];
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Order::class); }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OneCOrderReadResource;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A protected initial-list endpoint, NOT a reliable change-feed cursor.
 * Order eligibility is always applied server-side before any user filters.
 */
class OneCOrdersReadController extends Controller
{
    private const EXPORTED_STATUSES = [
        'accepted', 'assembling', 'assembled', 'shipped', 'in_transit', 'delivered',
    ];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'order_id' => 'sometimes|integer|min:1',
            'number' => 'sometimes|string|max:100',
            'status' => 'sometimes|string|max:255',
            'created_from' => 'sometimes|date_format:Y-m-d',
            'created_to' => 'sometimes|date_format:Y-m-d',
            'cooling_from' => 'sometimes|date_format:Y-m-d',
            'cooling_to' => 'sometimes|date_format:Y-m-d',
            'delivery_type' => 'sometimes|in:delivery,pickup',
            'payment_method' => 'sometimes|string|max:100',
            'after_id' => 'sometimes|integer|min:0',
            'snapshot_max_id' => 'sometimes|integer|min:0',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $statusFilters = [];
        if (isset($filters['status'])) {
            $statusFilters = array_unique(array_map('trim', explode(',', $filters['status'])));
            $known = array_column(OrderStatus::cases(), 'value');
            if (in_array('', $statusFilters, true)
                || array_diff($statusFilters, $known) !== []) {
                throw ValidationException::withMessages([
                    'status' => 'Неизвестный статус. Используйте значения из документации 1С.',
                ]);
            }
        }

        foreach (['created', 'cooling'] as $name) {
            $from = $filters[$name.'_from'] ?? null;
            $to = $filters[$name.'_to'] ?? null;
            if ($from && $to && $from > $to) {
                throw ValidationException::withMessages([
                    $name.'_to' => 'Дата окончания должна быть не раньше даты начала.',
                ]);
            }
        }

        // Neither explicit order_id nor status filters can bypass cooling.
        // Old orders without a reliable cooling_until are excluded by default.
        $query = Order::query()
            ->whereNotNull('cooling_until')
            ->where('cooling_until', '<=', now())
            ->where(function ($q): void {
                $q->whereIn('status', self::EXPORTED_STATUSES)
                    ->orWhere(function ($cancelled): void {
                        $cancelled->where('status', 'cancelled')
                            ->where(function ($released): void {
                                // Cancellation is visible only if this order had
                                // demonstrably crossed the release barrier earlier.
                                $released->whereColumn('integration_sent_at', '>=', 'cooling_until')
                                    ->orWhereHas('statusHistory', fn ($history) =>
                                        $history->whereIn('status', self::EXPORTED_STATUSES)
                                            ->whereColumn('order_status_history.created_at', '>=', 'orders.cooling_until')
                                    );
                            });
                    });
            });

        if (isset($filters['order_id'])) {
            $query->whereKey((int) $filters['order_id']);
        }
        if (isset($filters['number'])) {
            $query->where('number', $filters['number']);
        }
        if ($statusFilters !== []) {
            $query->whereIn('status', $statusFilters);
        }
        if (isset($filters['delivery_type'])) {
            $query->where('delivery_type', $filters['delivery_type']);
        }
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }
        foreach (['created' => 'created_at', 'cooling' => 'cooling_until'] as $name => $column) {
            if (isset($filters[$name.'_from'])) {
                $query->where($column, '>=', $filters[$name.'_from'].' 00:00:00');
            }
            if (isset($filters[$name.'_to'])) {
                $endExclusive = Carbon::parse($filters[$name.'_to'], 'UTC')
                    ->addDay()->format('Y-m-d').' 00:00:00';
                $query->where($column, '<', $endExclusive);
            }
        }

        // Stable ID-based pagination for an initial LIST scan. It does not
        // guarantee subsequent changes to existing orders will be observed.
        $snapshotMaxId = (int) ($filters['snapshot_max_id'] ?? ((clone $query)->max('id') ?? 0));
        $afterId = (int) ($filters['after_id'] ?? 0);
        if ($afterId > $snapshotMaxId && $snapshotMaxId !== 0) {
            throw ValidationException::withMessages([
                'after_id' => 'after_id не может превышать snapshot_max_id.',
            ]);
        }

        $limit = (int) ($filters['limit'] ?? 50);
        $orders = $query->where('id', '>', $afterId)
            ->where('id', '<=', $snapshotMaxId)
            ->with([
                'items', 'payments', 'additionalServices', 'statusHistory',
                'deliveryHandlingType',
            ])
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $orders->count() > $limit;
        $page = $orders->take($limit)->values();
        $codes = $page->pluck('payment_method')->filter()->unique()->all();
        $labels = $codes === [] ? [] : PaymentMethod::query()
            ->whereIn('code', $codes)->pluck('name', 'code')->all();
        $request->attributes->set('onec_payment_labels', $labels);

        return response()->json([
            'data' => $page->map(fn (Order $order): array =>
                (new OneCOrderReadResource($order))->resolve($request)
            )->all(),
            'meta' => [
                'limit' => $limit,
                'snapshot_max_id' => $snapshotMaxId,
                'next_after_id' => $hasMore ? (int) $page->last()->id : null,
                'has_more' => $hasMore,
                'as_of' => now()->toISOString(),
                'mode' => 'list',
            ],
        ]);
    }
}

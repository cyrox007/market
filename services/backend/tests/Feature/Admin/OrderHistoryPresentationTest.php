<?php

namespace Tests\Feature\Admin;

use App\Models\Order\OrderStatusHistory;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderHistoryPresentationTest extends TestCase
{
    public function test_admin_formats_utc_timestamps_in_moscow_without_changing_storage_timezone(): void
    {
        $column = TextColumn::make('created_at')->dateTime('d.m.Y H:i');
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Europe/Moscow', $column->getTimezone());
        $this->assertSame('19.11.2026 11:37', $column->formatState(Carbon::parse('2026-11-19 08:37:00', 'UTC')));
    }

    public function test_history_uses_customer_labels_for_new_lifecycle_statuses(): void
    {
        foreach (['manager_processing' => 'В обработке у менеджера', 'assembling' => 'Собирается', 'awaiting_payment' => 'Ожидание оплаты'] as $status => $label) {
            $this->assertSame($label, (new OrderStatusHistory(['status' => $status]))->status_label);
        }
    }

    public function test_legacy_automatic_comment_is_cleaned_for_display_only(): void
    {
        $original = 'Заказ создан. Период охлаждения 60 минут.';
        $history = new OrderStatusHistory(['comment' => $original]);
        $this->assertSame('Заказ создан.', $history->comment);
        $this->assertSame($original, $history->getAttributes()['comment']);
        $this->assertSame('Звонок покупателю', (new OrderStatusHistory(['comment' => 'Звонок покупателю']))->comment);
    }
}

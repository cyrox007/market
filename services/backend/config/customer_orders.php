<?php
return [
    // Logistics may add internal states without changing the customer's group or UI contract.
    'completed_statuses' => ['delivered'],
    'cancelled_statuses' => ['cancelled'],
    'statuses' => [
        'new' => ['label' => 'Новый', 'step' => 'created'],
        'manager_processing' => ['label' => 'В обработке у менеджера', 'step' => 'created'],
        'awaiting_payment' => ['label' => 'Ожидает оплаты', 'step' => 'created'],
        'accepted' => ['label' => 'Принят', 'step' => 'preparing'],
        'assembling' => ['label' => 'Собирается', 'step' => 'preparing'],
        'assembled' => ['label' => 'Собран', 'step' => 'preparing'],
        'shipped' => ['label' => 'Отправлен', 'step' => 'shipping'],
        'in_transit' => ['label' => 'Заказ в пути', 'step' => 'shipping'],
        // Reserved for an explicit logistics signal, not inferred from assembled.
        'ready_for_pickup' => ['label' => 'Готов к выдаче', 'step' => 'ready_for_pickup'],
        'delivered' => ['label' => 'Доставлен', 'step' => 'received'],
        'cancelled' => ['label' => 'Отменён', 'step' => null],
    ],
    'progress' => [
        'delivery' => ['created' => 'Создан', 'preparing' => 'Подготовка', 'shipping' => 'Отправлен', 'received' => 'Доставлен'],
        'pickup' => ['created' => 'Создан', 'preparing' => 'Подготовка', 'ready_for_pickup' => 'Готов к выдаче', 'received' => 'Получен'],
    ],
];

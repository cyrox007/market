<?php

namespace App\Policies;

use App\Models\Product\ProductDeliveryBlock;
use App\Models\User;

class ProductDeliveryBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny product_delivery_blocks');
    }

    public function view(User $user, ProductDeliveryBlock $productDeliveryBlock): bool
    {
        return $user->can('view product_delivery_blocks');
    }

    public function create(User $user): bool
    {
        return $user->can('create product_delivery_blocks');
    }

    public function update(User $user, ProductDeliveryBlock $productDeliveryBlock): bool
    {
        return $user->can('update product_delivery_blocks');
    }

    public function delete(User $user, ProductDeliveryBlock $productDeliveryBlock): bool
    {
        return $user->can('delete product_delivery_blocks');
    }
}

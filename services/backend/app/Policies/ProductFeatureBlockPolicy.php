<?php

namespace App\Policies;

use App\Models\Product\ProductFeatureBlock;
use App\Models\User;

class ProductFeatureBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny product_feature_blocks');
    }

    public function view(User $user, ProductFeatureBlock $productFeatureBlock): bool
    {
        return $user->can('view product_feature_blocks');
    }

    public function create(User $user): bool
    {
        return $user->can('create product_feature_blocks');
    }

    public function update(User $user, ProductFeatureBlock $productFeatureBlock): bool
    {
        return $user->can('update product_feature_blocks');
    }

    public function delete(User $user, ProductFeatureBlock $productFeatureBlock): bool
    {
        return $user->can('delete product_feature_blocks');
    }
}

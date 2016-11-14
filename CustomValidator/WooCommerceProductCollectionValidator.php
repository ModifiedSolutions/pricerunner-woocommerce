<?php

namespace CustomValidator;

use PricerunnerSDK\Models\Product;
use PricerunnerSDK\Validators\ProductCollectionValidator;
use PricerunnerSDK\Validators\ProductValidator;

class WooCommerceProductCollectionValidator extends ProductCollectionValidator
{
    protected function createProductValidator($product)
    {
        return new WooCommerceProductValidator($product);
    }

    protected function validateProductAgainstProductCollection(Product $product, ProductValidator $productValidator)
    {
        $this->validateEan($product, $productValidator);
        $this->validateSku($product, $productValidator);
    }
}
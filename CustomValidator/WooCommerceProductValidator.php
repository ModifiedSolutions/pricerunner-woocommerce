<?php

    namespace Pricerunner\CustomValidator;

    use PricerunnerSDK\Validators\ProductValidator;

    if (!defined('ABSPATH')) exit;

    class WooCommerceProductValidator extends ProductValidator
    {
        public function validate()
        {
            $this->validateCategoryName();
            $this->validateProductName();
            $this->validateSku();
            $this->validatePrice();
            $this->validateProductUrl();
            $this->validateIsbn();
            // $this->validateManufacturer();
            // $this->validateManufacturerSku();
            // $this->validateShippingCost();
            // $this->validateEan();
            $this->validateUpc();
            $this->validateDescription();
            $this->validateImageUrl();
            $this->validateStockStatus();
            // $this->validateDeliveryTime();
            $this->validateRetailerMessage();
            $this->validateCatalogId();
            $this->validateWarranty();
        }
    }

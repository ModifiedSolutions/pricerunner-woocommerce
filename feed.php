<?php

require_once(dirname(__FILE__) . '/../../../wp-load.php');
require_once(dirname(__FILE__) . '/classes/PricerunnerFeed.php');

use CustomValidator\WooCommerceProductCollectionValidator;
use PricerunnerSDK\PricerunnerSDK;
use PricerunnerSDK\Errors\ProductErrorRenderer;

$pricerunnerFeed = new PricerunnerFeed();

/*
 * Validate if WooCommerce is currently active
 */
if (!pricerunner_woocommerce_active_check()) {
	exit('No active shop.');
}

/*
 * Validate if the hash key is correct since last pricerunner feed activation
 */
if (!isset($_GET['hash']) || $_GET['hash'] != get_option('pricerunner_feed_hash')) {
	exit('Hash key is not valid.');
}

/*
 * Fetches all categories and builds them into a string.
 */
$categories = $pricerunnerFeed->getCategories();

/*
 * Passed all validations. Grab products and fetch into XML here!
 */
$feed = $pricerunnerFeed->getProducts($categories);
$dataContainer = PricerunnerSDK::generateDataContainer($feed, true, new WooCommerceProductCollectionValidator());

/*
 * Here we test our product feed
 */
if (isset($_GET['test'])) {
	$errors = $dataContainer->getErrors();

    $productErrorRenderer = new ProductErrorRenderer($errors);
    echo $productErrorRenderer->render();

    exit;
}

header('Content-Type: application/xml');
echo $dataContainer->getXmlString();
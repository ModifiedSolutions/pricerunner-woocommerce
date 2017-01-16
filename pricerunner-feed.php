<?php

    if (!defined('ABSPATH')) exit;

    /**
     * Plugin Name: Pricerunner Feed
     * Plugin URI: 
     * Description: Product XML Feed For Pricerunner.dk
     * Version: 1.0.9
     * Author: Modified Solutions ApS
     * Author URI: https://www.modified.dk/
     * Developer: Modified Solutions ApS
     * Developer URI: https://www.modified.dk/
     * Text Domain: woocommerce-extension
     * Domain Path: /languages
     * 
     */


    /*
     * This definition must be defined.
     * If it isn't the Pricerunner SDK will not be allowed to function.
     */
    define('PRICRUNNER_OFFICIAL_PLUGIN_VERSION', 'woo-1.0.9');


    require_once dirname(__FILE__) .'/classes/FeedLoader.php';
    require_once(dirname(__FILE__) . '/classes/Plugin.php');

    require_once(dirname(__FILE__) . '/models/Model.php');
    require_once(dirname(__FILE__) . '/pricerunner-php-sdk/src/files.php');
    require_once(dirname(__FILE__) . '/CustomValidator/WooCommerceProductValidator.php');
    require_once(dirname(__FILE__) . '/CustomValidator/WooCommerceProductCollectionValidator.php');



    // Register feed.
    $pricerunnerPlugin = Pricerunner\Plugin::make();
    $pricerunnerLoader = Pricerunner\FeedLoader::make();
    add_action('init', array($pricerunnerLoader, 'init'));



    add_action('admin_menu', array($pricerunnerPlugin, 'registerAdminMenuItem'));
    add_action('admin_enqueue_scripts', array($pricerunnerPlugin, 'registerAdminCss'));

    register_activation_hook(__FILE__, array($pricerunnerPlugin, 'activate'));
    register_deactivation_hook(__FILE__, array($pricerunnerPlugin, 'deactivate'));



    /*
     * Initializer
     */
    function pricerunner_feed()
    {
        $loader = Pricerunner\FeedLoader::make();
        $plugin = Pricerunner\Plugin::make();

    	// Check if WooCommerce is activated.
    	if (!$loader->isWoocommerceActive()) {
            $plugin->error(Pricerunner\Plugin::WOOCOMMERCE_NOT_ACTIVE);
    	}

    	if (!current_user_can('manage_options'))  {
    		$plugin->error(Pricerunner\Plugin::NOT_SUFFICIENT_PERMISSIONS);
    	}

        if ($nonceMessage = $plugin->checkNonce() !== true) {
            $plugin->error($nonceMessage);
        }

        echo $plugin->displayPage();
    }

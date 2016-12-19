<?php

    if (!defined('ABSPATH')) exit;

    /**
     * Plugin Name: Pricerunner Feed
     * Plugin URI: 
     * Description: Product XML Feed For Pricerunner.dk
     * Version: 1.0.7
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
    define('PRICRUNNER_OFFICIAL_PLUGIN_VERSION', 'woo-1.0.7');


    /*
     * THIS IS THE DATABASE VERSION.
     * 
     * Don't edit this one unless you make edits to the pricerunner_install() database structure.
     * This version number is not equal to the plugin's version itself.
     */
    $dbVersion = '1.0';

    /*
     * Check if the registered version is equal to our actual version. If not, run the installer again to update.
     */
    if (get_option('pricerunner_db_version', false) !== false && get_option('pricerunner_db_version') != $dbVersion) {
    	pricerunner_install();
    }

    /*
     * Register our new admin menu in the Wordpress dashboard.
     */
    add_action('admin_menu', 'pricerunner_feed_menu');

    add_action('admin_enqueue_scripts', 'pricerunner_load_admin_style');

    /*
     * Launch the install function when this plugin is being activated.
     */
    register_activation_hook(__FILE__, 'pricerunner_install');

    /*
     * Launch the uninstall function when this plugin is being deactivated.
     */
    register_deactivation_hook(__FILE__, 'pricerunner_uninstall');

    /*
     * Initialize function to add our page to the admin menus.
     */
    function pricerunner_feed_menu() {
        if (!current_user_can('manage_options'))  {
            return;
        }

    	// For Roles/Capabilities refer to the Wordpress docs: https://codex.wordpress.org/Roles_and_Capabilities
    	add_menu_page('Pricerunner XML Feed', 'Pricerunner Feed', 'manage_options', 'pricerunner-xml-feed', 'pricerunner_feed', 'dashicons-admin-generic');
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return  bool
     */
    function pricerunner_woocommerce_active_check() {
        $active_plugins = [];

        if (is_multisite()) {
            $active_plugins = array_keys(get_site_option('active_sitewide_plugins', array()));
        } else {
            $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        }

        foreach ($active_plugins as $active_plugin) {
            $active_plugin = explode('/', $active_plugin);
            if (isset($active_plugin[1]) && $active_plugin[1] === 'woocommerce.php') {
                return true;
            }
        }

        return false;
    }

    /*
     * Run our main function
     */
    function pricerunner_feed() {
    	// Check if WooCommerce is activated.
    	if (!pricerunner_woocommerce_active_check()) {
    		wp_die('<div class="wrap"><div id="setting-error-invalid_siteurl" class="error settings-error notice"><p>WooCommerce is not activated.</p></div></div>');
    	}

    	if (!current_user_can('manage_options'))  {
    		wp_die('<div class="wrap"><div id="setting-error-invalid_siteurl" class="error settings-error notice"><p>'. __('You do not have sufficient permissions to access this page.') .'</p></div></div>');
    	}

    	require_once dirname(__FILE__) .'/classes/PricerunnerFeed.php';

    	$pricerunnerFeed = new Pricerunner\Feed();
    	$pricerunnerFeed->init();
    }

    /*
     * Database installer. Controlled by $dbVersion and will launch if the current version doesn't match the value.
     */
    function pricerunner_install()
    {
        global $dbVersion;
    	require_once dirname(__FILE__) .'/classes/PricerunnerFeed.php';

    	$pricerunnerFeed = new Pricerunner\Feed();
    	$pricerunnerFeed->install($dbVersion);
    }

    /*
     * Plugin uninstaller
     */
    function pricerunner_uninstall()
    {
    	require_once dirname(__FILE__) .'/classes/PricerunnerFeed.php';

    	$pricerunnerFeed = new Pricerunner\Feed();
    	$pricerunnerFeed->uninstall();
    }

    /*
     * Loads our custom CSS.
     */
    function pricerunner_load_admin_style($hook)
    {
        if ($hook != 'toplevel_page_pricerunner-xml-feed') {
            return;
        }

        wp_enqueue_style('pricerunner_admin_style', plugins_url('assets/css/styles.css', __FILE__));
    }

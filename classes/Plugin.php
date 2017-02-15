<?php

    namespace Pricerunner;

    use PricerunnerSDK\PricerunnerSDK;

    if (!defined('ABSPATH')) exit;

    class Plugin
    {
        const WOOCOMMERCE_NOT_ACTIVE = 'WooCommerce is not activated.';
        const NOT_SUFFICIENT_PERMISSIONS = 'You do not have sufficient permissions to access this page.';
        const UNKNOWN_ACTION = 'Unknown action.';
        const WP_NONCE_NOT_SET = 'WP nonce not set.';
        const INVALID_WP_NONCE = 'WP nonce not valid! Please go back and refresh the page to try again.';
        const API_ERROR = 'An error occured when posting to the API. Please try again!';
        const CURL_MISSING_ERROR = 'The cURL extension for PHP isn\'t installed on this server configuration. Please install it before using this plugin.';


        /**
         * Contains our singleton instance.
         * @var \Pricerunner\Plugin
         */
        public static $instance;

        /**
         * Return a singleton instance of this class.
         * @return \Pricerunner\Plugin
         */
        public static function make()
        {
            if (is_null(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }


        /**
         * Internal database schema version.
         * @var string
         */
        public $dbVersion = '1.0';


        /**
         * @var \Pricerunner\Model
         */
        private $model;


        /**
         * Constructer
         */
        public function __construct()
        {
            $this->model = Model::make();
        }


        /**
         * Runs the activation process when the plugin gets activated inside WP.
         * @return void
         */
        public function activate()
        {
            update_option('pricerunner_feed_hash', PricerunnerSDK::getRandomString());
        }

        /**
         * Runs the deactivation process when the plugin gets deactivated inside WP.
         * @return void
         */
        public function deactivate()
        {
            delete_option('pricerunner_feed_hash');
            delete_option('pricerunner_feed_active');
            delete_option('pricerunner_feed_url');
            
            delete_option('pricerunner_contact_domain');
            delete_option('pricerunner_contact_name');
            delete_option('pricerunner_contact_email');
            delete_option('pricerunner_contact_phone');
        }


        /**
         * Register an admin menu item to access the plugin page.
         */
        public function registerAdminMenuItem()
        {
            if (!current_user_can('manage_options'))  {
                return;
            }

            add_menu_page(
                'Pricerunner XML Feed',
                'Pricerunner Feed',
                'manage_options',
                'pricerunner-xml-feed',
                'pricerunner_feed',
                'dashicons-rss'
            );
        }


        /**
         * Register a custom CSS file inside the administration on
         * the pricerunner plugin page.
         */
        public function registerAdminCss($hook)
        {
            if ($hook != 'toplevel_page_pricerunner-xml-feed') {
                return;
            }

            wp_enqueue_style('pricerunner_admin_style', plugins_url('../assets/css/styles.css', __FILE__));
        }


        /**
         * Runs a security check when form data is being posted.
         * The request verification is WP's own system called "nonce".
         */
        public function checkNonce()
        {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if (!isset($_REQUEST['_wpnonce'])) {
                    return self::WP_NONCE_NOT_SET;
                }

                if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'pricerunner_form')) {
                    return self::INVALID_WP_NONCE;
                }
            }

            return true;
        }


        public function generateFeedUrl()
        {
            $hash = get_option('pricerunner_feed_hash');
            $siteUrl = get_site_url();

            preg_match_all('/https?\:\/\/[\w.]+\.(?:[\w]{2,})(\/[^.\n]+\/?)/', $siteUrl, $matches);
            $groups = $matches[1];

            if (empty($groups[0])) {
                $siteUrl .= '/shop';
            }

            $feed = '/?feed='. FeedLoader::FEED_IDENTIFIER .'&hash='. $hash;
            
            return $siteUrl . $feed;
        }


        /**
         * Displays a page or call an action.
         */
        public function displayPage()
        {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $this->doAction();

                wp_redirect($_SERVER['HTTP_REFERER']);
                exit;
            }

            ob_start();


            if (get_option('pricerunner_feed_active') == 1) {
                include (dirname(__FILE__) .'/../views/reset.php');
            } else {
                include (dirname(__FILE__) .'/../views/index.php');
            }

            $content = ob_get_clean();
            return $content;
        }


        /**
         * Perfoms an action
         * @return mixed
         */
        public function doAction()
        {
            if (!array_key_exists('_pr_action', $_POST)) {
                return self::UNKNOWN_ACTION;
            }

            $action = sanitize_text_field($_POST['_pr_action']);
            $methodName = 'action'. $action;

            if (!method_exists($this, $methodName)) {
                return self::UNKNOWN_ACTION;
            }

            return call_user_func(array($this, $methodName));
        }


        /**
         * Calls wp_die() with an error message.
         */
        public function error($message)
        {
            wp_die('<div class="wrap"><div class="error settings-error notice"><p>'. $message .'</p></div></div>');
        }



        /* ACTIONS */

        public function actionEnableFeed()
        {
            $input = $this->sanitize($_POST);

            $errors = $this->validateInputFields($input);

            if (count($errors) != 0) {
                return $this->error($errors[0]);
            }

            update_option('pricerunner_feed_active', 1);
            update_option('pricerunner_feed_url', $input['feed_url']);

            update_option('pricerunner_contact_name', $input['feed_name']);
            update_option('pricerunner_contact_phone', $input['feed_phone']);
            update_option('pricerunner_contact_email', $input['feed_email']);
            update_option('pricerunner_contact_domain', $input['feed_domain']);

            try {

                PricerunnerSDK::postRegistration(
                    $input['feed_name'], 
                    $input['feed_phone'], 
                    $input['feed_email'], 
                    $input['feed_domain'], 
                    $input['feed_url']
                );

            } catch (Exception $e) {

                update_option('pricerunner_feed_active', 0);
                return $this->error(self::API_ERROR);
            }
        }

        public function actionResetFeed()
        {
            update_option('pricerunner_feed_hash', PricerunnerSDK::getRandomString());
            update_option('pricerunner_feed_active', 0);

            delete_option('pricerunner_contact_domain');
            delete_option('pricerunner_contact_name');
            delete_option('pricerunner_contact_email');
            delete_option('pricerunner_contact_phone');
            delete_option('pricerunner_feed_url');
        }


        public function debug()
        {
            // curl
            $curlVersion = curl_version();

            $output = '';


            $feedUrl = get_feed_link(FeedLoader::FEED_IDENTIFIER);
            $parameterChar = '?';
            if (strpos($feedUrl, $parameterChar) !== false) {
                $parameterChar = '&';
            }
            $feedUrl = esc_url($feedUrl . $parameterChar . 'hash=' . get_option('pricerunner_feed_hash'));
            
            $vars = array(
                'WP Version'  => get_bloginfo('version'),
                'WP Site Url' => site_url(),
                'WP Home Url' => home_url(),
                'PHP Version' => phpversion(),
                'Feed URL'    => $feedUrl,
                'Alternate Feed URL' => @(get_site_url() . '/shop/?feed='. FeedLoader::FEED_IDENTIFIER .'&hash='. get_option('pricerunner_feed_hash')),
                'Server Software' => $_SERVER["SERVER_SOFTWARE"],
                'Apache Version' => (function_exists('apache_get_version') ? apache_get_version() : ''),
                'Loaded Ext. ' => print_r(get_loaded_extensions(), true),
                'cURL Version' => $curlVersion['version']
            );

            foreach ($vars as $text => $value) {
                $output .= $text .': '. $value . PHP_EOL;
            }



            // These are the bitfields that can be used 
            // to check for features in the curl build
            $bitfields = array(
                'CURL_VERSION_IPV6', 
                'CURL_VERSION_KERBEROS4', 
                'CURL_VERSION_SSL', 
                'CURL_VERSION_LIBZ'
            );


            foreach($bitfields as $feature) {
                $output .= $feature .': ' . ($curlVersion['features'] & constant($feature) ? ' suppored' : ' not supported');
                $output .= PHP_EOL;
            }


            $base64encoded = base64_encode($output);
            $finalOutput = 'SERVER DEBUG INFO:'. PHP_EOL;
            $finalOutput .= 'Please email us at infodk@pricerunner.com and send this error report.'. PHP_EOL;
            $finalOutput .= PHP_EOL. $base64encoded;

            return $finalOutput;
        }



        /**
         * Simple validation of input fields before we pass them on to the SDK.
         * @param  array $input
         * @return array
         */
        private function validateInputFields($input)
        {
            $errors = array();

            if (empty($input['feed_domain'])) {
                $errors[] = 'Manglende domænenavn.';
            }

            if (empty($input['feed_name'])) {
                $errors[] = 'Manglende navn/firmanavn.';
            }

            if (empty($input['feed_url'])) {
                $errors[] = 'Manglende URL til feed.';
            }

            if (empty($input['feed_phone'])) {
                $errors[] = 'Manglende telefonnummer.';
            }

            if (empty($input['feed_email'])) {
                $errors[] = 'Manglende e-mail adresse.';
            }

            return $errors;
        }

        public function sanitize($input)
        {
            foreach ($input as $key => $value) {

                if ($key == 'feed_email') {
                    $input[$key] = sanitize_email($value);
                    continue;
                }

                $input[$key] = sanitize_text_field($value);
            }

            return $input;
        }
    }

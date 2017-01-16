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

            $action = $_POST['_pr_action'];
            $methodName = 'action'. $action;

            if (!method_exists($this, $methodName)) {
                return self::UNKNOWN_ACTION;
            }

            return call_user_method($methodName, $this);
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
            $errors = $this->validateInputFields($_POST);

            if (count($errors) != 0) {
                return $this->error($errors[0]);
            }

            update_option('pricerunner_feed_active', 1);
            update_option('pricerunner_feed_url', $_POST['feed_url']);

            update_option('pricerunner_contact_name', $_POST['feed_name']);
            update_option('pricerunner_contact_phone', $_POST['feed_phone']);
            update_option('pricerunner_contact_email', $_POST['feed_email']);
            update_option('pricerunner_contact_domain', $_POST['feed_domain']);

            try {
                PricerunnerSDK::postRegistration(
                    $_POST['feed_name'], 
                    $_POST['feed_phone'], 
                    $_POST['feed_email'], 
                    $_POST['feed_domain'], 
                    $_POST['feed_url']
                );
                $success = true;
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
    }

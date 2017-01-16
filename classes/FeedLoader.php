<?php

    namespace Pricerunner;

    use \Exception;
    use Pricerunner\Feed;
    use Pricerunner\Model;
    use PricerunnerSDK\PricerunnerSDK;
    use PricerunnerSDK\Errors\ProductErrorRenderer;
    use Pricerunner\CustomValidator\WooCommerceProductCollectionValidator;

    if (!defined('ABSPATH')) exit;

    class FeedLoader
    {
        const FEED_IDENTIFIER = 'pricerunner-feed';
        const FEED_CONTENT_TYPE = 'application/xml';
        const FEED_TEST_CONTENT_TYPE = 'text/html';

        public static $instance;

        public static function make()
        {
            if (is_null(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }


        private $model;

        public function __construct()
        {
            $this->model = Model::make();
        }


        public function init()
        {
            add_feed(self::FEED_IDENTIFIER, array($this, 'run'));
            add_filter('feed_content_type', array($this, 'header'), 10, 2);
        }


        /**
         * Changes the HTTP response header when loading the feed.
         * 
         * @param string $content_type 
         * @param string $page 
         * 
         * @return string
         */
        public function header($content_type, $page)
        {
            if ($page == self::FEED_IDENTIFIER) {

                $this->clearCache();

                if (array_key_exists('test', $_GET)) {
                    return self::FEED_TEST_CONTENT_TYPE;
                }
                return self::FEED_CONTENT_TYPE;
            }

            return $content_type;
        }


        public function clearCache()
        {
            add_filter('wp_feed_cache_transient_lifetime', array($this, 'setFeedCache'));
        }


        /**
         * Hack to "clear" the feed cache.
         */
        public function setFeedCache($seconds)
        {
            return 1;
        }


        /**
         * Runs the feed and either display the result or an error.
         * @return void
         */
        public function run()
        {
            try {
                $this->preChecks();
            }
            catch (Exception $e) {
                return $this->displayError($e);
            }

            /*
             * Fetches all categories and builds them into a string.
             */
            $_categories = $this->model->getCategories();
            $categories = $this->model->buildCategoryStrings($_categories);

            /*
             * Passed all validations. Grab products and fetch into XML here!
             */
            $feed = $this->model->getProducts($categories);
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

            echo $dataContainer->getXmlString();
            exit;
        }


        /**
         * Detects if WooCommerce is found within the activated plugins.
         * @return bool
         */
        public function isWoocommerceActive()
        {
            $active_plugins = [];

            $active_plugins = is_multisite() ?
                array_keys(get_site_option('active_sitewide_plugins', array())) :
                apply_filters('active_plugins', get_option('active_plugins', array()));

            foreach ($active_plugins as $active_plugin) {
                $active_plugin = explode('/', $active_plugin);
                if (isset($active_plugin[1]) && $active_plugin[1] === 'woocommerce.php') {
                    return true;
                }
            }

            return false;
        }


        /**
         * Validates multiple things that must be valid in order to display the feed.
         * @return void
         * @throws Exception
         */
        private function preChecks()
        {
            // Make sure woocommerce is activated.
            if (!$this->isWoocommerceActive()) {
                throw new Exception('No active webshop.', 1011);
            }

            // Validates the given hash value is the current hash key.
            if (!isset($_GET['hash']) || $_GET['hash'] != get_option('pricerunner_feed_hash')) {
                throw new Exception('Hash key is not valid.', 1012);
            }
        }


        /**
         * Display an error as a XML node.
         * 
         * @param  Exception $error 
         * @return void
         */
        private function displayError(Exception $error)
        {
            echo $this->getXMLHeader();

            echo '<error>' . "\n";
                echo "\t". '<code>'. $error->getCode() .'</code>' . "\n";
                echo "\t". '<message>'. $error->getMessage() .'</message>' . "\n";
            echo '</error>' . "\n";

            echo $this->getXMLFooter();
        }


        /**
         * Returns the XML header string.
         * @return string
         */
        private function getXMLHeader()
        {
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        }


        /**
         * Returns the XML footer string.
         * @return string
         */
        private function getXMLFooter()
        {
            return '';
        }
    }

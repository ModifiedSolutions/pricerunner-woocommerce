<?php

    namespace Pricerunner;

    use WP_Query;
    use stdClass;

    if (!defined('ABSPATH')) exit;

    class Model
    {
        /**
         * Contains our singleton instance.
         * @var \Pricerunner\Model
         */
        public static $instance;

        /**
         * Return a singleton instance of this class.
         * @return \Pricerunner\Model
         */
        public static function make()
        {
            if (is_null(static::$instance)) {
                static::$instance = new static($GLOBALS['wpdb']);
            }

            return static::$instance;
        }


    	/**
    	 * This is the stored $wpdb object directly from Wordpress
    	 * 
    	 * @var object
    	 */
    	private $db;

    	/**
    	 * Used to hold the active categories for each particular product. Gets reset for every product that's being looped.
    	 * 
    	 * @var array
    	 */
    	private $category;

        /**
         * Used to recall parental product data inside of loops.
         *
         * @var array
         */
        private $sanitizedProducts;

    	/**
    	 * Class constructor.
    	 * Using Wordpress' global $wpdb object for mysql-querying.
    	 * 
    	 * @return void
    	 */
    	public function __construct($db)
    	{
    		$this->db = $db;
            $this->sanitizedProducts = array();
            $this->category = array();
    	}


        /**
         * Generates categories.
         *
         * @param int $productId  
         * @return void
         */
        private function generateCategories($productId)
        {
            $terms = wc_get_product_terms($productId, 'product_cat', array('orderby' => 'parent', 'order' => 'DESC'));
            if (empty($terms)) {
                return;
            }

            $mainTerm = apply_filters('woocommerce_breadcrumb_main_term', $terms[0], $terms);

            $this->categoryAncestors($mainTerm->term_id, 'product_cat');
            $this->addCategory($mainTerm->name, $mainTerm->term_id, $mainTerm->parent);
        }


        /**
         * Fetches category ancestors and adds them to the category property.
         * 
         * @param int $term_id 
         * @param mixed $taxonomy 
         * 
         * @return void
         */
        private function categoryAncestors($term_id, $taxonomy)
        {
            $ancestors = get_ancestors($term_id, $taxonomy);
            $ancestors = array_reverse($ancestors);

            foreach ($ancestors as $ancestor) {
                $ancestor = get_term($ancestor, $taxonomy);

                if (!is_wp_error($ancestor) && $ancestor) {
                    $this->addCategory($ancestor->name, $ancestor->term_id, $ancestor->parent);
                }
            }
        }


        /**
         * Add category to the category property.
         *
         * @param string $name
         * @param int $id
         * @param int $parent
         */
        public function addCategory($name, $id, $parent)
        {
            $this->category[] = array(
                'name'   => strip_tags($name),
                'id'     => $id,
                'parent' => $parent
            );
        }


        /**
         * Builds an array of categories in a single dimensional structure.
         * 
         * @return \stdClass
         */
        public function buildCategory()
        {
            $categories = $this->category;
            $categoryString = '';

            if (empty($categories)) {
                $dummyObj = new stdClass;
                $dummyObj->name = '';
                $dummyObj->id = 0;
                $dummyObj->parentId = 0;

                return $dummyObj;
            }

            foreach ($categories as $_category) {
                $categoryString .= $_category['name'] .' > ';
            }

            $categoryString = mb_substr($categoryString, 0, -3);
            $revCategories = array_reverse($categories);

            $this->category = array();

            $obj = new stdClass;
            $obj->name     = $categoryString;
            $obj->id       = $revCategories[0]['id'];
            $obj->parentId = $revCategories[0]['parent'];

            return $obj;
        }


    	/**
    	 * Get all of the active products. Listed in a format that suits Pricerunner's requirements.
    	 * Further description below.
    	 *
         * @param array $categories
         *
    	 * @return 	array
    	 */
    	public function getProducts($categories)
        {
            /**
             * Pricerunner specifications: http://www.pricerunner.dk/krav-til-produktfilen.html
             * 
             * - REQUIRED FIELDS
             * Category             Electronic > Digital Cameras
             * Product Name         EOS 650D
             * SKU                  ABC123
             * Currency             $, €, DKK... (Don't set this for now. We're assuming it's DKK)
             * Price                1333.37
             * Shipping Cost        5 (Only add if it's a flat rate - i.e. shipping cost for a specific product is equal over the whole country)
             * Product URL          https://www.site.com/product/example-1
             * 
             * - REQUIRED FOR AUTOMATIC MATCHING OF PRODUCTS. WITHOUT THESE LISTINGS MIGHT BE DELAYED OR EVEN PREVENTED
             * Manufacturer SKU
             * Manufacturer         Canon
             * EAN or UPC           8714574585567
             * 
             * - OTHER FIELDS
             * Description          Product description right here
             * Image URL            https://www.site.com/images/product-image-1.jpg
             * Stock Status         In Stock / Out of Stock / Preorder
             * Delivery Time        Delivers in 5-7 days
             * Retailer Message     Free shipping until... (Max 125 characters)
             * Product State        New / Used / Refurbished / Open Box
             * ISBN                 0563389532 (REQUIRED FOR BOOK RETAILERS)
             * Catalog Id           73216 (Only for CDs, DVDs, HD-DVDs and Blu-Ray films)
             * Warranty             1 year warranty (Keep under 25 characters if possible - max supported: 70)
             */

            $args = array(
                'post_type' => ['product', 'product_variation'],
                'posts_per_page' => -1
            );

            $getProducts = new WP_Query($args);
            $products = array();

            while ($getProducts->have_posts()) {
                $getProducts->the_post(); 
                global $product;

                $_product = new stdClass();

                $_product->id = $product->id;
                $_product->parentId = $product->variation_id;
                $_product->postType = $product->post->post_type;
                $_product->postStatus = $product->post->post_status;
                $_product->productName = $product->post->post_title;
                $_product->slug = $product->post->post_name;
                $_product->description = $product->post->post_excerpt;
                $_product->content = $product->post->post_content;

                $this->sanitizedProducts[$_product->id] = $_product;
                unset($product);

                if ($_product->parentId != 0) {
                    // For now we exclude generation of variant products as single entities.
                    continue;
                }

                $products[] = $this->createPricerunnerProduct($_product);

                unset($_product);
            }

            return $products;
        }


    	/**
    	 * Every product needs to run through this function so the SDK can validate them correctly.
    	 * We retain the variant building ability for this method, despite it being temporarily disabled.
         *
    	 * @param 	object 	$product
    	 * @return 	\PricerunnerSDK\Models\Product
    	 */

    	public function createPricerunnerProduct($product)
    	{
    		if ($product->parentId == 0) {
                $realIdForData = $product->id;
    		} else {
                $realIdForData = $this->sanitizedProducts[$product->id]->id;
            }

            $pricerunnerProduct = new \PricerunnerSDK\Models\Product();

    		// Get product specific data from another table
    		$metaData = $this->getProductData($product->id);

            foreach ($metaData as $_meta) {
                
                $metaKey = $_meta->meta_key;
                $metaValue = $_meta->meta_value;

                switch ($metaKey) {
                    case '_price':
                        $_price = str_replace(',', '.', $metaValue);
                        $_price = sprintf("%.2F", $_price);
                        $pricerunnerProduct->setPrice($_price);
                        break;

                    case '_stock_status':
                        $_stockStatus = $metaValue == 'instock' ? 'In Stock' : 'Out of Stock';
                        $pricerunnerProduct->setStockStatus($_stockStatus);
                        break;

                    case '_sku':
                        $pricerunnerProduct->setSku($metaValue);
                        break;
                }
            }

            $this->generateCategories($product->id);
            $category = $this->buildCategory()->name;
            
            if(!empty($category)) {
                $pricerunnerProduct->setCategoryName($category);
            }

            if ($product->parentId == 0) {
                $productName = $product->productName;
            } else {
                $productName = $this->sanitizedProducts[$realIdForData]->productName;
            }
            $pricerunnerProduct->setProductName($productName);


    		$pricerunnerProduct->setShippingCost('');
    		$pricerunnerProduct->setProductUrl(get_bloginfo('wpurl') .'/?product='. $product->slug);

            if ($product->parentId != 0) {
                $product->description = $this->sanitizedProducts[$realIdForData]->description;
                $product->content = $this->sanitizedProducts[$realIdForData]->content;
            }

            if (!empty($product->description)){
                $pricerunnerProduct->setDescription(\PricerunnerSDK\PricerunnerSDK::getXmlReadyString($product->description));
            } elseif (!empty($product->content)){
                $pricerunnerProduct->setDescription(\PricerunnerSDK\PricerunnerSDK::getXmlReadyString($product->content));
            }

            // If image URL is empty, then we're probably looking up on a variant that has no image.
            if ($product->parentId == 0) {
                $getImageUrl = wp_get_attachment_url(get_post_thumbnail_id($product->id));
            } else {
                $getImageUrl = wp_get_attachment_url(get_post_thumbnail_id($realIdForData));
            }
    		$pricerunnerProduct->setImageUrl($getImageUrl);

            // Woocommerce has no defaults for us to determine the these values from.
    		$pricerunnerProduct->setManufacturerSku('');
            $pricerunnerProduct->setManufacturer('');
            $pricerunnerProduct->setEan('');
            $pricerunnerProduct->setDeliveryTime('');
            $pricerunnerProduct->setProductState('New');

    		return $pricerunnerProduct;
    	}


    	/**
    	 * Get product specific data.
    	 * 
    	 * @param 	int 	$id 
    	 * @return 	array
    	 */
    	public function getProductData($id)
    	{
            $sql = "
            SELECT
    			`meta_key`,
    			`meta_value`
    		FROM
    			`". $this->db->prefix ."postmeta`
    		WHERE
    			`post_id` = '". $id ."'
    			AND `meta_key` IN ('_price', '_stock_status', '_sku')
            ";

    		return $this->getResults($sql);
    	}


    	public function getCategories()
    	{
    		$sql = "
    			SELECT tt.`term_taxonomy_id` AS id, tt.`parent`, t.`name`
    			FROM `". $this->db->prefix ."term_taxonomy` AS tt
    			INNER JOIN `". $this->db->prefix ."terms` AS t ON t.`term_id` = tt.`term_id`
    			WHERE tt.`taxonomy` = 'product_cat'
    			ORDER BY tt.`parent` ASC
    		";

    		$results = $this->getResults($sql);
    		$categories = array();

    		foreach ($results as $key => $category) {
    			$categories[$category->id] = $category;
    		}

    		return $categories;
    	}


        /**
         * @param   array   $categories 
         * @return  array
         */
        public function buildCategoryStrings($categories)
        {
            $categoryStringArray = array();
            
            foreach ($categories as $id => $category) {
                if ($category->parent == 0) {
                    $categoryStringArray[$id] = $category->name;
                } else {
                    // Fix for orphans.
                    $parent = isset($categoryStringArray[$category->parent]) ? $categoryStringArray[$category->parent] . ' > ' : '';
                    $categoryStringArray[$id] = $parent . $category->name;
                }
            }

            return $categoryStringArray;
        }


    	/**
    	 * Use Wordpress' $wpdb object to create a query.
    	 * 
    	 * @param 	string 	$query 
    	 * @return 	array
    	 */
    	private function query($query)
    	{
    		return $this->db->query($query);
    	}


    	/**
    	 * Use Wordpress' $wpdb object to get results.
    	 *
    	 * @param 	string 	$query 
    	 * @return 	array
    	 */
    	private function getResults($query)
    	{
    		return $this->db->get_results($query, OBJECT);
    	}
    	
    }

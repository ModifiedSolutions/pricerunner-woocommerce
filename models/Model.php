<?php

class Model
{
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
	 * Used to hold all categories from the database.
	 * 
	 * @var array
	 */
	private $categories;

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

		$this->categories = $this->getCategoriesFromDatabase();

        $this->sanitizedProducts = array();
	}

	/**
	 * Get the current active feed.
	 * 
	 * @param 	string 	$path 
	 * 
	 * @return 	array
	 */
	public function getActiveFeed($path)
	{
		$sql = "SELECT *
		FROM
			`". $this->db->prefix ."pricerunner_feeds`
		WHERE
			`feed_url` = '". $path ."'
		ORDER BY
			`id` DESC
		LIMIT 1";

		return $this->getResults($sql)[0];
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
		 * Category 			Electronic > Digital Cameras
		 * Product Name 		EOS 650D
		 * SKU 					ABC123
		 * Currency 			$, €, DKK... (Don't set this for now. We're assuming it's DKK)
		 * Price 				1333.37
		 * Shipping Cost 		5 (Only add if it's a flat rate - i.e. shipping cost for a specific product is equal over the whole country)
		 * Product URL 			https://www.site.com/product/example-1
		 * 
		 * - REQUIRED FOR AUTOMATIC MATCHING OF PRODUCTS. WITHOUT THESE LISTINGS MIGHT BE DELAYED OR EVEN PREVENTED
		 * Manufacturer SKU
		 * Manufacturer 		Canon
		 * EAN or UPC 			8714574585567
		 * 
		 * - OTHER FIELDS
		 * Description 			Product description right here
		 * Image URL 			https://www.site.com/images/product-image-1.jpg
		 * Stock Status 		In Stock / Out of Stock / Preorder
		 * Delivery Time 		Delivers in 5-7 days
		 * Retailer Message 	Free shipping until... (Max 125 characters)
		 * Product State 		New / Used / Refurbished / Open Box
		 * ISBN 				0563389532 (REQUIRED FOR BOOK RETAILERS)
		 * Catalog Id 			73216 (Only for CDs, DVDs, HD-DVDs and Blu-Ray films)
		 * Warranty 			1 year warranty (Keep under 25 characters if possible - max supported: 70)
		 */

		$sql = "
        SELECT
            p.ID AS id,
            p.post_parent AS parentId,
            p.post_type AS postType,
            p.post_status AS postStatus,
            p.post_title AS productName,
            p.post_name AS slug,
            p.post_excerpt AS description,
            p.post_content AS content,
            tr.term_taxonomy_id AS categoryId
		FROM
            ". $this->db->prefix ."posts AS p
            
		LEFT JOIN (
            SELECT object_id, MAX(term_taxonomy_id) AS term_taxonomy_id
            FROM ". $this->db->prefix ."term_relationships
            GROUP BY object_id
            ) AS tr ON tr.object_id = p.id
            
		LEFT JOIN ". $this->db->prefix ."term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id

		WHERE
            (p.post_type = 'product' OR p.post_type = 'product_variation')
            AND p.post_status = 'publish'
            AND (
                CASE WHEN p.post_type = 'product'
                     THEN tt.taxonomy = 'product_cat'
                     ELSE TRUE = TRUE
                END
            )
            
		ORDER BY
            p.id, p.post_parent ASC";

		$getProducts = $this->getResults($sql);

        foreach ($getProducts as $product){
            $this->sanitizedProducts[$product->id] = $product;
        }
        unset($product);

        $products = [];
		foreach ($getProducts as $product) {

			$product->category = $categories[$product->categoryId];

            if ($product->parentId == 0) {

                $prProduct = $this->createPricerunnerProduct($product);

			} else {
                // For now we exclude generation of variant products as single entities.
                continue;
			}

			$products[] = $prProduct;
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
		$data = $this->getProductData($product->id);
        if (!empty($data)){
            for ($i = 0; $i < count($data); $i++) {
                if ($data[$i]->meta_key == '_price') {
                    $pricerunnerProduct->setPrice($data[$i]->meta_value);
                }

                if ($data[$i]->meta_key == '_stock_status') {
                    $stockStatus = $data[$i]->meta_value == 'instock' ? 'In Stock' : 'Out of Stock';
                    $pricerunnerProduct->setStockStatus($stockStatus);
                }
            }
        }

        if ($product->parentId == 0) {
            $category = $product->category;
        } else {
            $category = $this->sanitizedProducts[$realIdForData]->category;
        }
        $pricerunnerProduct->setCategoryName($category);

        if ($product->parentId == 0) {
            $productName = $product->productName;
        } else {
            $productName = $this->sanitizedProducts[$realIdForData]->productName;
        }
        $pricerunnerProduct->setProductName($productName);

		$pricerunnerProduct->setSku($product->id);
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
        $pricerunnerProduct->setRetailerMessage('');
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
			AND (`meta_key` = '_price' OR `meta_key` = '_stock_status')
        ";

		return $this->getResults($sql);
	}

	/**
	 * Return active category (with parent categories) in a breadcrumb format
	 * 
	 * @param 	int 	$productId 
	 * @return 	string
	 */
	public function fetchProductCategory($productId)
	{
		// Reset the category-string
		$this->category = array();

		// Get the active category for this product
		$sql = "SELECT
			tr.`term_taxonomy_id` AS `id`
		FROM
			`". $this->db->prefix ."term_relationships` AS `tr`
		INNER JOIN
			`". $this->db->prefix ."term_taxonomy` AS `tt`
			ON tt.`term_taxonomy_id` = tr.`term_taxonomy_id`
		WHERE
			tr.`object_id` = '". $productId ."' AND
			tt.`taxonomy` = 'product_cat'";

		// Run query to get the active category id
		$query = $this->getResults($sql);

		if (count($query) == 0) {
			return false;
		}

		// Run our recursive method to get the parent/child categories
		$this->getCategoryFromArray($query[0]->id);

		// Reverse the result for correct parent/child showing and return the imploded value
		return implode(' > ', array_reverse($this->category));
	}

	/**
	 * Recursively run through our categories object and return parent/child listing
	 * 
	 * @param 	int 	$id 
	 * @return 	void
	 */
	private function getCategoryFromArray($id)
	{
		if (array_key_exists($id, $this->categories)) {
			$this->category[] = $this->categories[$id]['name'];

			// If parent is not 0 then run this method again recursively
			if ($this->categories[$id]['parent'] != 0) {
				$this->getCategoryFromArray($this->categories[$id]['parent']);
			}
		}
	}

	/**
	 * Get all categories from the database and put into a property for later use.
	 * 
	 * @return 	array
	 */
	public function getCategoriesFromDatabase()
	{
		$sql = "SELECT
			t.`name`,
			tt.`term_taxonomy_id` AS `id`,
			tt.`parent`
		FROM
			`". $this->db->prefix ."terms` AS `t`

		INNER JOIN
			`". $this->db->prefix ."term_taxonomy` AS `tt`
			ON tt.`term_id` = t.`term_id`

		WHERE
			tt.`taxonomy` = 'product_cat'";

		$query = $this->getResults($sql);

		$categories = array();

		foreach ($query as $key => $value) {
			$categories[$value->id] = array(
				'name' 		=> $value->name,
				'parent' 	=> $value->parent
			);
		}

		return $categories;
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
	 * @param 	array 	$categories 
	 * @return 	array
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
	 * Save contact informations to the database when activate button has been clicked
	 * 
	 * @param 	string 	$domain 
	 * @param 	string 	$name 
	 * @param 	string 	$url 
	 * @param 	string 	$phone 
	 * @param 	string 	$email 
	 * 
	 * @return 	array
	 */
	public function saveContactInformations($domain, $name, $url, $phone, $email) 
	{
		$db = $this->db;

		$domain = $db->_real_escape($domain);
		$name = $db->_real_escape($name);
		$url = $db->_real_escape($url);
		$phone = $db->_real_escape($phone);
		$email = $db->_real_escape($email);

		$sql = "INSERT INTO `". $db->prefix ."pricerunner_feeds`
		(`domain`, `name`, `feed_url`, `phone`, `email`, `created_at`) VALUES
		('". $domain ."', '". $name ."', '". $url ."', '". $phone ."', '". $email ."', NOW())";

		$this->query($sql);
	}

	/**
	 * The DB installer.
	 * 
	 * @return 	void
	 */
	public function install()
	{
		$db = $this->db;

		// Define table name
		$tableName = $db->prefix .'pricerunner_feeds';

		// This is not supported pre-version 3.5. We have to create the collation string manually if we want to support earlier versions...
		$charsetCollate = $db->get_charset_collate();

		$sql = "CREATE TABLE ". $tableName ." (
			id int(11) NOT NULL AUTO_INCREMENT,
			domain varchar(64) DEFAULT '' NOT NULL,
			name varchar(64) DEFAULT '' NOT NULL,
			feed_url varchar(255) DEFAULT '' NOT NULL,
			phone varchar(32) DEFAULT '' NOT NULL,
			email varchar(255) DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) ". $charsetCollate .";";

		// Load WP file to run this SQL query
		require_once (ABSPATH .'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	public function uninstall()
	{
		$tableName = $this->db->prefix .'pricerunner_feeds';
		$sql = "DROP TABLE IF EXISTS `". $tableName ."`";

		$this->db->query($sql);
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
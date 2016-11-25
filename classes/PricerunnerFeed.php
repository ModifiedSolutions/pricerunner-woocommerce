<?php

require_once(dirname(__FILE__) . '/../models/Model.php');
require_once(dirname(__FILE__) . '/../pricerunner-php-sdk/src/files.php');
require_once(dirname(__FILE__) . '/../CustomValidator/WooCommerceProductValidator.php');
require_once(dirname(__FILE__) . '/../CustomValidator/WooCommerceProductCollectionValidator.php');

use PricerunnerSDK\PricerunnerSDK;

class PricerunnerFeed
{

	/**
	 * Our Model class
	 * 
	 * @var object
	 */
	private $model;

	/**
	 * Class constructor.
	 * Using Wordpress' global $wpdb object for mysql-querying.
	 *
	 * @return 	void
	 */
	public function  __construct()
	{
		$this->model = new Model($GLOBALS['wpdb']);
	}

	/**
	 * Main function to launch everything!
	 *
	 * @return 	void
	 */
	public function init()
	{
		// Reset Pricerunner Feed - set new hash and active to 0
		if (isset($_POST['pr_feed_reset'])) {
			update_option('pricerunner_feed_hash', PricerunnerSDK::getRandomString());
			update_option('pricerunner_feed_active', 0);
		}

		$feedHash = get_option('pricerunner_feed_hash');

		$feedPath = plugins_url('pricerunner-feed') .'/feed.php?hash='. $feedHash;

		// Feed activation
		if (isset($_POST['pr_feed_submit'])) {
			$errors = $this->validateInputFields($_POST);

			if (count($errors) == 0) {
				update_option('pricerunner_feed_active', 1);

				try {
		            PricerunnerSDK::postRegistration($_POST['feed_name'], $_POST['feed_phone'], $_POST['feed_email'], $_POST['feed_domain'], $_POST['feed_url']);

		            $success = true;
		        } catch (Exception $e) {
		            $errors = array('An error occured when posting to the API. Please try again!');
		        }
				
			}
		}

		ob_start();

		// Check if there's an active feed - render different viewfiles
		if (get_option('pricerunner_feed_active') == 1) {
			$activeFeed = $this->model->getActiveFeed($feedPath);
			include (dirname(__FILE__) .'/../views/reset.php');
		} else {
			include (dirname(__FILE__) .'/../views/index.php');
		}

		$content = ob_get_clean();

		echo $content;
	}

	/**
	 * Used in feed.php to generate product feeds.
	 * 
	 * @return 	array
	 */	
	public function getProducts($categories)
	{
		return $this->model->getProducts($categories);
	}

	public function getCategories()
	{
		$categories = $this->model->getCategories();
		return $this->model->buildCategoryStrings($categories);
	}

	/**
	 * Simple validation of input fields before we pass them on to the SDK.
	 * 
	 * @param 	array 	$input 
	 * 
	 * @return 	array
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

		if (count($errors) == 0) {
			$this->model->saveContactInformations($input['feed_domain'], $input['feed_name'], $input['feed_url'], $input['feed_phone'], $input['feed_email']);
		}

		return $errors;
	}

	/**
	 * Run this function on first time installs and DB changes.
	 * 
	 * @return 	void
	 */
	public function install($dbVersion)
	{
		// Install our MySQL table.
		$this->model->install();

		// Set the database version for this plugin.
		update_option('pricerunner_db_version', $dbVersion);

		// Set a random string as the unique hash identifier. This is used later by Pricerunner to access the product feed in XML-format
		update_option('pricerunner_feed_hash', PricerunnerSDK::getRandomString());
	}


	public function uninstall()
	{
		$this->model->uninstall();
		
		delete_option('pricerunner_feed_hash');
		delete_option('pricerunner_feed_active');
	}

}
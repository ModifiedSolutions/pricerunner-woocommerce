<?php
	if (!defined('ABSPATH')) exit;

	$nonce   = wp_create_nonce('pricerunner_form');
	$postUrl = admin_url('admin.php?page=pricerunner-xml-feed');

	$domain  = esc_url(get_option('pricerunner_contact_domain'));
	$name    = esc_html(get_option('pricerunner_contact_name'));
	$email   = esc_html(get_option('pricerunner_contact_email'));
	$phone   = esc_html(get_option('pricerunner_contact_phone'));
	$feedUrl = esc_url(get_option('pricerunner_feed_url'));

?>

<div class="wrap">
	
	<form method="post" action="<?php echo $postUrl; ?>">

		<input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>" />
		<input type="hidden" name="_pr_action" value="ResetFeed" />
		
		<h1>Pricerunner XML Feed</h1>

		<?php if (isset($success)): ?>
			<div id="setting-error-settings_updated" class="updated settings-error notice">
				<p>
					Thank you.<br>
					Your feed has been activated and Pricerunner have been notified about this. Your request must be manually approved before your shop will be monitored.<br>
					<br>
					Please do not reset your feed unless you want Pricerunner to stop monitoring your shop.<br>
					By resetting and reactivating your feed a new Feed URL will be generated.
				</p>
			</div>
		<?php endif; ?>

		<table class="form-table">
			
			<tr>
				<th>
					<label for="feed_domain">Domain</label>
				</th>
				<td>
					<?= $domain; ?>
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_domain">Name/Company Name</label>
				</th>
				<td>
					<?= $name; ?>
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_url">Feed URL</label>
				</th>
				<td>
					<a href="<?= $feedUrl; ?>" target="_blank"><?= $feedUrl; ?></a>
					<br />
					<small>The feed is updated automatically so you don't have to do anything to get new updates into the feed.</small>
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_url">Phone</label>
				</th>
				<td>
					<?= $phone; ?>
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_url">E-mail</label>
				</th>
				<td>
					<?= $email; ?>
				</td>
			</tr>

		</table>

		<p>
			If you want to reset the feed Pricerunner will stop monitoring your shop. Upon reactivation a new feed URL will be generated, and your request must be manually approved by Pricerunner again.
		</p>

		<button onclick="return confirm('Are you sure you want to reset the feed?');" type="submit" class="button" name="pr_feed_reset">Reset Pricerunner Feed</button>
		&emsp;
		<a href="<?= $feedUrl; ?>&amp;test=1" target="_blank" onclick="return confirm('This operation might take a while, do you want to continue?');" class="button button-primary">Run Feed Test</a>
		&emsp;
		<button class="button" type="button" id="pricerunnerErrorReportingButton">Error reporting</button>
	</form>

	<div id="pricerunnerErrorReportingContainer" style="display: none">
		<textarea readonly rows="30" cols="100"><?php echo Pricerunner\Plugin::make()->debug(); ?></textarea>
	</div>

</div>


<script>
jQuery(document).ready(function()
{
	jQuery('#pricerunnerErrorReportingButton').click(function(e)
	{
		jQuery('#pricerunnerErrorReportingContainer').toggle();
	});
});
</script>


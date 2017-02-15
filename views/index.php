<?php

	if (!defined('ABSPATH')) exit;

	$nonce = wp_create_nonce('pricerunner_form');
	$postUrl = admin_url('admin.php?page=pricerunner-xml-feed');
	$feedUrl = esc_url(Pricerunner\Plugin::make()->generateFeedUrl());

?>
<div class="wrap">
	
	<form method="post" action="<?php echo esc_url($postUrl); ?>">

		<input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>" />
		<input type="hidden" name="_pr_action" value="EnableFeed" />

		<h1>Pricerunner XML Feed</h1>

		<table class="form-table">
			
			<tr>
				<th>
					<label for="feed_domain">Domain</label>
				</th>
				<td>
					<input type="text" name="feed_domain" id="feed_domain" value="<?= get_site_url(); ?>" readonly>
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_name">Name/Company Name</label>
				</th>
				<td>
					<input type="text" name="feed_name" id="feed_name" value="<?= get_bloginfo(); ?>">
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_url">Feed URL</label>
				</th>
				<td>
					<input type="text" name="feed_url" id="feed_url" value="<?= $feedUrl; ?>" readonly>
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_phone">Phone</label>
				</th>
				<td>
					<input type="text" name="feed_phone" id="feed_phone">
				</td>
			</tr>

			<tr>
				<th>
					<label for="feed_email">E-mail</label>
				</th>
				<td>
					<input type="text" name="feed_email" id="feed_email" value="<?= esc_html(get_option('admin_email')); ?>">
				</td>
			</tr>

		</table>
		
		<button class="button button-primary" type="submit" name="pr_feed_submit">Activate</button>
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


<link rel="stylesheet" href="<?= plugins_url( '' , dirname(__FILE__) ); ?>/assets/css/styles.css">

<div class="wrap">
	
	<form method="POST">

		<h1>Pricerunner XML Feed</h1>

		<?php if (isset($errors) && count($errors) > 0): ?>
			<div id="setting-error-invalid_siteurl" class="error settings-error notice">
				<p>
				<?php foreach ($errors as $error): ?>
					- <strong><?= $error ?></strong><br>
				<?php endforeach; ?>
				</p>
			</div>
		<?php endif; ?>

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
					<input type="text" name="feed_url" id="feed_url" value="<?= $feedPath; ?>" readonly>
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
					<input type="text" name="feed_email" id="feed_email" value="<?= get_option('admin_email'); ?>">
				</td>
			</tr>

		</table>
		
		<button class="button button-primary" type="submit" name="pr_feed_submit">Aktiver</button>

	</form>

</div>
<?php
if (!defined('WP_CONTENT_URL')) die;

add_action('admin_menu', 'mce_admin_menu');

function mce_admin_menu() {
	add_submenu_page('options-general.php', 'My Comments Elsewhere', 'My Comments Elsewhere', 8, 'My Comments Elsewhere', 'mce_submenu');
}

function mce_submenu() {
	global $mce_options;

	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
			
	if (!empty($_POST)) {
		check_admin_referer('my-comments-elsewhere');
	}
	
	if (!empty($_POST['mce_do_save']) || !empty($_POST['mce_do_import'])) {
		require MCE_DIR . '/import.php';
	}	
				
	if (!empty($_POST['mce_do_choose'])) {
		if ($_POST['service'] == 'backtype' || $_POST['service'] == 'cocomment') {
			$mce_options['type'] = $_POST['service'];
			
			update_option('mce_options', $mce_options);
			
			echo '<div id="message" class="updated fade"><p>' . __('Service chosen successfully, now please fill in the details below.', 'mce') . '</p></div>' . "\n";		
		} else {
			echo '<div id="message" class="error"><p>' . __('Incorrect service selected.', 'mce') . '</p></div>';
		}
	} else if (!empty($_POST['mce_do_save'])) {		
		if ($mce_options['username'] != $_POST['username'] || (isset($_POST['api_key']) && $mce_options['api_key'] != $_POST['api_key'])) {
			$verify_credentials = true;
		} else {
			$verify_credentials = false;
		}
		
		$old_username 		 = $mce_options['username'];
		$old_filter_own_blog = $mce_options['filter_own_blog'];
		
		$mce_options['username'] = trim($_POST['username']);
		
		if (isset($_POST['api_key'])) {
			$mce_options['api_key'] = trim($_POST['api_key']);
		}
				
		if ($verify_credentials) {
			$report = mce_verify_credentials();
		} else {
			$report = array('success' => 1);
		}
		
		if (!$report['success']) {
			echo '<div id="message" class="error"><p>' . __($report['message'], 'mce') . '</p></div>';
		} else {
			$mce_options['filter_own_blog']			 = (int) $_POST['filter_own_blog'];
			$mce_options['dofollow_links'] 		     = (int) $_POST['dofollow_links'];
			$mce_options['open_links_in_new_window'] = (int) $_POST['open_links_in_new_window'];
			$mce_options['include_action_links']	 = (int) $_POST['include_action_links'];
			$mce_options['date_format']				 = (int) $_POST['date_format'];
			$mce_options['comments_per_page']		 = (int) $_POST['comments_per_page'];
			$mce_options['rss_feed'] 				 = (int) $_POST['rss_feed'];
			
			if ($mce_options['comments_per_page'] < 1) {
				$mce_options['comments_per_page'] = 20;
			} else if ($mce_options['comments_per_page'] > 200) {
				$mce_options['comments_per_page'] = 200;
			}
			
			if ($mce_options['type'] == 'backtype') {
				$mce_options['rss'] = 'http://feeds.backtype.com/' . urlencode($mce_options['username']);
			} else {
				$mce_options['rss'] = 'http://www.cocomment.com/myRss/' . urlencode($mce_options['username']) . '.rss';
			}
			
			$allowed_timeframes = array('hourly', 'daily', 'weekly');
			
			$mce_options['import_frequency'] = trim($_POST['import_frequency']);
			
			if (!in_array($mce_options['import_frequency'], $allowed_timeframes)) {
				$mce_options['import_frequency'] = 'daily';
			}
			
			if ($old_username && $old_username != $mce_options['username']) {
				$mce_options['initial_import'] = false;
			}
			if ($old_filter_own_blog != $mce_options['filter_own_blog'] && $mce_options['type'] == 'backtype') {
				$mce_options['initial_import'] = false;
			}
			
			update_option('mce_options', $mce_options);
			
			wp_clear_scheduled_hook('mce_import_cron');

			if ($_POST['import_frequency'] == 'hourly') {
				$time = mktime(date('H') + 1, 0, 0); //strtotime('+1 hour')
				wp_schedule_event($time, 'hourly', 'mce_import_cron');
			} else if ($_POST['import_frequency'] == 'daily') {
				$time = mktime(0, 0, 0, date('n'), date('j') + 1, date('Y')); //strtotime('tomorrow')
				wp_schedule_event($time, 'daily', 'mce_import_cron');
			} else {
				$days_until_monday = array(0 => 1, 1 => 7, 2 => 6, 3 => 5, 4 => 4, 5 => 3, 6 => 2);
				$extra_days		   = $days_until_monday[date('w')];
				$time			   = mktime(0, 0, 0, date('n'), date('j') + $extra_days, date('Y')); //strtotime('Next monday')
				wp_schedule_event($time, 'weekly', 'mce_import_cron');
			}
			
			if (empty($mce_options['initial_import'])) {
				if ($mce_options['last_import']) {
					$message = 'You will have to <a href="#initial_import">reimport your comments</a>.';
				} else {
					$message = 'Now please start the <a href="#initial_import">initial import</a> below.';
				}
			} else {
				$message = '';
			}
			
			echo '<div id="message" class="updated fade"><p>' . __('Settings saved successfully. ' . $message, 'mce') . '</p></div>' . "\n";		
		}
	} else if (!empty($_POST['mce_do_import'])) {
		$report = mce_import(true);
		
		if (!$report['success']) {
			echo '<div id="message" class="error"><p>' . __($report['message'], 'mce') . '</p></div>';
		} else {
			echo '<div id="message" class="updated fade"><p>' . __($report['comments_count'] . ' comments imported successfully.' . (empty($mce_options['page_url']) ? ' You should now <a href="#create_the_page">create the comments page</a>. Alternatively, you can set up <a href="#digest_posts">daily/weekly digest posts</a>.' : ''), 'mce') . '</p></div>' . "\n";		
		}
	} else if (!empty($_POST['mce_do_acknowledgements'])) {
		$mce_options['itw_link']     = !empty($_POST['itw_link']);
		$mce_options['service_link'] = !empty($_POST['service_link']);
		
		update_option('mce_options', $mce_options);
		
		if ($mce_options['itw_link'] && $mce_options['service_link']) {
			echo '<div id="message" class="updated fade"><p>' . __('Thank you!', 'mce') . '</p></div>' . "\n";		
		} else {
			echo '<div id="message" class="error"><p>' . __('This plugin is sad :( Please consider giving credit.', 'mce') . '</p></div>';
		}
	} else if (!empty($_POST['mce_do_reset'])) {
		require MCE_DIR . '/install.php';
		
		$mce_options = mce_default_options();
		
		update_option('mce_options', $mce_options);
	} else if (!empty($_POST['mce_do_digest'])) {
		if ($_POST['digest_frequency'] != 'daily' && $_POST['digest_frequency'] != 'weekly') {
			$_POST['digest_frequency'] = 'never';
		}
		if (!trim($_POST['digest_title'])) {
			$_POST['digest_title'] = 'My comments elsewhere: [date]';
		}
		
		$mce_options['digest_frequency']    = $_POST['digest_frequency'];
		$mce_options['digest_title']	    = strip_tags($_POST['digest_title']);
		$mce_options['digest_order']		= (int) $_POST['digest_order'];
		$mce_options['digest_max_comments'] = (int) $_POST['digest_max_comments'];
		$mce_options['digest_min_comments'] = (int) $_POST['digest_min_comments'];
		$mce_options['digest_category']	   = (int) $_POST['digest_category'];
		$mce_options['digest_tags']		   = strip_tags($_POST['digest_tags']);
	
		if ($mce_options['digest_max_comments'] < 0) {
			$mce_options['digest_max_comments'] = 0;
		}
		if ($mce_options['digest_min_comments'] < 1) {
			$mce_options['digest_min_comments'] = 1;
		}
		
		update_option('mce_options', $mce_options);
		
		wp_clear_scheduled_hook('mce_digest_cron');
		
		if ($_POST['digest_frequency'] == 'daily') {
			$time = mktime(1, 0, 0, date('n'), date('j') + 1, date('Y')); //strtotime('tomorrow')
			wp_schedule_event($time, 'daily', 'mce_digest_cron'); 
		} else if ($_POST['digest_frequency'] == 'weekly') {
			$days_until_monday = array(0 => 1, 1 => 7, 2 => 6, 3 => 5, 4 => 4, 5 => 3, 6 => 2);
			$extra_days		   = $days_until_monday[date('w')];
			$time			   = mktime(1, 0, 0, date('n'), date('j') + $extra_days, date('Y')); //strtotime('Next monday')
			wp_schedule_event($time, 'weekly', 'mce_digest_cron');
		}
				
		echo '<div id="message" class="updated fade"><p>' . __('Digest options updated successfully.', 'mce') . '</p></div>' . "\n";		
	} else if (!empty($_POST['mce_do_css'])) {
		require MCE_DIR . '/install.php';
		
		if ($success = mce_update_css($_POST['css'], $_POST['mce_do_css'])) {
			echo '<div id="message" class="updated fade"><p>' . __('CSS updated successfully.', 'mce') . '</p></div>' . "\n";
		} else {
			echo '<div id="message" class="error"><p>' . __('Cannot write to file, make sure it is writable.', 'mce') . '</p></div>';
		}
	}
		
	if (empty($mce_options['type'])) {
		mce_form_choice();
	} else {
		mce_form_settings();
	}
}

function mce_form_choice() {
	?>
	<div class="wrap">
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
	<h2><?php _e('My Comments Elsewhere Options', 'mce'); ?></h2>
	<div class="updated"><p><?php _e('Please choose the comment tracking service you\'d like to use.', 'mce'); ?></p></div>
	<table>
	<tr>
	<td>
	<input type="radio" name="service" id="service_backtype" value="backtype" checked="checked" />
	</td>
	<td>
	<a href="#" onclick="javascript:document.getElementById('service_backtype').checked='checked';return false;" target="_blank"><img src="<?php echo WP_PLUGIN_URL . '/my-comments-elsewhere/backtype.png'; ?>" alt="Backtype" /></a>	
	</td>
	</tr>
	<tr>
	<td>&nbsp;</td>
	<td><p><a href="http://www.backtype.com" target="_blank">Backtype</a> automatically finds your comments on other blogs.</p></td>
	</tr>
	<tr>
	<td>
	<input type="radio" name="service" id="service_cocomment" value="cocomment" />
	</td>
	<td>
	<a href="#" onclick="javascript:document.getElementById('service_cocomment').checked='checked';return false;" target="_blank"><img src="<?php echo WP_PLUGIN_URL . '/my-comments-elsewhere/cocomment.png'; ?>" alt="CoComment" /></a>
	</td>
	</tr>
	<tr>
	<td>&nbsp;</td>
	<td><p><a href="http://www.cocomment.com" target="_blank">CoComment</a> requires a browser extension to be installed.</p></td>
	</tr>
	<tr>
	<td>&nbsp;</td>
	<td>
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<input name="mce_do_choose" value="<?php _e('Choose Service', 'mce'); ?>" type="submit" class="button-primary"  />
	</td>
	</tr>
	</table>
	<h3><?php _e('I\'m not yet using either of these services, which one do you recommend?', 'mce'); ?></h3>
	<p><?php _e('In my opinion, Backtype is the better option. You don\'t have to install any browser extensions for it to work and it\'s completely automated.', 'mce'); ?></p>
	</form>
	</div>
	<?php
}

function mce_form_settings() {
	global $mce_options;
	
	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
					
	$digest_categories = get_categories('hide_empty=0');	
	
	$css_location 	   = MCE_DIR . '/style.css';
	$css_contents 	   = file_get_contents($css_location);
	$css_writable 	   = is_writable($css_location);
	
	$do_initial_import = (empty($mce_options['initial_import']) && $mce_options['username'] && ($mce_options['type'] == 'cocomment' || $mce_options['api_key']) ? true : false);
	?>
	<?php if (!empty($mce_options['last_import'])): ?><!-- last import: <?php echo date('Y-m-d H:i:s', $mce_options['last_import']); ?>--><?php endif; ?>
	<div class="wrap">
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">	
	<h2><?php _e('My Comments Elsewhere Options', 'mce'); ?></h2>
	<?php if ($mce_options['type'] == 'backtype'): ?>
	<?php if (empty($mce_options['username']) || empty($mce_options['api_key']) && empty($_POST)): ?>
	<p>
	<?php _e('The first thing you\'ll need to do is sign up at <a href="http://www.backtype.com/" target="_blank">Backtype</a> if you haven\'t already. Backtype will then monitor 
			  your comments across the web and send them back to your blog.', 'mce'); ?>
	</p>
	<p>
	<?php _e('Once you\'re logged in, you can find your API key <a href="http://www.backtype.com/developers" target="_blank">here</a>. (Under "authentication")', 'mce'); ?>
	</p>
	<?php else: ?>
	<?php if (!empty($mce_options['page_url'])): ?>
	<div>
	<p>
	<?php _e('If you find a blog that doesn\'t appear to be tracked (comments from that blog are not appearing), you can add it <a href="http://www.backtype.com/addblogs" target="_blank">here</a>.', 'mce'); ?>
	</p>
	<p>
	<?php _e('PS: Did you know that BackType also allows you to <a href="http://www.backtype.com/people" target="_blank">follow other people\'s comments</a>?', 'mce'); ?>
	<?php _e('You can even use it to <a href="http://www.backtype.com/home/subscriptions" target="_blank">track conversations</a>!', 'mce'); ?>
	</p>
	</div>
	<?php endif; ?>
	<?php endif; ?>
	<?php else: ?>
	<?php if (empty($mce_options['username']) && empty($_POST)): ?>
	<p>
	<?php _e('The first thing you\'ll need to do is sign up at <a href="http://www.cocomment.com/" target="_blank">CoComment</a> if you haven\'t already. CoComment will then monitor 
			  your comments across the web and send them back to your blog.', 'mce'); ?>
	</p>
	<?php endif; ?>	
	<?php endif; ?>
	<table class="form-table">
	<?php if ($mce_options['type'] == 'backtype'): ?>
	<tr>
	<th scope="row" valign="top"><?php _e('Backtype Username', 'mce'); ?></th>
	<td><input size="25" type="text" name="username" value="<?php echo htmlspecialchars($mce_options['username']); ?>" /></td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('API Key', 'mce'); ?></th>
	<td><input size="25" type="text" name="api_key" value="<?php echo htmlspecialchars($mce_options['api_key']); ?>" /></td>
	</tr>
	<?php else: ?>
	<tr>
	<th scope="row" valign="top"><?php _e('CoComment Username', 'mce'); ?></th>
	<td><input size="25" type="text" name="username" value="<?php echo htmlspecialchars($mce_options['username']); ?>" /></td>
	</tr>
	<?php endif; ?>
	<tr>
	<th scope="row" valign="top"><?php _e('Import Comments', 'mce'); ?></th>
	<td>
	<select name="import_frequency">
	<option value="hourly" <?php if ($mce_options['import_frequency'] == 'hourly'): ?>selected="selected"<?php endif; ?>><?php _e('Hourly', 'mce'); ?></option>
	<option value="daily" <?php if ($mce_options['import_frequency'] == 'daily'): ?>selected="selected"<?php endif; ?>><?php _e('Daily', 'mce'); ?></option>
	<?php if ($mce_options['type'] != 'cocomment'): ?><option value="weekly" <?php if ($mce_options['import_frequency'] == 'weekly'): ?>selected="selected"<?php endif; ?>><?php _e('Weekly', 'mce'); ?></option><?php endif; ?>
	</select>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Filter out comments made here', 'mce'); ?></th>
	<td>
	<select name="filter_own_blog">
	<option value="1" <?php if ($mce_options['filter_own_blog'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['filter_own_blog'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select> <?php _e('Do not show comments you posted on your own blog', 'mce'); ?>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Dofollow Links', 'mce'); ?></th>
	<td>
	<select name="dofollow_links">
	<option value="1" <?php if ($mce_options['dofollow_links'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['dofollow_links'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Open links in new window', 'mce'); ?></th>
	<td>
	<select name="open_links_in_new_window">
	<option value="1" <?php if ($mce_options['open_links_in_new_window'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['open_links_in_new_window'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Include action links', 'mce'); ?></th>
	<td>
	<select name="include_action_links">
	<option value="1" <?php if ($mce_options['include_action_links'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['include_action_links'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
	(<?php _e('reply, share, tweet', 'mce'); ?>)
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Date Format', 'mce'); ?></th>
	<td>
	<select name="date_format">
	<option value="1" <?php if ($mce_options['date_format'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Relative date', 'mce'); ?></option>
	<option value="2" <?php if ($mce_options['date_format'] == 2): ?>selected="selected"<?php endif; ?>><?php _e('Full date', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['date_format'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('Do not display', 'mce'); ?></option>
	</select>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Comments per page', 'mce'); ?></th>
	<td><input size="5" type="text" name="comments_per_page" value="<?php echo htmlspecialchars($mce_options['comments_per_page']); ?>" /></td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Add RSS feed', 'mce'); ?></th>
	<td>
	<select name="rss_feed">
	<option value="1" <?php if ($mce_options['rss_feed'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['rss_feed'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
	</td>
	</tr>
	<tr>
	<td colspan="2">
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<span class="submit"><input name="mce_do_save" value="<?php _e('Save Changes', 'mce'); ?>" type="submit" class="button-primary"  /></span>
	</td>
	</tr>	
	</table>	
	</form>
	<?php if ($do_initial_import): ?>
	<a name="initial_import"></a>
	<?php if (empty($mce_options['last_import'])): ?>
	<h3><?php _e('Initial Import', 'mce'); ?></h3>
	<?php if ($mce_options['type'] == 'backtype'): ?>
	<p><?php _e('You still have to run your initial import, please do this now. Depending on the amount of comments you have, this might take a while. Do not close the page whilst this is running.', 'mce'); ?></p>
	<?php else: ?>
	<p><?php _e('You still have to run your initial import, please do this now. Note that only your last 20 comments will be imported as CoComment does not make available all your comments via an API.', 'mce'); ?></p>
	<?php endif; ?>
	<?php else: ?>
	<h3><?php _e('Reimport', 'mce'); ?></h3>
	<?php if ($mce_options['type'] == 'backtype'): ?>
	<p><?php _e('You have to reimport your comments because you\'ve changed some default settings. Depending on the amount of comments you have, this might take a while. Do not close the page whilst this is running.', 'mce'); ?></p>
	<?php else: ?>
	<p><?php _e('You have to reimport your comments because you\'ve changed some default settings. Note that all current comments will be lost.', 'mce'); ?></p>
	<?php endif; ?>
	<?php endif; ?>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<span class="submit"><input name="mce_do_import" value="<?php _e('Do Import', 'mce'); ?>" type="submit" /></span>
	</form>
	<?php elseif (!empty($mce_options['initial_import'])): ?>
	<?php if (empty($mce_options['page_url'])): ?>	
	<a name="create_the_page"></a>
	<h3><?php _e('Setting things up', 'mce'); ?></h3>
	<p><?php _e('Make a <a href="page-new.php">new page</a> (not a post) and add the shortcode [mycomments] to display your comments aggregated from across the web.', 'mce'); ?></p>
	<?php endif; ?>
	<h3><?php _e('Create digest posts', 'mce'); ?></h3>
	<p><?php _e('If you want to, you can create daily or weekly digest posts of your most recent comments. To set this up, ', 'mce'); ?> <a href="#" onclick="javascript:document.getElementById('create_digest_posts').style.display='block';return false;"><?php _e('click here', 'mce'); ?></a>.</p>
	<div id="create_digest_posts" style="display:none">
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">					
	<table class="form-table">
	<tr>
	<th scope="row" valign="top"><?php _e('Post Frequency', 'mce'); ?></th>
	<td>
	<select name="digest_frequency">
	<option value="never" <?php if ($mce_options['digest_frequency'] == 'never'): ?>selected="selected"<?php endif; ?>><?php _e('Never', 'mce'); ?></option>
	<option value="daily" <?php if ($mce_options['digest_frequency'] == 'daily'): ?>selected="selected"<?php endif; ?>><?php _e('Daily', 'mce'); ?></option>
	<option value="weekly" <?php if ($mce_options['digest_frequency'] == 'weekly'): ?>selected="selected"<?php endif; ?>><?php _e('Weekly', 'mce'); ?></option>
	</select>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Post title', 'mce'); ?></th>
	<td><input type="text" name="digest_title" value="<?php echo htmlspecialchars($mce_options['digest_title']); ?>" size="30" /> <?php _e('[day], [week], [month], [year] and [date] are available', 'mce'); ?></td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Order of comments', 'mce'); ?></th>
	<td>
	<select name="digest_order">
	<option value="1" <?php if ($mce_options['digest_order'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Newest first', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['digest_order'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('Oldest first', 'mce'); ?></option>
	</select>
	<tr>
	<th scope="row" valign="top"><?php _e('Max comments per post', 'mce'); ?></th>
	<td><input size="3" type="text" name="digest_max_comments" value="<?php echo ($mce_options['digest_max_comments'] ? htmlspecialchars($mce_options['digest_max_comments']) : ''); ?>" /> <?php _e('Leave empty to add all comments during the period', 'mce'); ?></td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Min comments per post', 'mce'); ?></th>
	<td><input size="3" type="text" name="digest_min_comments" value="<?php echo htmlspecialchars($mce_options['digest_min_comments']); ?>" /> <?php _e('If this limit isn\'t reached, no post', 'mce'); ?></td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Post category', 'mce'); ?></th>
	<td>
	<select name="digest_category">
	<?php foreach ($digest_categories as $category): ?>
		<option value="<?php echo $category->term_id; ?>" <?php if ($category->term_id == $mce_options['digest_category']): ?>selected="selected"<?php endif; ?>><?php echo $category->name; ?></option>
	<?php endforeach; ?>	
	</select>
	</td>
	</tr>
	<tr>
	<th scope="row" valign="top"><?php _e('Post tags', 'mce'); ?></th>
	<td><input type="text" name="digest_tags" value="<?php echo htmlspecialchars($mce_options['digest_tags']); ?>" size="20" /> <?php _e('Separate tags with commas', 'mce'); ?></td>
	</tr>
	<tr>
	<td colspan="2">
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<span class="submit"><input name="mce_do_digest" value="<?php _e('Update', 'mce'); ?>" type="submit" /></span>
	</td>
	</tr>	
	</table>
	</form>
	</div>
	<h3><?php _e('Edit the CSS', 'mce'); ?></h3>
	<p><?php _e('If you want to edit the CSS for the comments page, ', 'mce'); ?> <a href="#" onclick="javascript:document.getElementById('form_edit_css').style.display = 'block';return false;"><?php _e('click here'); ?></a></p>
	<div id="form_edit_css" style="display:none">
	<?php if (!$css_writable): ?><p><?php _e('You will have to make "style.css" file writable first.', 'mce'); ?></p><?php endif; ?>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<p>
	<textarea name="css" rows="10" cols="80"><?php echo htmlspecialchars($css_contents); ?></textarea>
	</p>	
	<p>
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<span class="submit"><input name="mce_do_css" value="<?php _e('Update', 'mce'); ?>" type="submit" /> <input name="mce_do_css" value="<?php _e('Reset to default', 'mce'); ?>" type="submit" /></span>
	</form>
	</div>
	<h3><?php _e('Acknowledgements', 'mce'); ?></h3>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<p><input type="checkbox" name="itw_link" value="1" <?php if ($mce_options['itw_link']): ?>checked="checked"<?php endif; ?> /> <?php _e('Plugin by <a href="http://www.improvingtheweb.com/" target="_blank">Improving The Web</a>', 'mce'); ?></p>
	<p><input type="checkbox" name="service_link" value="1" <?php if ($mce_options['service_link']): ?>checked="checked"<?php endif; ?> /> <?php _e('Powered by ' . ($mce_options['type'] == 'backtype' ? '<a href="http://www.backtype.com/" target="_blank">Backtype</a>' : '<a href="http://www.cocomment.com/" target="_blank">CoComment</a>'), 'mce'); ?></p>
	<p>
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<span class="submit"><input name="mce_do_acknowledgements" value="<?php _e('Update', 'mce'); ?>" type="submit" /></span>
	</p>
	</form>	
	<h3><?php _e('Widget', 'mce'); ?></h3>
	<p><?php _e('The sidebar widget can be found'); ?> <a href="widgets.php"><?php _e('here', 'mce'); ?></a>.</p>
	<?php endif; ?>
	<h3><?php _e('Plugin provided by', 'mce'); ?></h3>
	<p><a href="http://www.improvingtheweb.com" target="_blank" style="background:yellow;padding:5px;">Improving The Web</a>: <a href="http://rss.improvingtheweb.com/improvingtheweb/wVZp" target="_blank">RSS</a> | <a href="http://twitter.com/improvingtheweb" target="_blank">Twitter</a></p>
	<?php if ($mce_options['type']): ?>
	<h3><?php _e('Tracking provided by', 'mce'); ?></h3>
	<?php if ($mce_options['type'] == 'backtype'): ?>
	<a href="http://www.backtype.com/" target="_blank"><img src="<?php echo WP_PLUGIN_URL . '/my-comments-elsewhere/backtype.png'; ?>" alt="Backtype" style="display:block;padding-bottom:5px;" /></a>
	<?php if ($mce_options['username']): ?><a href="http://www.backtype.com/<?php echo $mce_options['username']; ?>" target="_blank">&raquo; <?php _e('Your profile page', 'mce'); ?></a><?php endif; ?>
	<?php else: ?>
	<a href="http://www.cocomment.com/" target="_blank"><img src="<?php echo WP_PLUGIN_URL . '/my-comments-elsewhere/cocomment.png'; ?>" alt="CoComment" style="display:block;padding-bottom:5px;" /></a>
	<?php if ($mce_options['username']): ?><a href="http://www.cocomment.com/comments/<?php echo $mce_options['username']; ?>/" target="_blank">&raquo; <?php _e('Your profile page', 'mce'); ?></a><?php endif; ?>
	<?php endif; ?>	
	<?php endif; ?>
	<h3><?php _e('Start Over?', 'mce'); ?></h3>
	<p><?php _e('If you\'d like to start over (for whatever reason), click the button below. Be warned, the current settings and comments will be deleted.', 'mce'); ?></p>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" onsubmit="return confirm('<?php _e('Are you sure? All current settings and comments will be deleted.', 'mce'); ?>');">
	<?php wp_nonce_field('my-comments-elsewhere'); ?>
	<span class="submit"><input name="mce_do_reset" value="<?php _e('Reset', 'mce'); ?>" type="submit" /></span>
	</form>
	</div>
	<?php
}
?>
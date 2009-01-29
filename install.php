<?php
if (!defined('WP_CONTENT_URL')) die;

function mce_do_install() {
	mce_create_table();
	mce_default_options(true);
	$time = mktime(0, 0, 0, date('n'), date('j') + 1, date('Y')); 
	wp_schedule_event($time, 'daily', 'mce_import_cron');
}

function mce_do_uninstall() {
	delete_option('mce_options');
	wp_clear_scheduled_hook('mce_import_cron');
	wp_clear_scheduled_hook('mce_digest_cron');
}

function mce_create_table() {
	global $wpdb;

	if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}my_comments'") != "{$wpdb->prefix}my_comments") {	
		$charset_collate = '';

		if ($wpdb->supports_collation()) {
			if (!empty($wpdb->charset)) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if (!empty($wpdb->collate)) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}
		}
		
		$wpdb->query("CREATE TABLE  `{$wpdb->prefix}my_comments` (
	 		`comment_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	 		`comment_content` TEXT NOT NULL ,
	 		`comment_date` datetime NOT NULL default '0000-00-00 00:00:00',
  	 		`comment_date_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
  	 		`import_date` datetime NOT NULL default '0000-00-00 00:00:00',
 	 		`comment_url` VARCHAR(200) NOT NULL, 
 	 		`comment_identifier` BIGINT UNSIGNED NOT NULL, 
	 		`post_title` VARCHAR(150) NOT NULL ,
	 		`post_url` VARCHAR(200) NOT NULL ,
	 		`blog_title` VARCHAR(150) NOT NULL ,
	 		`blog_url` VARCHAR(200) NOT NULL ,
	 		KEY `comment_date` (comment_date),
			KEY `import_date` (import_date),
	 		KEY `comment_identifier` (comment_identifier))
			$charset_collate");
	}
}

function mce_default_options($install=false) {
	if ($install && get_option('mce_options')) {
		return;
	}
	
 	$mce_options = array('type' => '', 'username' => '', 'api_key' => '', 'import_frequency' => 'daily', 'last_import' => 0, 'filter_own_blog' => 1, 'dofollow_links'  => 1, 'open_links_in_new_window' => 1, 'date_format' => 1, 'comments_per_page'  => 20, 
				   	    'include_action_links' => 1, 'page_url' => '', 'rss_feed' => 0, 'service_link' => 1, 'itw_link' => 1, 'initial_import' => 0, 'widget_title' => 'My Comments', 'widget_max_comments' => 5, 
						'widget_show_post_title' => 1, 'widget_trim_post_title' => 80, 'widget_show_excerpt' => 1, 'widget_trim_excerpt' => 250, 'widget_date_format' => 1, 
						'widget_link_to_page' => 1, 'widget_link_title' => 'View more comments', 'widget_cache' => false, 'digest_frequency' => 'never', 'digest_title' => 'My comments elsewhere: [date]', 
						'digest_order' => 1, 'digest_max_comments' => 50, 'digest_min_comments' => 1, 'digest_category' => 0, 'digest_tags' => '', 'digest_last_time' => 0, 'comments_count' => 0);

	if ($install) {
		add_option('mce_options', $mce_options);
	} else {
		return $mce_options;
	}
}

function mce_update_css($contents, $action='update') {
	$location = MCE_DIR . '/style.css';
	
	if (strtolower($action) == 'reset to default') {
		$contents = '.mce_comment {
}
.mce_url {	
	font-weight:bold;
	display:block;
}
.mce_date {	
}
.mce_text {	
}
.mce_actions { 
	font-size:90%;
}
.mce_pagination {
}
.mce_acknowledgement {
}
.mce_pagination a, .mce_pagination strong {
	padding-right:5px;
}
.mce_digest_acknowledgement {
}
.mce_widget_title {
}
.mce_widget_date {
}
.mce_widget_excerpt {
}
.mce_widget_footer {
}';
	}

	if (!is_writable($location) || !($handle = fopen($location, 'w')) || fwrite($handle, $contents) === false) {
		return false;
	} else {
		fclose($handle);
		return true;
	}
}
?>
<?php
if (!defined('WP_CONTENT_URL')) die;

require MCE_DIR . '/import_php' . (class_exists('SimpleXMLElement') ? '5' : '4') . '.php';

function mce_import($initial_import=false) {
	global $mce_options, $wpdb;
	
	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
	
	if ($initial_import) {
		if (!empty($mce_options['initial_import'])) {
			return array('success' => 0, 'message' => 'You have already executed the initial import once before, no need to do it again as comments will be imported automatically from now on.');
		}
	} else if (empty($mce_options['initial_import'])) {
		return array('success' => 0, 'message' => 'You have not yet done the initial import.');
	}
	
	if (function_exists('set_time_limit')) {
		set_time_limit(0);
	}
		
	if (function_exists('ignore_user_abort')) {
		ignore_user_abort();
	}
	
	if ($initial_import) {
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}my_comments");
	}
	
	if ($mce_options['type'] == 'backtype') {
		$report = mce_import_backtype($initial_import);
	} else {
		$report = mce_import_cocomment($initial_import);
	} 
	
	if ($report['success']) {
		if ($initial_import) {
			$mce_options['initial_import'] = time();
		}
		
		$mce_options['last_import']    = time();
		$mce_options['comments_count'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}my_comments");
		
		unset($mce_options['widget_cache']);
		
		update_option('mce_options', $mce_options);
		
		if ($initial_import) {
			$report['comments_count'] = $mce_options['comments_count'];
		}
	}
	
	return $report;
}

function mce_import_cocomment($initial_import=false) {
	global $mce_options, $wpdb;
	
	require_once(ABSPATH . WPINC . '/rss.php');
	
	if (function_exists('init')) {
		init();
	}
		
	$blog_url = trim(get_bloginfo('url'), '/');
	
	if (!$contents = mce_url_open('http://www.cocomment.com/myRss/' . urlencode($mce_options['username']) . '.rss')) {
		return array('success' => 0, 'Could not open the URL.');
	}
		
	$rss = new MagpieRSS($contents['body']);
	
	unset($contents);

	if (!empty($rss->items)) {
		$blog_url 	 = strtolower(trim(get_bloginfo('url'), '/'));
		$import_date = date('Y-m-d H:i:s');
		$gmt_offset  = get_option('gmt_offset') * 3600;
		
		foreach ($rss->items as $item) {			
			$comment = array('content' 	  => mce_filter_content($item['description']), 
							 'url' 		  => trim($item['link']), 
							 'post_title' => strip_tags(trim($item['title'])),
							 'post_url'   => trim($item['link']), 
							 'blog_title' => '', 
							 'blog_url'   => '');
			
			$timestamp 			 = strtotime(trim($item['pubdate']));
			$comment['date_gmt'] = gmdate('Y-m-d H:i:s', $timestamp);
			$comment['date'] 	 = date('Y-m-d H:i:s', $timestamp + $gmt_offset);
			
			if (preg_match('/comment\/([0-9]+)/i', $item['guid'], $match)) {
				$comment['identifier'] = (int) $match[1];
			} else {
				continue;
			}
			
			if ($mce_options['filter_own_blog'] && strpos(strtolower($comment['blog_url']), $blog_url) === 0) {
				continue;
			}
						
			if (preg_match('/^(.*?)\((.*?)\)$/i', $comment['post_title'], $match) || preg_match('/^(.*?)&#28;(.*?)&#x29;$/i', $comment['post_title'], $match)) {
				$comment['post_title'] = trim($match[1]);
			} 
			
			if (!$initial_import && $wpdb->query($wpdb->prepare("SELECT `comment_ID` FROM {$wpdb->prefix}my_comments WHERE `comment_identifier` = %d", $comment['identifier']))) {
				$already_processed = true;
				break;
			}									
				
			$result = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}my_comments (`comment_content`, `comment_date`, `comment_date_gmt`, `import_date`, `comment_url`, `comment_identifier`, `post_title`, `post_url`, `blog_title`, `blog_url`) 
												   VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)", $comment['content'], $comment['date'], $comment['date_gmt'], $import_date, $comment['url'], $comment['identifier'], $comment['post_title'], 
												   $comment['post_url'], $comment['blog_title'], $comment['blog_url']));	
		}
	} 
	
	return array('success' => 1);
}

function mce_filter_content($content) {
	return wpautop(make_clickable(convert_chars(wptexturize(strip_tags(trim($content))))));
}

function mce_url_open($url, $args=array('timeout' => 10), $tries=1) {
	if (function_exists('wp_remote_get')) {
		$result = wp_remote_get($url, $args);

		if (is_wp_error($result)) {
			if ($tries < 3 && $result->get_error_code() == 'http_request_failed') {
				return mce_url_open($url, $args, ++$tries);
			} else {
				return false;
			}			
		} else {
			return $result;
		}
	} else {	
		if (!class_exists('Snoopy')) {
			require_once ABSPATH . 'wp-includes/class-snoopy.php';
		}
		
		$snoopy = new Snoopy();
				
		if (!$snoopy->fetch($url) || !$snoopy->results) {
			if ($tries < 3) {
				return mce_url_open($url, $args, ++$tries);
			} else {
				return false;
			}
		}		
		
		return array('body' => $snoopy->results, 'response' => array('code' => $snoopy->status));
	}
}
?>
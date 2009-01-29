<?php
if (!defined('WP_CONTENT_URL')) die;

function mce_verify_credentials() {
	global $mce_options;
	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
	
	if ($mce_options['type'] == 'backtype') {
		if (!$mce_options['username'] || !$mce_options['api_key']) {
			return array('success' => 0, 'message' => 'Please fill in your username and API key.');
		} else if (!$result = mce_url_open('http://api.backtype.com/user/' . urlencode($mce_options['username']) . '/profile.xml?key=' . urlencode($mce_options['api_key']))) {
			return array('success' => 0, 'message' => 'Could not receive web page. Are you sure your server can retrieve offsite pages? Please try again.');
		}	

		$xml = mce_xml_unserialize($result['body']);
		
		if (!$xml || !empty($xml->errorCode) || $result['response']['code'] != 200) {
			if (!empty($xml->errorCode) && $xml->errorCode == 'limit-exceeded') {
				return array('success' => 0, 'message' => 'Your API limit for the day has been exceeded');
			} else {
				$mce_options['username'] = '';
				$mce_options['api_key']  = '';
				return array('success' => 0, 'message' => 'Your username or associated API key is incorrect.');
			}
		} else {
			return array('success' => 1);
		}
	} else {
		if (!$mce_options['username']) {
			return array('success' => 0, 'message' => 'Please fill in your username.');
		} else if (!$result = mce_url_open('http://www.cocomment.com/myRss/' . urlencode($mce_options['username']) . '.rss')) {
			return array('success' => 0, 'message' => 'Could not receive web page. Are you sure your server can retrieve offsite pages? Please try again.');
		} else if ($result['response']['code'] != 200) {
			$mce_options['username'] = '';
			return array('success' => 0, 'message' => 'Invalid HTTP response (' . $result['response']['code'] . ')');
		} else {
			return array('success' => 1);
		}
	}
}

function mce_import_backtype($initial_import=false) {
	global $mce_options, $wpdb;
	
	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
	
	if (empty($mce_options['username']) || empty($mce_options['api_key'])) {
		return array('success' => 0, 'message' => 'Your username or associated API key is incorrect.');
	}
			
	if ($initial_import) {
		$items_per_page = 100;
	} else if (!empty($mce_options['items_per_page'])) {
		$items_per_page = $mce_options['items_per_page']; //for low memory systems..
	} else {
		switch ($mce_options['import_frequency']) {
			case 'weekly': 
				$items_per_page = 100;
				break;
			case 'daily': 
				$items_per_page = 50;
				break;
			case 'hourly':
			default:
				$items_per_page = 25;
				break;
		}
	}

	$page			   = 1;
	$already_processed = false;
	$error		 	   = 0;
	$url 			   = 'http://api.backtype.com/user/' . urlencode($mce_options['username']) . '/comments.xml?key=' . urlencode($mce_options['api_key']) . '&itemsperpage=' . $items_per_page . '&sort=1&page=';
	$blog_url	 	   = str_replace('www.', '', strtolower(trim(get_bloginfo('url'), '/')));
	$import_date 	   = date('Y-m-d H:i:s');
	$gmt_offset  	   = get_option('gmt_offset') * 3600;
	
	do {				
		if (!$contents = mce_url_open($url . $page)) {
			$error = 1;
			break;
		}
		
		$xml = mce_xml_unserialize($contents['body']);
	
		unset($contents);
		
		if (!$xml || !empty($xml->errorCode) || empty($xml->comments)) {
			if (!empty($xml->errorCode) && $xml->errorCode == 'limit-exceeded') {
				$error = 2;
			} else if ($xml && empty($xml->comments->entry) && (int) $xml->startindex > (int) $xml->totalresults) {
				//..
			} else {
				$error = 3;
			}
			break;
		}
		
		if (empty($xml->comments->entry) || !count($xml->comments->entry)) { //second condition redundant..
			break;
		}
			
		foreach ($xml->comments->entry as $entry) {
			$comment = array('content'    => mce_filter_content($entry->comment->content), 
							 'url'        => trim($entry->comment->url), 
							 'post_title' => strip_tags(trim($entry->post->title)), 
							 'post_url'   => trim($entry->post->url), 
							 'blog_title' => strip_tags(trim($entry->blog->title)), 
							 'blog_url'   => trim($entry->blog->url));
			
			$timestamp			   = strtotime(trim($entry->comment->date));
			$comment['date_gmt']   = gmdate('Y-m-d H:i:s', $timestamp);   //todo: check to see if this is the same as the input..
			$comment['date'] 	   = date('Y-m-d H:i:s', $timestamp + $gmt_offset);
			$comment['identifier'] = (int) $entry->comment->id;
		
			if (!$comment['post_title']) {
				$comment['post_title'] = $comment['blog_title'];
			}
			
			if ($mce_options['filter_own_blog'] && strpos(str_replace('www.', '', strtolower($comment['blog_url'])), $blog_url) === 0) {
				continue;
			}

			if (!$initial_import && $wpdb->query($wpdb->prepare("SELECT `comment_ID` FROM {$wpdb->prefix}my_comments WHERE `comment_identifier` = %d", $comment['identifier']))) {				
				$already_processed = true;
				break;
			}		
				
			$result = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}my_comments (`comment_content`, `comment_date`, `comment_date_gmt`, `import_date`, `comment_url`, `comment_identifier`, `post_title`, `post_url`, `blog_title`, `blog_url`) 
												   VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)", $comment['content'], $comment['date'], $comment['date_gmt'], $import_date, $comment['url'], $comment['identifier'], $comment['post_title'], 
												   $comment['post_url'], $comment['blog_title'], $comment['blog_url']));
		}
		
		sleep(1);
		
		$page++;	
	} while (!$already_processed && !$error);

	if (!$error) {
		return array('success' => 1);
	} else if ($error == 1) {
		if ($page == 1) {
			return array('success' => 0, 'message' => 'Could not open the URL.');
		} else {
			return array('success' => 0, 'message' => 'Could not open some of the URLs.');
		}
	} else if ($error == 2) {
		return array('success' => 0, 'message' => 'Your API limit for the day has been exceeded.');
	} else {
		return array('success' => 0, 'message' => 'An unknown error occured.');
	}
}

function mce_xml_unserialize($xml){
	return new SimpleXMLElement($xml); 
}
?>
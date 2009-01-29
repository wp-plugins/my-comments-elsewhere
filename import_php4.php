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

		if (!$xml || !empty($xml['error']) || $result['response']['code'] != 200) {
			if (!empty($xml['error']) && $xml['error']['errorCode'] == 'limit-exceeded') {
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
				
		if (!$xml || !empty($xml['error']) || empty($xml['feed']['comments'])) {
			if (!empty($xml['error']) && $xml['error']['errorCode'] == 'limit-exceeded') {
				$error = 2;
			} else if ($xml && empty($xml['feed']['comments']['entry']) && (int) $xml['feed']['startindex'] > (int) $xml['feed']['totalresults']) {
				//...
			} else {
				$error = 3;
			}
			break;
		}
		
		if (empty($xml['feed']['comments']['entry']) || !count($xml['feed']['comments']['entry'])) { //second condition redundant..
			break;
		}
				
		foreach ($xml['feed']['comments']['entry'] as $entry) {			
			$comment = array('content'    => mce_filter_content($entry['comment']['content']),
							 'url'        => trim($entry['comment']['url']),
							 'post_title' => strip_tags(trim($entry['post']['title'])),
							 'post_url'   => trim($entry['post']['url']),
							 'blog_title' => strip_tags(trim($entry['blog']['title'])),
							 'blog_url'   => trim($entry['blog']['url']));
					
			$timestamp			   = strtotime(trim($entry['comment']['date']));
			$comment['date_gmt']   = gmdate('Y-m-d H:i:s', $timestamp);   //todo: check to see if this is the same as the input..
			$comment['date'] 	   = date('Y-m-d H:i:s', $timestamp + $gmt_offset);
			$comment['identifier'] = (int) $entry['comment']['id'];
			
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

//xml lib by http://keithdevens.com/software/phpxml

function &mce_xml_unserialize(&$xml){
	$xml_parser = &new mce_XML();
	$data = &$xml_parser->parse($xml);
	$xml_parser->destruct();
	
	return $data;
}

class mce_XML{
	var $parser;
	var $document;
	var $parent;
	var $stack;
	var $last_opened_tag;

	function mce_XML() {
 		$this->parser = &xml_parser_create('UTF-8');
		xml_parser_set_option(&$this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_object(&$this->parser, &$this);
		xml_set_element_handler(&$this->parser, 'open','close');
		xml_set_character_data_handler(&$this->parser, 'data');
	}
	
	function destruct() { 
		xml_parser_free(&$this->parser); 
	}
	
	function &parse(&$data) {
		$this->document = array();
		$this->stack    = array();
		$this->parent   = &$this->document;
		return xml_parse(&$this->parser, &$data, true) ? $this->document : NULL;
	}
	
	function open(&$parser, $tag, $attributes){
		$this->data = ''; 
		$this->last_opened_tag = $tag;
		if (is_array($this->parent) && array_key_exists($tag,$this->parent)) {
			if (is_array($this->parent[$tag]) && array_key_exists(0,$this->parent[$tag])) { 
				$key = mce_count_numeric_items($this->parent[$tag]);
			} else {
				if (array_key_exists("$tag attr",$this->parent)) {
					$arr = array('0 attr'=>&$this->parent["$tag attr"], &$this->parent[$tag]);
					unset($this->parent["$tag attr"]);
				} else {
					$arr = array(&$this->parent[$tag]);
				}
				$this->parent[$tag] = &$arr;
				$key = 1;
			}
			$this->parent = &$this->parent[$tag];
		} else {
			$key = $tag;
		}
		
		if ($attributes) {
			$this->parent["$key attr"] = $attributes;
		}
		
		$this->parent  = &$this->parent[$key];
		$this->stack[] = &$this->parent;
	}
	
	function data(&$parser, $data){
		if ($this->last_opened_tag != NULL) {
			$this->data .= $data;
		}
	}
	
	function close(&$parser, $tag){
		if ($this->last_opened_tag == $tag) {
			$this->parent = $this->data;
			$this->last_opened_tag = NULL;
		}
		
		array_pop($this->stack);
		
		if ($this->stack) {
			$this->parent = &$this->stack[count($this->stack)-1];
		}
	}
}

function mce_count_numeric_items(&$array){
	return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
}
?>
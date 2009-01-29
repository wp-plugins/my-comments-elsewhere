<?php
if (!defined('WP_CONTENT_URL')) die;
		
function mce_digest() {
	global $mce_options, $wpdb;
	
	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
	
	if ($mce_options['digest_frequency'] == 'never') {
		return;
	} else if ($mce_options['digest_frequency'] == 'daily') {
		$start_time = mktime(0, 0, 0, date('n'), date('j') - 1, date('Y'));   //strtotime('Yesterday')
		$end_time   = mktime(23, 59, 59, date('n'), date('j') - 1, date('Y')); //strtotime('Today') - 1
	} else {
		$start_time = mktime(0, 0, 0, date('n'), date('j') - 7, date('Y')); //strtotime('Last monday')
		$end_time   = mktime(23, 59, 59, date('n'), date('j') - 1, date('Y')); //strtotime('Yesterday')
	}
	
	if (!empty($mce_options['digest_start_time'])) {
		$start_time = $mce_options['digest_start_time'];
	}	
	
	$reply 				   = __('Reply', 'mce');
	$share				   = __('Share', 'mce');
	$tweet				   = __('Tweet', 'mce');
	$reply_to_this_comment = __('Reply to this comment', 'mce');
	$read_this_comment     = __('Read this comment', 'mce');
	$email_this_comment    = __('Email this comment', 'mce');
	$post_on_twitter       = __('Post on Twitter', 'mce');
	
	$no_follow  = (!$mce_options['dofollow_links'] ? ' rel="nofollow"' : '');
	$new_window = ($mce_options['open_links_in_new_window'] ? ' target="_blank"' : '');

	$gmt_offset = get_option('gmt_offset') * 3600;
	
	$comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mce WHERE import_date >= FROM_UNIXTIME(%s) AND import_date <= FROM_UNIXTIME(%s) ORDER BY comment_date " . ($mce_options['digest_order'] == 1 ? "DESC" : "ASC") . ($mce_options['digest_max_comments'] ? " LIMIT " . $mce_options['digest_max_comments'] : ""), $start_time, $end_time));
	
	if (empty($comments)) {
		return;
	} else if (count($comments) < $mce_options['digest_min_comments']) {
		if (empty($mce_options['digest_start_time'])) {
			$mce_options['digest_start_time'] = $start_time;
			update_option('mce_options', $mce_options);
		}
		return;
	}
			
	$date_format = get_option('date_format');
	$time_format = get_option('time_format');
	
	$content = '';
	
	foreach ($comments as $comment) {
		$comment->comment_date = strtotime($comment->comment_date_gmt) + $gmt_offset;
		$content .= '
		<div class="mce_comment">
		<a href="' . $comment->post_url . '"' . $no_follow . $new_window . ' class="mce_url">' . ($comment->post_title ? $comment->post_title : 'No title') . '</a>';
		if ($mce_options['date_format']) {
			$content .= '<small class="mce_date">' . date($date_format, $comment->comment_date) . ' at ' . date($time_format, $comment->comment_date) . '</small>';
		}
		$content .= '
		<div class="mce_text">' . $comment->comment_content . '</div>';
		if ($mce_options['include_action_links']) {
			$content .= '<p class="mce_actions"><a href="' . $comment->comment_url . '" title="' . $reply_to_this_comment . '" rel="nofollow"' . $new_window . '>' . $reply . '</a> | <a href="mailto:?subject=' . rawurlencode($read_this_comment) . '&amp;body=' . rawurlencode($comment->comment_url) . '" title="' . $email_this_comment . '" rel="nofollow"' . $new_window . ' class="share">' . $share . '</a> | <a href="' . $qs . 'mce_tweet=' . urlencode($comment->comment_ID) . '" title="' . $post_on_twitter . '" rel="nofollow"' . $new_window . '>' . $tweet . '</a></p>';
		}
		$content .= '</div>';
	}
	
	if ($mce_options['itw_link'] || $mce_options['service_link'] || $mce_options['rss_feed']) {
		$content .= '<p class="mce_acknowledgement">';
		if ($mce_options['itw_link']) {
			$content .= 'Plugin by <a href="http://www.improvingtheweb.com/" target="_blank" title="Wordpress Plugins">Improving The Web</a>';
			if ($mce_options['service_link'] || $mce_options['rss_feed']) {	
				$content .= ' - ';
			}
		}
		if ($mce_options['service_link']) {
			if ($mce_options['type'] == 'backtype') {
				$content .= 'Powered by <a href="http://www.backtype.com/" title="BackType" target="_blank">Backtype</a>';
			} else {
				$content .= 'Powered by <a href="http://www.cocomment/" title="CoComment" target="_blank">CoComment</a>';
			}
			if ($mce_options['rss_feed']) {
				$content .= ' - ';
			}
		}
		
		if ($mce_options['rss_feed']) {
			$content .= '<a href="' . $mce_options['rss'] . '" rel="nofollow" target="_blank" title="RSS">Subscribe</a>';
		}
		
		$content .= '</p>';
	}
	
	$title_search  = array('[day]', '[week]', '[month]', '[year]', '[date]');
	$title_replace = array(date('D', $start_time), date('jS F', $start_time) . ' - ' . date('jS F', $end_time), date('M', $start_time), date('Y', $start_time), date('F jS, Y', $start_time));

	$post 					= array();
	$post['post_content']	= $content;
	$post['post_title']     = str_replace($title_search, $title_replace, $mce_options['digest_title']);
	$post['post_date'] 	    = date('Y-m-d h:i:s', time()); //date('Y-m-d 23:59:59')
	$post['post_date_gmt']  = get_gmt_from_date($post['post_date']);
	$post['comment_status'] = 'open';
	$post['ping_status']    = 'open';
	$post['post_status']    = 'publish';
	$post['post_author']    = $wpdb->get_var("SELECT user_id from $wpdb->usermeta WHERE meta_key = 'wp_capabilities' and meta_value LIKE '%administrator%' order by user_id asc limit 1");

	if ($mce_options['digest_tags']) {
		$post['tags_input'] = $mce_options['digest_tags'];
	}
	
	wp_insert_post($post);
	
	if (!empty($mce_options['digest_start_time'])) {
		unset($mce_options['digest_start_time']);
		update_option('mce_options', $mce_options);
	}
}
?>
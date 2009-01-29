<?php
/*
Plugin Name: My Comments Elsewhere
Plugin URI: http://www.improvingtheweb.com/wordpress-plugins/my-comments-elsewhere/
Description: Aggregates all comments you made on other websites back onto your own blog.
Author: Improving The Web
Version: 1.0
Author URI: http://www.improvingtheweb.com/
*/

if (!defined('WP_CONTENT_URL')) {
	define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
}
if (!defined('WP_CONTENT_DIR')) {
	define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WP_PLUGIN_URL')) {
	define('WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins');
}
if (!defined('WP_PLUGIN_DIR')) {
	define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

define('MCE_DIR', dirname(__FILE__));

if (is_admin()) {
	register_activation_hook(__FILE__, 'mce_install');
	register_deactivation_hook(__FILE__, 'mce_uninstall');
	
	require MCE_DIR . '/admin.php';
} else {
	add_shortcode('mycomments', 'mce_list_comments');
}

add_filter('cron_schedules', 'mce_more_recurrences');
add_action('plugins_loaded', 'mce_widget_init');
add_action('wp_head', 'mce_head');
add_action('mce_import_cron', 'mce_import_cron');
add_action('mce_digest_cron', 'mce_digest_cron');

if (!empty($_REQUEST['mce_tweet'])) {
	add_action('init', 'mce_tweet');
}

function mce_install() {
	require MCE_DIR . '/install.php';
	mce_do_install();
}

function mce_uninstall() {
	require MCE_DIR . '/install.php';
	mce_do_uninstall();
}

function mce_more_recurrences($schedule) {
	$schedule['weekly'] = array('interval' => 604800, 'display' => __('Once Weekly'));
	
	return $schedule;
}

function mce_import_cron() {
	require MCE_DIR . '/import.php';
	mce_import();
}

function mce_digest_cron() {
	require MCE_DIR . '/digest.php';
	mce_digest();
}

function mce_head() {
	if (!is_admin()) {
		echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/my-comments-elsewhere/style.css" />' . "\n";
	}
}

function mce_tweet() {
	global $wpdb;
		
	$comment = $wpdb->get_row($wpdb->prepare("SELECT comment_url, blog_url FROM {$wpdb->prefix}my_comments WHERE comment_ID = %d", $_GET['mce_tweet']));
	
	if ($comment) {
		require MCE_DIR . '/import.php';
		$result = mce_url_open('http://tinyurl.com/api-create.php?url=' . $comment->comment_url);
		wp_redirect('http://twitter.com/home?status=' . urlencode('Check this comment out ' . $result['body'] . ($comment->blog_url ? ' at ' . $comment->blog_url : '')));
		die();
	} else {
		wp_die('Invalid comment.');
	}
}

function mce_list_comments($atts) {
	global $wpdb, $post, $mce_options;

	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
	
	if (!is_page()) {
		return '<p>' . __('You should add the shortcode to a page, not a post.', 'mce') . '</p>';
	}
		
	$post->comment_status = 'closed';
	$post->ping_status    = 'closed';
	
	if ($post->comment_count > 0) {
		$post->comment_count = 0;
		add_filter('comments_array', 'array'); //is this correct?
	}

	$mce_options['comments_per_page'] = (int) $mce_options['comments_per_page'];
	
	if (empty($mce_options['comments_per_page'])) {
		$mce_options['comments_per_page'] = get_option('comments_per_page');
	}
	
	if ($mce_options['comments_per_page'] < 0) {
		$mce_options['comments_per_page'] = absint($mce_options['comments_per_page']);
	}
	
	if (empty($mce_options['page_url']) || $mce_options['page_url'] != get_permalink()) {
		$mce_options['page_url'] = get_permalink();
		update_option('mce_options', $mce_options);
	}
	
	if (isset($_GET['cp'])) {
		$page = absint($_GET['cp']);
		if ($page <= 1 || $page != $_GET['cp']) {
			wp_redirect(get_permalink());
			die();
		}
	} else {
		$_GET['cp'] = 1;
		$page 		= 1;
	}
	
	$permalink = get_permalink();
	
	$qs = (strpos($permalink, '?') !== false ? '&' : '?');
				
	$comments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}my_comments ORDER BY comment_date DESC LIMIT " . (($page-1) * $mce_options['comments_per_page']) . ", " . $mce_options['comments_per_page']);
		
	$content = '';
	
	if (empty($comments)) {
		status_header(404);
		if ($page > 1) {
			$content .= '<p>' . __('Page not found.', 'mce') . '</p>';
		} else {
			$content .= '<p>' . __('No comments yet.', 'mce') . '</p>';
		}
	} else {
		$reply 				   = __('Reply', 'mce');
		$share				   = __('Share', 'mce');
		$tweet				   = __('Tweet', 'mce');
		$reply_to_this_comment = __('Reply to this comment', 'mce');
		$read_this_comment     = __('Read this comment', 'mce');
		$email_this_comment    = __('Email this comment', 'mce');
		$post_on_twitter       = __('Post on Twitter', 'mce');
		
		$no_follow  = (!$mce_options['dofollow_links'] ? ' rel="nofollow"' : '');
		$new_window = ($mce_options['open_links_in_new_window'] ? ' target="_blank"' : '');

		$gmt_offset  = get_option('gmt_offset') * 3600;
		
		if ($mce_options['date_format']) {
			$date_format = get_option('date_format');
			$time_format = get_option('time_format');
		}
		
		foreach ($comments as $comment) {
			$comment->comment_date = strtotime($comment->comment_date_gmt) + $gmt_offset;
			$content .= '
			<div class="mce_comment">
			<a href="' . $comment->post_url . '"' . $no_follow . $new_window . ' class="mce_url">' . ($comment->post_title ? $comment->post_title : 'No title') . '</a>';
			if ($mce_options['date_format']) {
				$content .= '<small class="mce_date">' . ($mce_options['date_format'] == 1 ? mce_relative_date($comment->comment_date) : date($date_format, $comment->comment_date) . ' at ' . date($time_format, $comment->comment_date)) . '</small>';
			}
			$content .= '
			<div class="mce_text">' . $comment->comment_content . '</div>';
			if ($mce_options['include_action_links']) {
				$content .= '<p class="mce_actions"><a href="' . $comment->comment_url . '" title="' . $reply_to_this_comment . '" rel="nofollow"' . $new_window . '>' . $reply . '</a> | <a href="mailto:?subject=' . rawurlencode($read_this_comment) . '&amp;body=' . rawurlencode($comment->comment_url) . '" title="' . $email_this_comment . '" rel="nofollow"' . $new_window . ' class="share">' . $share . '</a> | <a href="' . $qs . 'mce_tweet=' . urlencode($comment->comment_ID) . '" title="' . $post_on_twitter . '" rel="nofollow"' . $new_window . '>' . $tweet . '</a></p>';
			}
			$content .= '</div>';
		}
		
		$content .= '<p class="mce_pagination">' . mce_mce_pagination() . '</p>';

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
	}
	
	return $content;
}

function mce_mce_pagination() {			
	global $mce_options;
	if (empty($mce_options)) {
		$mce_options = get_option('mce_options');
	}
		
	$nr_pages = ceil($mce_options['comments_count'] / $mce_options['comments_per_page']);
		
	if ($nr_pages <= 1) {
		return; 
	}	

	$permalink = get_permalink();
	
	if (strpos($permalink, '?') !== false) {
		$paged_permalink = $permalink . '&cp=';
	} else {
		$paged_permalink = $permalink . '?cp=';
	}
	
	$permalink		 = clean_url($permalink);
	$paged_permalink = clean_url($paged_permalink);
	
	$page = $_GET['cp'];
	
	$output = '';
	
	if ($page > 1) {
		$before = $page - 4;
		$before = $before < 1 ? 1 : $before;
			
	 	$output .= '<a href="' . $permalink . '">&laquo; ' . __('first', 'mce') . '</a>&nbsp;';
		$output .= '<a href="' . ($page > 2 ? $paged_permalink . ($page-1) : $permalink) . '">‹ ' . __('previous', 'mce') . '</a>&nbsp;';
		if ($before > 1) {
			$output .= ' … ';
		}
		for ($i=$before; $i<$page; $i++) {
			$output .= '<a href="' . ($i > 1 ? $paged_permalink . $i : $permalink) . '">' . $i . '</a>&nbsp;';
		}
	}

	$output .= '<strong>' . $page . '</strong>&nbsp;';

	if ($page < $nr_pages) {
		$after = $page + 4;
		$after = $after > $nr_pages ? $nr_pages : $after;

		for ($i=$page+1; $i<=$after; $i++) {
			$output .= '<a href="' . $paged_permalink . $i . '">' . $i . '</a>&nbsp;';
		}
	}
	
	if ($page < $nr_pages) {
		if ($after < $nr_pages) {
			$output .= ' … ';
		}
		$output .= '<a href="' . $paged_permalink . ($page+1) . '">' . __('next', 'mce') . ' ›</a>&nbsp;';
		$output .= '<a href="' . $paged_permalink . $nr_pages . '">' . __('last', 'mce') . ' &raquo;</a>&nbsp;';
	}
		
	return $output;
}

function mce_widget_init() {
	if (!function_exists('register_sidebar_widget')) {
		return;
	}

	function mce_widget($args) {
		extract($args);
	
		global $mce_options;
	
		if (empty($mce_options)) {
			$mce_options = get_option('mce_options');
		}
			
		if (empty($mce_options['widget_title'])) {
			$mce_options['widget_title'] = __('My Comments', 'mce');
		}
				
		if (empty($mce_options['widget_cache'])) {
			global $wpdb;
			$mce_options['widget_cache'] = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}my_comments ORDER BY comment_date DESC LIMIT %d", $mce_options['widget_max_comments']));
		
			update_option('mce_options', $mce_options);
		}
		
		echo $before_widget;
		echo $before_title . $mce_options['widget_title'] . $after_title;
		echo '<ul id="mce_widget">';
		
		$no_follow  = (!$mce_options['dofollow_links'] ? ' rel="nofollow"' : '');
		$new_window = ($mce_options['open_links_in_new_window'] ? ' target="_blank"' : '');
		
		$gmt_offset = get_option('gmt_offset') * 3600;
		if ($mce_options['widget_date_format']) {
			$date_format = get_option('date_format');
			$time_format = get_option('time_format');
		}
		
		if ($mce_options['widget_cache']) {
			foreach ($mce_options['widget_cache'] as $comment) {
				if (!$comment->post_title) {
					if ($comment->blog_title) {
						$comment->post_title = $comment->blog_title;
					} else {
						$comment->post_title = 'No title';
					}
				}
				if ($mce_options['widget_trim_post_title']) {
					$comment->post_title_trimmed = mce_trim($comment->post_title, $mce_options['widget_trim_post_title']);
				} else {
					$comment->post_title_trimmed = $comment->post_title;
				}
				if ($mce_options['widget_trim_excerpt']) {
					$comment->comment_content = mce_trim($comment->comment_content, $mce_options['widget_trim_excerpt']);
				} 
				$comment->comment_date = strtotime($comment->comment_date) + $gmt_offset;
				echo '<li>';
				if ($mce_options['widget_show_post_title']) {
					echo '<a class="mce_widget_title" href="' . $comment->post_url . '" title="' . $comment->post_title . '"' . $no_follow . $new_window . '>' . $comment->post_title_trimmed . '</a>';
					if ($mce_options['widget_date_format']) {
						echo '<div class="mce_widget_date">' . ($mce_options['widget_date_format'] == 1 ? mce_relative_date($comment->comment_date) : date($date_format, $comment->comment_date) . ' at ' . date($time_format, $comment->comment_date)) . '</div>';
					}
				}
				if ($mce_options['widget_show_excerpt']) {
					if (!$mce_options['widget_show_post_title']) {
						echo '<a href="' . $comment->post_url . '" title="' . htmlspecialchars($comment->post_title) . '"' . $no_follow . $new_window . '>';
					} else if (!$mce_options['widget_show_date']) {
						echo '<div>';
					}
					echo '<span class="mce_widget_excerpt">' . $comment->comment_content . '</span>';
					if (!$mce_options['widget_show_post_title']) {
						echo '</a>';
					}
					if (!$mce_options['widget_show_post_title'] && $mce_options['widget_date_format']) {
						echo '<div class="mce_widget_date">' . ($mce_options['widget_date_format'] == 1 ? mce_relative_date($comment->comment_date) : date($date_format, $comment->comment_date) . ' at ' . date($time_format, $comment->comment_date)) . '</div>';
					}
					if ($mce_options['widget_show_post_title'] && !$mce_options['widget_show_date']) {	
						echo '</div>';
					}
				}
				echo '</li>';
			}
					
			if ($mce_options['widget_link_to_page'] && $mce_options['page_url'] && $mce_options['widget_link_title']) {
				echo '<li class="mce_widget_footer"><a href="' . $mce_options['page_url'] . '">' . $mce_options['widget_link_title'] . '</a></li>';
			}
		} else {
			echo '<li>' . __('None found', 'mce') . '</li>';
		}
	
		echo '</ul>';
								
		echo $after_widget;
	}
	
	function mce_widget_control() {
		require MCE_DIR . '/widget_control.php';
	}

	register_sidebar_widget(__('My Comments', 'mce'), 'mce_widget');
	register_widget_control(__('My Comments', 'mce'), 'mce_widget_control');
}

function mce_trim($text, $chars=120) {
	if (strlen($text) > $chars) {
		$text = strip_tags($text);
		$text = substr($text, 0, $chars-3);
		$text = trim(substr($text, 0, strrpos($text, ' ')));
		$text .= '...';
	}
	
	return $text;
}

function mce_relative_date($timestamp) { 	
	$time_diff = time() - $timestamp;
	
	if ($time_diff < 120) {
		if ($time_diff <= 1) {
			return '1 second ago';
		} else if ($time_diff < 60) {
			return $time_diff . ' seconds ago';
		} else {
			return '1 minute ago';
		}
	} else if ($time_diff < 3600) {
   		return intval($time_diff / 60) . ' minutes ago';
    } else if ($time_diff < 7200) { 
  		return '1 hour ago'; 
    } else if ($time_diff < 86400) { 
    	return intval($time_diff / 3600) . ' hours ago';
	} else if ($time_diff < 172800) { 
    	return '1 day ago'; 
    } else if ($time_diff < 604800) { 
    	return intval($time_diff / 86400) . ' days ago';
    } else if ($time_diff < 1209600) { 
    	return '1 week ago'; 
    } else if ($time_diff < 3024000) { 
       return intval($time_diff / 604900) . ' weeks ago';
    } else { 
    	return date('M j, Y', $timestamp); 
    }
}	
?>
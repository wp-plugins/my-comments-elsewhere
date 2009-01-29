<?php
if (!defined('WP_CONTENT_URL')) die;

global $mce_options;

if (empty($mce_options)) {
	$mce_options = get_option('mce_options');
}

if (!empty($_POST['mce_widget_save'])) {			
	$mce_options['widget_title'] 		   = strip_tags(stripslashes($_POST['mce_widget_title']));
	$mce_options['widget_max_comments']    = (int) $_POST['mce_widget_max_comments'];
	$mce_options['widget_show_post_title'] = (int) $_POST['mce_widget_show_post_title'];
	$mce_options['widget_trim_post_title'] = (int) $_POST['mce_widget_trim_post_title'];
	$mce_options['widget_show_excerpt']	   = (int) $_POST['mce_widget_show_excerpt'];
	$mce_options['widget_trim_excerpt']	   = (int) $_POST['mce_widget_trim_excerpt'];
	$mce_options['widget_date_format']	   = (int) $_POST['mce_widget_date_format'];
	$mce_options['widget_link_to_page']    = (int) $_POST['mce_widget_link_to_page'];
	
	if (!$mce_options['widget_show_post_title'] && !$mce_options['widget_show_excerpt']) {
		$mce_options['widget_show_post_title'] = 1;
	}
	if ($mce_options['widget_max_comments'] < 3) {
		$mce_options['widget_max_comments'] = 3;
	} else if ($mce_options['widget_max_comments'] > 50) {
		$mce_options['widget_max_comments'] = 50;
	}
	
	if ($mce_options['widget_trim_post_title'] && $mce_options['widget_trim_post_title'] < 20) {
		$mce_options['widget_trim_post_title'] = 20;
	}
	if ($mce_options['widget_trim_excerpt'] && $mce_options['widget_trim_excerpt'] < 20) {
		$mce_options['widget_trim_excerpt'] = 20;
	}
	
	unset($mce_options['widget_cache']);
	unset($mce_options['widget_cache_date']);
	
	update_option('mce_options', $mce_options);
}
?>
<style type="text/css">
#mce_widget_widget_options td { padding-bottom: 5px; }
</style>
<p>
<label for="mce_widget_title">
	<?php _e('Title:'); ?>
	<input class="widefat" id="mce_widget_title" name="mce_widget_title" type="text" value="<?php echo attribute_escape($mce_options['widget_title']); ?>" />
</label>
</p>
<table id="mce_widget_widget_options">
<tr>
<td>
	<label for="mce_widget_max_comments"><?php _e('Nr. of comments:', 'mce'); ?> </label>
</td>
<td>	
	<input style="width: 30px; text-align: center;" id="mce_widget_max_comments" name="mce_widget_max_comments" type="text" value="<?php echo $mce_options['widget_max_comments']; ?>" />
</td>
</tr>
<tr>
<td>
	<label for="mce_widget_show_post_title"><?php _e('Show post title:', 'mce'); ?></label>
</td>
<td>
	<select name="mce_widget_show_post_title" id="mce_widget_show_post_title">
	<option value="1" <?php if ($mce_options['widget_show_post_title'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['widget_show_post_title'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
<tr>
<td>	
	<label for="mce_widget_trim_post_title"><?php _e('Trim post titles at:', 'mce'); ?></label>
</td>
<td>
	<input style="width: 35px; text-align: center;" type="text" name="mce_widget_trim_post_title" id="mce_widget_trim_post_title" value="<?php echo $mce_options['widget_trim_post_title']; ?>" size="3" /> <?php _e('chars', 'mce'); ?>
</td>
</tr>
<tr>
<td>
	<label for="mce_widget_show_excerpt"><?php _e('Show excerpt:', 'mce'); ?></label>
</td>
<td>
	<select name="mce_widget_show_excerpt" id="mce_widget_show_excerpt">
	<option value="1" <?php if ($mce_options['widget_show_excerpt'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['widget_show_excerpt'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
</tr>
<tr>
<td>
	<label for="mce_widget_trim_excerpt"><?php _e('Trim excerpts at:', 'mce'); ?></label>
</td>
<td>
	<input style="width: 35px; text-align: center;" type="text" name="mce_widget_trim_excerpt" id="mce_widget_trim_excerpt" value="<?php echo $mce_options['widget_trim_excerpt']; ?>" size="3" /> <?php _e('chars', 'mce'); ?>
</td>
</tr>
<tr>
<td>
	<label for="mce_widget_date_format"><?php _e('Show date:', 'mce'); ?></label>
</td>
<td>
	<select name="mce_widget_date_format" id="mce_widget_date_format">
	<option value="1" <?php if ($mce_options['widget_date_format'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Relative date', 'mce'); ?></option>
	<option value="2" <?php if ($mce_options['widget_date_format'] == 2): ?>selected="selected"<?php endif; ?>><?php _e('Full date', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['widget_date_format'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('Do not display', 'mce'); ?></option>
	</select>
</tr>
<tr>
<td>
	<label for="mce_widget_link_to_page"><?php _e('Show more link:', 'mce'); ?></label>
</td>
<td>
	<select name="mce_widget_link_to_page" id="mce_widget_link_to_page">
	<option value="1" <?php if ($mce_options['widget_link_to_page'] == 1): ?>selected="selected"<?php endif; ?>><?php _e('Yes', 'mce'); ?></option>
	<option value="0" <?php if ($mce_options['widget_link_to_page'] == 0): ?>selected="selected"<?php endif; ?>><?php _e('No', 'mce'); ?></option>
	</select>
</tr>
</table>
</p>

<input type="hidden" id="mce_widget_save" name="mce_widget_save" value="1" />
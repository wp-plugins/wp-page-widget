<?php

/*
  Plugin Name: Wordpress Page Widgets
  Plugin URI: http://codeandmore.com/products/wordpress-plugins/wp-page-widget/
  Description: Allow users to customize Widgets per page.
  Author: CodeAndMore
  Version: 1.0
  Author URI: http://codeandmore.com/
 */

/* Hooks */
add_action('admin_print_scripts', 'pw_print_scripts');
add_action('admin_print_styles', 'pw_print_styles');
add_action('admin_menu', 'pw_admin_menu');

/* AJAX Hooks */
add_action('wp_ajax_pw-widgets-order', 'pw_ajax_widgets_order');
add_action('wp_ajax_pw-save-widget', 'pw_ajax_save_widget');

/* Filters */
add_filter('sidebars_widgets', 'pw_filter_widgets');

function pw_print_scripts() {
	global $pagenow, $typenow;

	// currently this plugin just work on edit page screen.
	if ( in_array($pagenow, array('post-new.php', 'post.php')) ) {
		wp_enqueue_script('pw-widgets', plugin_dir_url(__FILE__) . 'assets/js/page-widgets.js', array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'), '0.1', true);
	}
}

function pw_print_styles() {
	global $pagenow, $typenow;

	// currently this plugin just work on edit page screen.
	if ( in_array($pagenow, array('post-new.php', 'post.php')) ) {
		wp_enqueue_style('pw-widgets', plugin_dir_url(__FILE__) . 'assets/css/page-widgets.css', array(), '0.1');
	}
}

function pw_admin_menu() {
	// check user capability (only allow Editor or above to customize Widgets

	$settings = pw_get_settings();

	if (current_user_can('edit_theme_options')) {
		// add Page Widgets metabox
		foreach ($settings['post_types'] as $post_type) {
			add_meta_box('pw-widgets', 'Page Widgets', 'pw_metabox_content', $post_type, 'advanced', 'high');
		}
	}

	// options page
	add_options_page('Page Widgets', 'Page Widgets', 'manage_options', 'pw-settings', 'pw_settings_page');
}

function pw_settings_page() {
	global $wp_registered_sidebars;

	if ( $_POST['save-changes'] ) {
		$opts = stripslashes_deep($_POST['pw_opts']);

		update_option('pw_options', $opts);
		echo '<div id="message" class="update faded"><p>Saved Changes</p></div>';
	}

	$opts = pw_get_settings();
	$post_types = get_post_types('', false);
	?>
<div class="wrap">
	<h2>Settings - Page Widgets</h2>

	<form action="" method="post">
		<table class="form-table">
			<tr>
				<th>Available for post type</th>
				<td>
					<?php foreach ( $post_types as $post_type => $post_type_obj) {
						if ( in_array($post_type, array('attachment', 'revision', 'nav_menu_item')) ) continue;
						echo '<input type="checkbox" name="pw_opts[post_types][]" value="'.$post_type.'" '.checked(true, in_array($post_type, (array) $opts['post_types']), false).' /> '.$post_type_obj->labels->singular_name.'<br />';
					} ?>
				</td>
			</tr>
			<tr>
				<th>Which sidebars you want to customize</th>
				<td>
					<?php foreach ($wp_registered_sidebars as $sidebar => $registered_sidebar) {
						echo '<input type="checkbox" name="pw_opts[sidebars][]" value="'.$sidebar.'" '.checked(true, in_array($sidebar, (array) $opts['sidebars']), false).' /> '.$registered_sidebar['name'].'<br />';
					} ?>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" name="save-changes" value="Save Changes" />
		</p>
	</form>
</div>
	<?php
}

function pw_get_settings() {
	$defaults = array(
	    'post_types' => array('post', 'page'),
	    'sidebars' => array(),
	);

	$settings = get_option('pw_options', array());
	return wp_parse_args($settings, $defaults);
}

function pw_metabox_content($post) {
	global $wp_registered_sidebars, $sidebars_widgets, $wp_registered_widgets;

	$settings = pw_get_settings();

	// register the inactive_widgets area as sidebar
	register_sidebar(array(
		'name' => __('Inactive Widgets'),
		'id' => 'wp_inactive_widgets',
		'description' => '',
		'before_widget' => '',
		'after_widget' => '',
		'before_title' => '',
		'after_title' => '',
	));

	$sidebars_widgets = wp_get_sidebars_widgets();
	if ( empty( $sidebars_widgets ) )
		$sidebars_widgets = wp_get_widget_defaults();


	// include widgets function
	if ( !function_exists('wp_list_widgets') )
		require_once(ABSPATH . '/wp-admin/includes/widgets.php');
	?>
<div class="widget-liquid-left">
<div id="widgets-left">
	<div id="available-widgets" class="widgets-holder-wrap">
		<div class="sidebar-name">
		<div class="sidebar-name-arrow"><br /></div>
		<h3><?php _e('Available Widgets'); ?> <span id="removing-widget"><?php _e('Deactivate'); ?> <span></span></span></h3></div>
		<div class="widget-holder">
		<p class="description"><?php _e('Drag widgets from here to a sidebar on the right to activate them. Drag widgets back here to deactivate them and delete their settings.'); ?></p>
		<div id="widget-list">
		<?php wp_list_widgets(); ?>
		</div>
		<br class='clear' />
		</div>
		<br class="clear" />
	</div>

	<div class="widgets-holder-wrap">
		<div class="sidebar-name">
		<div class="sidebar-name-arrow"><br /></div>
		<h3><?php _e('Inactive Widgets'); ?>
		<span><img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-feedback" title="" alt="" /></span></h3></div>
		<div class="widget-holder inactive">
		<p class="description"><?php _e('Drag widgets here to remove them from the sidebar but keep their settings.'); ?></p>
		<?php wp_list_widget_controls('wp_inactive_widgets'); ?>
		<br class="clear" />
		</div>
	</div>
</div>
</div>

<div class="widget-liquid-right">
<div id="widgets-right">
<?php
$i = 0;
foreach ( $wp_registered_sidebars as $sidebar => $registered_sidebar ) {
	if ( 'wp_inactive_widgets' == $sidebar )
		continue;
	if (  !in_array($sidebar, $settings['sidebars']) )
		continue;
	$closed = $i ? ' closed' : ''; ?>
	<div class="widgets-holder-wrap<?php echo $closed; ?>">
	<div class="sidebar-name">
	<div class="sidebar-name-arrow"><br /></div>
	<h3><?php echo esc_html( $registered_sidebar['name'] ); ?>
	<span><img src="<?php echo esc_url( admin_url( 'images/wpspin_dark.gif' ) ); ?>" class="ajax-feedback" title="" alt="" /></span></h3></div>
	<?php wp_list_widget_controls( $sidebar ); // Show the control forms for each of the widgets in this sidebar ?>
	</div>
<?php
	$i++;
} ?>
</div>
</div>
<form action="" method="post">
<?php wp_nonce_field( 'save-sidebar-widgets', '_wpnonce_widgets', false ); ?>
</form>
<br class="clear" />
	<?php
}

function pw_ajax_widgets_order() {
	check_ajax_referer( 'save-sidebar-widgets', 'savewidgets' );

	if ( !current_user_can('edit_theme_options') )
		die('-1');

	if ( !$_POST['post_id'] )
		die('-1');

	$post_id = stripslashes($_POST['post_id']);

	unset( $_POST['savewidgets'], $_POST['action'] );

	// save widgets order for all sidebars
	if ( is_array($_POST['sidebars']) ) {
		$sidebars = array();
		foreach ( $_POST['sidebars'] as $key => $val ) {
			$sb = array();
			if ( !empty($val) ) {
				$val = explode(',', $val);
				foreach ( $val as $k => $v ) {
					if ( strpos($v, 'widget-') === false )
						continue;

					$sb[$k] = substr($v, strpos($v, '_') + 1);
				}
			}
			$sidebars[$key] = $sb;
		}
		pw_set_sidebars_widgets($sidebars, $post_id);
		die('1');
	}

	die('-1');
}

function pw_ajax_save_widget() {
	global $wp_registered_widget_controls, $wp_registered_widgets, $wp_registered_widget_updates;

	check_ajax_referer( 'save-sidebar-widgets', 'savewidgets' );

	if ( !current_user_can('edit_theme_options') || !isset($_POST['id_base']) )
		die('-1');

	if ( !$_POST['post_id'] )
		die('-1');

	$post_id = stripslashes($_POST['post_id']);

	unset( $_POST['savewidgets'], $_POST['action'] );

	do_action('load-widgets.php');
	do_action('widgets.php');
	do_action('sidebar_admin_setup');

	$id_base = $_POST['id_base'];
	$widget_id = $_POST['widget-id'];
	$sidebar_id = $_POST['sidebar'];
	$multi_number = !empty($_POST['multi_number']) ? (int) $_POST['multi_number'] : 0;
	$settings = isset($_POST['widget-' . $id_base]) && is_array($_POST['widget-' . $id_base]) ? $_POST['widget-' . $id_base] : false;
	$error = '<p>' . __('An error has occured. Please reload the page and try again.') . '</p>';

	$sidebars = wp_get_sidebars_widgets();
	$sidebar = isset($sidebars[$sidebar_id]) ? $sidebars[$sidebar_id] : array();

	// delete
	if ( isset($_POST['delete_widget']) && $_POST['delete_widget'] ) {

		if ( !isset($wp_registered_widgets[$widget_id]) )
			die($error);

		$sidebar = array_diff( $sidebar, array($widget_id) );
		$_POST = array('sidebar' => $sidebar_id, 'widget-' . $id_base => array(), 'the-widget-id' => $widget_id, 'delete_widget' => '1');
	} elseif ( $settings && preg_match( '/__i__|%i%/', key($settings) ) ) {
		if ( !$multi_number )
			die($error);

		$_POST['widget-' . $id_base] = array( $multi_number => array_shift($settings) );
		$widget_id = $id_base . '-' . $multi_number;
		$sidebar[] = $widget_id;
	}
	$_POST['widget-id'] = $sidebar;

	if ( !isset($_POST['delete_widget']) && !$_POST['delete_widget'] ) {
		foreach ( (array) $wp_registered_widget_updates as $name => $control ) {

			if ( $name == $id_base ) {
				if ( !is_callable( $control['callback'] ) )
					continue;

				ob_start();
					call_user_func_array( $control['callback'], $control['params'] );
				ob_end_clean();
				break;
			}
		}
	}

	if ( isset($_POST['delete_widget']) && $_POST['delete_widget'] ) {
		$sidebars[$sidebar_id] = $sidebar;
		pw_set_sidebars_widgets($sidebars);
		echo "deleted:$widget_id";
		die();
	}

	if ( !empty($_POST['add_new']) )
		die();

	if ( $form = $wp_registered_widget_controls[$widget_id] )
		call_user_func_array( $form['callback'], $form['params'] );

	die();
}


function pw_set_sidebars_widgets($sidebars_widgets, $post_id) {
	if ( !isset( $sidebars_widgets['array_version'] ) )
		$sidebars_widgets['array_version'] = 3;
	update_post_meta($post_id, '_sidebars_widgets', $sidebars_widgets);
}


/* Use this to get sidebars widgets */
function pw_get_sidebars_widgets($deprecated = true) {
	if ( $deprecated !== true )
		_deprecated_argument( __FUNCTION__, '2.8.1' );

	global $post, $wp_registered_widgets, $wp_registered_sidebars, $_wp_sidebars_widgets;

	// If loading from front page, consult $_wp_sidebars_widgets rather than options
	// to see if wp_convert_widget_settings() has made manipulations in memory.
	if ( !is_admin() ) {
		if ( empty($_wp_sidebars_widgets) ) {
			$_wp_sidebars_widgets = get_post_meta($post->ID, '_sidebars_widgets', true);
			if ( empty($_wp_sidebars_widgets) )
			$_wp_sidebars_widgets = get_option('sidebars_widgets', array());
		}

		$sidebars_widgets = $_wp_sidebars_widgets;
	} else {
		$sidebars_widgets = get_post_meta($post->ID, '_sidebars_widgets', true);
		if ( empty($sidebars_widgets) )
			$sidebars_widgets = get_option('sidebars_widgets', array());
		$_sidebars_widgets = array();

		if ( isset($sidebars_widgets['wp_inactive_widgets']) || empty($sidebars_widgets) )
			$sidebars_widgets['array_version'] = 3;
		elseif ( !isset($sidebars_widgets['array_version']) )
			$sidebars_widgets['array_version'] = 1;

		switch ( $sidebars_widgets['array_version'] ) {
			case 1 :
				foreach ( (array) $sidebars_widgets as $index => $sidebar )
				if ( is_array($sidebar) )
				foreach ( (array) $sidebar as $i => $name ) {
					$id = strtolower($name);
					if ( isset($wp_registered_widgets[$id]) ) {
						$_sidebars_widgets[$index][$i] = $id;
						continue;
					}
					$id = sanitize_title($name);
					if ( isset($wp_registered_widgets[$id]) ) {
						$_sidebars_widgets[$index][$i] = $id;
						continue;
					}

					$found = false;

					foreach ( $wp_registered_widgets as $widget_id => $widget ) {
						if ( strtolower($widget['name']) == strtolower($name) ) {
							$_sidebars_widgets[$index][$i] = $widget['id'];
							$found = true;
							break;
						} elseif ( sanitize_title($widget['name']) == sanitize_title($name) ) {
							$_sidebars_widgets[$index][$i] = $widget['id'];
							$found = true;
							break;
						}
					}

					if ( $found )
						continue;

					unset($_sidebars_widgets[$index][$i]);
				}
				$_sidebars_widgets['array_version'] = 2;
				$sidebars_widgets = $_sidebars_widgets;
				unset($_sidebars_widgets);

			case 2 :
				$sidebars = array_keys( $wp_registered_sidebars );
				if ( !empty( $sidebars ) ) {
					// Move the known-good ones first
					foreach ( (array) $sidebars as $id ) {
						if ( array_key_exists( $id, $sidebars_widgets ) ) {
							$_sidebars_widgets[$id] = $sidebars_widgets[$id];
							unset($sidebars_widgets[$id], $sidebars[$id]);
						}
					}

					// move the rest to wp_inactive_widgets
					if ( !isset($_sidebars_widgets['wp_inactive_widgets']) )
						$_sidebars_widgets['wp_inactive_widgets'] = array();

					if ( !empty($sidebars_widgets) ) {
						foreach ( $sidebars_widgets as $lost => $val ) {
							if ( is_array($val) )
								$_sidebars_widgets['wp_inactive_widgets'] = array_merge( (array) $_sidebars_widgets['wp_inactive_widgets'], $val );
						}
					}

					$sidebars_widgets = $_sidebars_widgets;
					unset($_sidebars_widgets);
				}
		}
	}

	if ( is_array( $sidebars_widgets ) && isset($sidebars_widgets['array_version']) )
		unset($sidebars_widgets['array_version']);

	$sidebars_widgets = apply_filters('sidebars_widgets', $sidebars_widgets);
	return $sidebars_widgets;
}

function pw_filter_widgets($sidebars_widgets) {
	global $post;

	if ( ( !is_admin() && !is_singular() ) && ( is_admin() && !in_array($pagenow, array('post-new.php', 'post.php')) ) )
		return $sidebars_widgets;

	$_sidebars_widgets = get_post_meta($post->ID, '_sidebars_widgets', true);

	if ( !empty($_sidebars_widgets) ) {
		if ( is_array( $_sidebars_widgets ) && isset($_sidebars_widgets['array_version']) )
		unset($_sidebars_widgets['array_version']);

		$sidebars_widgets = array_merge($sidebars_widgets, $_sidebars_widgets);
	}

	return $sidebars_widgets;
}
?>
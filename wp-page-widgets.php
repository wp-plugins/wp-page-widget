<?php

/*
  Plugin Name: Wordpress Page Widgets
  Plugin URI: http://codeandmore.com/products/wordpress-plugins/wp-page-widget/
  Description: Allow users to customize Widgets per page.
  Author: CodeAndMore
  Version: 1.1
  Author URI: http://codeandmore.com/
 */

define('PAGE_WIDGET_VERSION', '1.1');

/* Hooks */
add_action('admin_init', 'pw_init');
add_action('admin_print_scripts', 'pw_print_scripts');
add_action('admin_print_styles', 'pw_print_styles');
add_action('admin_menu', 'pw_admin_menu');
add_action('save_post', 'pw_save_post', 10, 2);

/* AJAX Hooks */
add_action('wp_ajax_pw-widgets-order', 'pw_ajax_widgets_order');
add_action('wp_ajax_pw-save-widget', 'pw_ajax_save_widget');
add_action('wp_ajax_pw-toggle-customize', 'pw_ajax_toggle_customize');
add_action('wp_ajax_pw-reset-customize', 'pw_ajax_reset_customize');

/* Filters */
add_filter('sidebars_widgets', 'pw_filter_widgets');
add_filter('widget_display_callback', 'pw_filter_widget_display_instance', 10, 3);
add_filter('widget_form_callback', 'pw_filter_widget_form_instance', 10, 2);

function pw_init() {
	global $wpdb;

	$current_version = get_option('page_widget_version', '1.0');
	$upgraded = false;

	if ( version_compare($current_version, '1.1', '<') ) {
		// we set enable customize sidebars for posts which hve been customized before.
		$post_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key LIKE %s", '_sidebars_widgets'));

		if ( !empty($post_ids) ) {
			foreach ($post_ids as $post_id) {
				update_post_meta($post_id, '_customize_sidebars', 'yes');
			}
		}

		$upgraded = true;
	}

	if ( $upgraded ) {
		update_option('page_widget_version', PAGE_WIDGET_VERSION);
	}
}

function pw_print_scripts() {
	global $pagenow, $typenow;

	// currently this plugin just work on edit page screen.
	if ( in_array($pagenow, array('post-new.php', 'post.php')) ) {
		wp_enqueue_script('pw-widgets', plugin_dir_url(__FILE__) . 'assets/js/page-widgets.js', array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'), '1.0', true);
	}
}

function pw_print_styles() {
	global $pagenow, $typenow;

	// currently this plugin just work on edit page screen.
	if ( in_array($pagenow, array('post-new.php', 'post.php')) ) {
		wp_enqueue_style('pw-widgets', plugin_dir_url(__FILE__) . 'assets/css/page-widgets.css', array(), '1.0');
	}

	wp_enqueue_style('pw-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0');
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
		echo '<div id="message" class="updated fade"><p>Saved Changes</p></div>';
	}

	$opts = pw_get_settings();
	$post_types = get_post_types('', false);
	?>
<div class="wrap">
	<h2>Settings - Page Widgets</h2>

	<div class="liquid-wrap">
		<div class="liquid-left">
			<div class="panel-left">
				<form action="" method="post">
					<table class="form-table">
						<tr>
							<th>Would you like to make a donation?</th>
							<td>
								<input type="radio" name="pw_opts[donation]" value="yes" <?php checked("yes", $opts['donation']) ?> /> Yes I have donated at least $5. Thank you for your nice work. And hide the donation message please. <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=X2CJ88BHMLAT6">Donate Now</a>.
								<br />
								<input type="radio" name="pw_opts[donation]" value="no" <?php checked("no", $opts['donation']) ?> /> No, I want to use this without donation.

							</td>
						</tr>
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
		</div>
		
		<div class="liquid-right">
			<div class="panel-right">
<!--				<div class="panel-box">
					<div class="handlediv"><br /></div>
					<h3 class="hndle">Test</h3>
					<div class="inside">
						
					</div>
				</div>-->
			</div>
		</div>
	</div>
</div>
	<?php
}

function pw_get_settings() {
	$defaults = array(
	    'donation' => 'no',
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


	$customize = get_post_meta($post->ID, '_customize_sidebars', true);
	if ( !$customize ) $customize = 'no';

	// include widgets function
	if ( !function_exists('wp_list_widgets') )
		require_once(ABSPATH . '/wp-admin/includes/widgets.php');
	?>
<form style="display: none;" action="" method="post"></form>

<div style="padding: 5px;">
	<?php if ( $settings['donation'] != 'yes' ) {
		echo '<div id="donation-message"><p>Thank you for using this plugin. If you appreciate our works, please consider to <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=X2CJ88BHMLAT6">donate us</a>. With your help, we can continue supporting and developing this plugin.<br /><a href="'.admin_url('options-general.php?page=pw-settings').'"><small>Hide this donation message</small></a>.</p></div>';
	}?>
</div>

<div style="padding: 5px;">
<!--	<a id="pw-button-customize" class="<?php echo $pw_class ?>" href="#"><span class="customize">Customize</span><span class="default">Default</span></a>-->
	<input type="radio" class="pw-toggle-customize" name="pw-customize-sidebars" value="no" <?php checked($customize, 'no') ?> /> Default (follow <a href="<?php echo admin_url('widgets.php') ?>">Widgets settings</a>)
	&nbsp;&nbsp;&nbsp;<input class="pw-toggle-customize" type="radio" name="pw-customize-sidebars" value="yes" <?php checked($customize, 'yes') ?> /> Customize
	<br class="clear" />
</div>

<div id="pw-sidebars-customize">
	<input type="hidden" name="pw-sidebar-customize" value="0" />

	<div class="widget-liquid-left">
	<div id="widgets-left">
		<div id="available-widgets" class="widgets-holder-wrap">
			<div class="sidebar-name">
				<div class="sidebar-name-arrow"><br /></div>
				<h3><?php _e('Available Widgets'); ?> <span id="removing-widget"><?php _e('Deactivate'); ?> <span></span></span></h3>
			</div>
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
				<span><img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-feedback" title="" alt="" /></span></h3>
			</div>
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
				<span><img src="<?php echo esc_url( admin_url( 'images/wpspin_dark.gif' ) ); ?>" class="ajax-feedback" title="" alt="" /></span></h3>
			</div>
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

</div><!-- End #pw-sidebars-customize -->
	<?php
}

function pw_ajax_toggle_customize() {
	$status = stripslashes($_POST['pw-customize-sidebars']);
	$post_id = (int) $_POST['post_id'];

	if ( !in_array($status, array('yes', 'no')) ) $status = 'no';

	$post_type = get_post_type($post_id);
	$post_type_object = get_post_type_object( $post_type );

	if ( current_user_can($post_type_object->cap->edit_posts) ) {
		update_post_meta($post_id, '_customize_sidebars', $status);
		echo 1;
	}

	exit(0);
}

function pw_save_post($post_id, $post) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;

	if ( isset($_POST['pw-customize-sidebars']) ) {
		$status = stripslashes($_POST['pw-customize-sidebars']);

		if ( !in_array($status, array('yes', 'no')) ) $status = 'no';

		$post_type = get_post_type($post);
		$post_type_object = get_post_type_object( $post_type );

		if ( current_user_can($post_type_object->cap->edit_posts) ) {
			update_post_meta($post_id, '_customize_sidebars', $status);
		}
	}

	return $post_id;
}

//function pw_ajax_reset_customize() {
//	global $wpdb;
//
//	$post_id = (int) $_POST['post_id'];
//
//	$post_type = get_post_type($post_id);
//	$post_type_object = get_post_type_object( $post_type );
//
//	if ( current_user_can($post_type_object->cap->edit_posts) ) {
//		delete_post_meta($post_id, '_sidebars_widgets');
//		$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE 'widget_{$post_id}_%%'"));
//		echo 1;
//	}
//
//	exit(0);
//}

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

				// do some hack
				$number = $multi_number > 0 ? $multi_number : (int)$_POST['widget_number'];
				$all_instance = $control['callback'][0]->get_settings();

				if ( !isset($all_instance[$number]) ) { // that's mean new widget was added. => call update function to add widget (globally).
					ob_start();
						call_user_func_array( $control['callback'], $control['params'] );
					ob_end_clean();
				} else { // mean existing widget was saved. => save separate settings for each post (avoid to overwrite global existing widget data.
					$widget_obj = &$control['callback'][0];
					$widget_obj->option_name = 'widget_'.$post_id.'_'.$widget_obj->id_base;

					ob_start();
						call_user_func_array( $control['callback'], $control['params'] );
					ob_end_clean();
				}
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

function pw_filter_widgets($sidebars_widgets) {
	global $post, $pagenow;

	if ( (is_admin() && !in_array($pagenow, array('post-new.php', 'post.php')))
		|| (!is_admin() && !is_single())
		)
		return $sidebars_widgets;

	$enable_customize = get_post_meta($post->ID, '_customize_sidebars', true);
	$_sidebars_widgets = get_post_meta($post->ID, '_sidebars_widgets', true);

	if ( $enable_customize == 'yes' && !empty($_sidebars_widgets) ) {
		if ( is_array( $_sidebars_widgets ) && isset($_sidebars_widgets['array_version']) )
			unset($_sidebars_widgets['array_version']);

		$sidebars_widgets = wp_parse_args($_sidebars_widgets, $sidebars_widgets);
	}

	return $sidebars_widgets;
}

function pw_filter_widget_display_instance($instance, $widget, $args) {
	global $post;

	$enable_customize = get_post_meta($post->ID, '_customize_sidebars', true);

	if ( $enable_customize == 'yes' &&  is_single() ) {
		$widget_instance = get_option('widget_'.$post->ID.'_'.$widget->id_base);

		if ( $widget_instance && isset($widget_instance[$widget->number]) ) {

			$instance = $widget_instance[$widget->number];
		}
	}

	return $instance;
}

function pw_filter_widget_form_instance($instance, $widget) {
	global $post, $pagenow;

	//$enable_customize = get_post_meta($post->ID, '_customize_sidebars', true);

	if ( (is_admin() && in_array($pagenow, array('post-new.php', 'post.php'))) ) {
		$widget_instance = get_option('widget_'.$post->ID.'_'.$widget->id_base);

		if ( $widget_instance && isset($widget_instance[$widget->number]) ) {

			$instance = $widget_instance[$widget->number];
		}
	}

	return $instance;
}
?>
<?php
/* 
Plugin Name: PCM Pending Posts
URI: http://pcmnw.com
Description: Pending Post notification Alert. <strong>Do Not Network Activiate.</strong> Alert via email, select editors when a contributor submits a post for review. Also add a Pending Post count to each domain on Sites > All Sites page.
Version: 1.1.0
Author: SatakeST
Author URI: http://objectfoo.com
License: GPLv2
*/

/**
 * Defines
 * **************************************************/
// preference array
define( 'PCM_OPTION_GROUP_PP', 'PCM_OPTION_GROUP_PP' );

// preference array keys
define( 'PCM_PRIMARY_EDITOR_KEY', 'primary_editor_email' );
define( 'PCM_SECONDARY_EDITOR_KEY', 'secondary_editor_email' );

/**
 * Activate / Deactivate
 **************************************************************************** */
register_activation_hook( __FILE__, 'pcm_pending_post_setup_pp');
function pcm_pending_post_setup_pp() {
	// default options
	$o = Array(
		PCM_PRIMARY_EDITOR_KEY => '',
		PCM_SECONDARY_EDITOR_KEY => 'gregory@nwempire.com'
		);

	add_option(PCM_OPTION_GROUP_PP, $o);
}

/**
 * Setup plugin
 * 
 * hook some function into the WP execution at hook points
 * 
 * @param type $var-name1 Description
 * @return type 
 **************************************************************************** */
add_action( 'plugins_loaded', 'setup_wpp_sat');
function setup_wpp_sat() {
	add_filter( 'myblogs_blog_actions', 'my_sites_pending_posts_pp', 100, 2 );
	add_action( 'draft_to_pending', 'new_pending_post_pp', 100, 1 );
	add_action( 'admin_menu', 'pending_posts_settings_pp' );
	add_action( 'admin_init', 'pending_posts_admin_init_pp' );
}

/**
 * Pending Post count on My Sites admin page
 **************************************************************************** */
function my_sites_pending_posts_pp($current_menu, $user_blog) {
	
	if ( !current_user_can( 'edit_others_posts' ) ) {
		return $current_menu;
	}

	switch_to_blog( $user_blog->userblog_id );

	$posts = wp_count_posts( 'post', 'readable' );
	$pendingPosts = ( !empty( $posts->pending ) ) ? $posts->pending : 0;
	
	if ( $pendingPosts > 0 ) {
		$current_menu = sprintf( '<a href="%1s" style="color:#E66F00;font-weight:bold">%2s</a>',
			esc_url( get_admin_url() ) . 'edit.php?post_status=pending&post_type=post',
			sprintf( 'Pending Post%1s: %2d', ( $pendingPosts > 0 ) ? 's' : '', $pendingPosts	)
		) . " | " . $current_menu;
	}

	restore_current_blog();
	
	return $current_menu;
}

/**
 * Pending Post count on My Sites admin page
 **************************************************************************** */
function new_pending_post_pp($post) {
	// bail if the user can edit_others_posts
	if( current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	$crlf		= "\r\n";
	$curr_user	= wp_get_current_user();
	$recipients	= '';
	$subject	= 'New Pending Post: '.get_bloginfo( 'name' );
	$headers	= 'From: Andy <andy@kingmagsulit.com>'.$crlf."Content-Type: text/html".$crlf;

	// get to: from options
	$o = get_option(PCM_OPTION_GROUP_PP);
	if (isset($o[PCM_PRIMARY_EDITOR_KEY]) && $o[PCM_PRIMARY_EDITOR_KEY] != '') {
		$recipients .= $o[PCM_PRIMARY_EDITOR_KEY] . ',';
	}

	if (isset($o[PCM_SECONDARY_EDITOR_KEY]) && $o[PCM_SECONDARY_EDITOR_KEY] != '') {
		$recipients .= $o[PCM_SECONDARY_EDITOR_KEY];
	}

	// get poster nice name
	$username = ($curr_user instanceof WP_User) ? $curr_user->display_name : '';
	
	/*
		TODO: include nonce'd instant publish link	*/
	// create edit url
	$edit_url = sprintf( '<a href="%1s" style="color:#E66F00;font-weight:bold">%2s</a>',
		esc_url( get_admin_url() ) . 'post.php?post=' . $post->ID . "&action=edit",
		$post->post_title );

	// HTML message body
	$message = sprintf( '<html><body><h1>%1s</h1>%2s<br />Message: <blockquote>%3s</blockquote></body></html>',
		'New Post by &lsquo;'. $username.'&rsquo;',
		'Edit: '.$edit_url,
		$post->post_content
		);

	// send the mail
	if ($recipients != '') {
		wp_mail($recipients, $subject, $message, $headers);
	}
}

/**
 * Settings Admin page
 **************************************************************************** */
function pending_posts_settings_pp() {
	add_options_page(
		'PCM Pending Posts Notifications',		// page title
		'Pending Post Alerts',					// menu title
		'edit_others_posts',					// visible to cap
		__FILE__,								// slug
		'render_pending_posts_settings_pp'		// render callback
	 );
}

// Render admin page
function render_pending_posts_settings_pp() {
	?>
	<div class="wrap">
		<?php screen_icon() ?>
		<h2>PCM Pending Posts Notifications</h2>
		<form action="options.php" method="post">
			<?php settings_fields(PCM_OPTION_GROUP_PP) ?>
			<?php do_settings_sections(__FILE__) ?>
			<input class="button-primary" name="Submit" type="submit" value="Save Changes" />
		</form>
	</div>
	<?php
}

// Register settings
function pending_posts_admin_init_pp() {
	register_setting(PCM_OPTION_GROUP_PP, PCM_OPTION_GROUP_PP, 'pcm_validate_options_pp');
	add_settings_section ('main_section', 'Admin Emails','pending_posts_section_text_pp', __FILE__ );
	add_settings_field(PCM_PRIMARY_EDITOR_KEY, 'Primary Editor Email: ', 'render_primary_editor_pp', __FILE__,'main_section');
	add_settings_field(PCM_SECONDARY_EDITOR_KEY, 'Secondary Editor Email: ', 'render_secondary_editor_pp', __FILE__,'main_section');
}

// Section instructions
function pending_posts_section_text_pp() {
	echo 'Enter a primary and/or secondary administrator email address to recieve email alerts when a post is submitted for review for this domain';
	// echo '<pre>';
	// print_r(get_option(PCM_OPTION_GROUP_PP));
	// echo '</pre>';
}

// Validate / Sanitize settings
function pcm_validate_options_pp($o) {
	$valid = array();
	$valid[PCM_PRIMARY_EDITOR_KEY] = '';
	$valid[PCM_SECONDARY_EDITOR_KEY] = '';

	$o[PCM_PRIMARY_EDITOR_KEY] = trim( $o[PCM_PRIMARY_EDITOR_KEY] );

	if( is_email( $o[PCM_PRIMARY_EDITOR_KEY] ) ) {
		$valid[PCM_PRIMARY_EDITOR_KEY] = $o[PCM_PRIMARY_EDITOR_KEY];
	} else if( $o[PCM_PRIMARY_EDITOR_KEY] != '') {
		add_settings_error(PCM_PRIMARY_EDITOR_KEY, 'myerr', 'Primary editor email should at least look like an email address' );
	}
		
	$o[PCM_SECONDARY_EDITOR_KEY] = trim( $o[PCM_SECONDARY_EDITOR_KEY] );
	if( is_email( $o[PCM_SECONDARY_EDITOR_KEY] ) ) {
		$valid[PCM_SECONDARY_EDITOR_KEY] = $o[PCM_SECONDARY_EDITOR_KEY];
	} else if( $o[PCM_SECONDARY_EDITOR_KEY] != '' ) {
		add_settings_error(PCM_SECONDARY_EDITOR_KEY, 'myerr', 'Secondary editor email should at least look like an email address' );
	}

	return $valid;
}

// Render primary editor email input element
function render_primary_editor_pp() {
	$options = get_option(PCM_OPTION_GROUP_PP);
	printf('<input id="%1$s" name="%2$s[%1$s]" size="40" type="text" value="%3$s" />',
		PCM_PRIMARY_EDITOR_KEY,
		PCM_OPTION_GROUP_PP,
		$options[PCM_PRIMARY_EDITOR_KEY]
	);
}

// Render secondary editor email input element
function render_secondary_editor_pp() {
	$options = get_option(PCM_OPTION_GROUP_PP);
	printf('<input id="%1$s" name="%2$s[%1$s]" size="40" type="text" value="%3$s" />',
		PCM_SECONDARY_EDITOR_KEY,
		PCM_OPTION_GROUP_PP,
		$options[PCM_SECONDARY_EDITOR_KEY]
	);
}
?>
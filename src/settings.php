<?php

require_once WPCF7_PLUGIN_DIR . '/includes/functions.php';
require_once WPCF7_PLUGIN_DIR . '/includes/l10n.php';
require_once WPCF7_PLUGIN_DIR . '/includes/formatting.php';
require_once WPCF7_PLUGIN_DIR . '/includes/form-tags-manager.php';
require_once WPCF7_PLUGIN_DIR . '/includes/shortcodes.php';
require_once WPCF7_PLUGIN_DIR . '/includes/capabilities.php';
require_once WPCF7_PLUGIN_DIR . '/includes/contact-form-template.php';
require_once WPCF7_PLUGIN_DIR . '/includes/contact-form-functions.php';
require_once WPCF7_PLUGIN_DIR . '/includes/mail.php';
require_once WPCF7_PLUGIN_DIR . '/includes/special-mail-tags.php';
require_once WPCF7_PLUGIN_DIR . '/includes/upgrade.php';
require_once WPCF7_PLUGIN_DIR . '/includes/rest-api.php';

if ( is_admin() ) {
	require_once WPCF7_PLUGIN_DIR . '/admin/admin.php';
} else {
	require_once WPCF7_PLUGIN_DIR . '/includes/controller.php';
}


add_action( 'plugins_loaded', 'wpcf7', 10, 0 );

function wpcf7() {
	wpcf7_load_textdomain();
	WPCF7::load_modules();

	/* Shortcodes */
	add_shortcode( 'contact-form-7', 'wpcf7_contact_form_tag_func' );
	add_shortcode( 'contact-form', 'wpcf7_contact_form_tag_func' );
}

add_action( 'init', 'wpcf7_init', 10, 0 );

function wpcf7_init() {
	wpcf7_get_request_uri();
	wpcf7_register_post_types();

	do_action( 'wpcf7_init' );
}

add_action( 'admin_init', 'wpcf7_upgrade', 10, 0 );

function wpcf7_upgrade() {
	$old_ver = WPCF7::get_option( 'version', '0' );
	$new_ver = WPCF7_VERSION;

	if ( $old_ver == $new_ver ) {
		return;
	}

	do_action( 'wpcf7_upgrade', $new_ver, $old_ver );

	WPCF7::update_option( 'version', $new_ver );
}

/* Install and default settings */

add_action( 'activate_' . WPCF7_PLUGIN_BASENAME, 'wpcf7_install', 10, 0 );

function wpcf7_install() {
	if ( $opt = get_option( 'wpcf7' ) ) {
		return;
	}

	wpcf7_load_textdomain();
	wpcf7_register_post_types();
	wpcf7_upgrade();

	if ( get_posts( array( 'post_type' => 'wpcf7_contact_form' ) ) ) {
		return;
	}

	$contact_form = WPCF7_ContactForm::get_template(
		array(
			'title' =>
				/* translators: title of your first contact form. %d: number fixed to '1' */
				sprintf( __( 'Contact form %d', 'contact-form-7' ), 1 ),
		)
	);

	$contact_form->save();

	WPCF7::update_option( 'bulk_validate',
		array(
			'timestamp' => current_time( 'timestamp' ),
			'version' => WPCF7_VERSION,
			'count_valid' => 1,
			'count_invalid' => 0,
		)
	);
}

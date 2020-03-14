<?php
/**
 * All the functions and classes in this file are deprecated.
 * You shouldn't use them. The functions and classes will be
 * removed in a later version.
 */

function wpcf7_add_shortcode( $tag, $func, $has_name = false ) {
	wpcf7_deprecated_function( __FUNCTION__, '4.6', 'wpcf7_add_form_tag' );

	return wpcf7_add_form_tag( $tag, $func, $has_name );
}

function wpcf7_remove_shortcode( $tag ) {
	wpcf7_deprecated_function( __FUNCTION__, '4.6', 'wpcf7_remove_form_tag' );

	return wpcf7_remove_form_tag( $tag );
}

function wpcf7_do_shortcode( $content ) {
	wpcf7_deprecated_function( __FUNCTION__, '4.6',
		'wpcf7_replace_all_form_tags' );

	return wpcf7_replace_all_form_tags( $content );
}

function wpcf7_scan_shortcode( $cond = null ) {
	wpcf7_deprecated_function( __FUNCTION__, '4.6', 'wpcf7_scan_form_tags' );

	return wpcf7_scan_form_tags( $cond );
}
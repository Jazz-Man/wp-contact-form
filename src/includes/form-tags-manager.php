<?php

function wpcf7_add_form_tag( $tag, $func, $features = '' ) {
	$manager = WPCF7_FormTagsManager::get_instance();

	return $manager->add( $tag, $func, $features );
}

function wpcf7_remove_form_tag( $tag ) {
	$manager = WPCF7_FormTagsManager::get_instance();

	return $manager->remove( $tag );
}

function wpcf7_replace_all_form_tags( $content ) {
	$manager = WPCF7_FormTagsManager::get_instance();

	return $manager->replace_all( $content );
}

function wpcf7_scan_form_tags( $cond = null ) {
	$contact_form = WPCF7_ContactForm::get_current();

	if ( $contact_form ) {
		return $contact_form->scan_form_tags( $cond );
	}

	return array();
}

function wpcf7_form_tag_supports( $tag, $feature ) {
	$manager = WPCF7_FormTagsManager::get_instance();

	return $manager->tag_type_supports( $tag, $feature );
}

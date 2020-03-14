<?php


class WPCF7_Editor {

    private $contact_form;
    private $panels = array();

    public function __construct( WPCF7_ContactForm $contact_form ) {
        $this->contact_form = $contact_form;
    }

    public function add_panel( $id, $title, $callback ) {
        if ( wpcf7_is_name( $id ) ) {
            $this->panels[$id] = array(
                'title' => $title,
                'callback' => $callback,
            );
        }
    }

    public function display() {
        if ( empty( $this->panels ) ) {
            return;
        }

        echo '<ul id="contact-form-editor-tabs">';

        foreach ( $this->panels as $id => $panel ) {
            echo sprintf( '<li id="%1$s-tab"><a href="#%1$s">%2$s</a></li>',
                esc_attr( $id ), esc_html( $panel['title'] ) );
        }

        echo '</ul>';

        foreach ( $this->panels as $id => $panel ) {
            echo sprintf( '<div class="contact-form-editor-panel" id="%1$s">',
                esc_attr( $id ) );

            if ( is_callable( $panel['callback'] ) ) {
                $this->notice( $id, $panel );
                call_user_func( $panel['callback'], $this->contact_form );
            }

            echo '</div>';
        }
    }

    public function notice( $id, $panel ) {
        echo '<div class="config-error"></div>';
    }
}
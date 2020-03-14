<?php

class WPCF7_Shortcode extends WPCF7_FormTag
{
    public function __construct($tag)
    {
        wpcf7_deprecated_function('WPCF7_Shortcode', '4.6', 'WPCF7_FormTag');

        parent::__construct($tag);
    }
}

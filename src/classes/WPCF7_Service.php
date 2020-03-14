<?php

abstract class WPCF7_Service
{
    abstract public function get_title();

    abstract public function is_active();

    public function get_categories()
    {
        return [];
    }

    public function icon()
    {
        return '';
    }

    public function link()
    {
        return '';
    }

    public function load($action = '')
    {
    }

    public function display($action = '')
    {
    }

    public function admin_notice($message = '')
    {
    }
}

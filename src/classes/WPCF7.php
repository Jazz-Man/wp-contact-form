<?php

/**
 * Class WPCF7.
 */
class WPCF7
{
    public static function load_modules()
    {
        self::load_module('acceptance');
        self::load_module('akismet');
        self::load_module('checkbox');
        self::load_module('constant-contact');
        self::load_module('count');
        self::load_module('date');
        self::load_module('file');
        self::load_module('flamingo');
        self::load_module('hidden');
        self::load_module('listo');
        self::load_module('number');
        self::load_module('quiz');
        self::load_module('really-simple-captcha');
        self::load_module('recaptcha');
        self::load_module('response');
        self::load_module('select');
        self::load_module('submit');
        self::load_module('text');
        self::load_module('textarea');
    }

    /**
     * @param string $name
     * @param bool $default
     *
     * @return bool
     */
    public static function get_option($name, $default = false)
    {
        $option = get_option('wpcf7');

        if (false === $option) {
            return $default;
        }

        return $option[$name] ?? $default;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public static function update_option($name, $value)
    {
        $option = get_option('wpcf7');
        $option = (false === $option) ? [] : (array) $option;
        $option = array_merge($option, [$name => $value]);
        update_option('wpcf7', $option);
    }

    /**
     * @return bool
     */
    protected static function load_module(string $mod)
    {
        $dir = WPCF7_PLUGIN_MODULES_DIR;

        if (empty($dir) || !is_dir($dir)) {
            return false;
        }

        $file = path_join($dir, $mod.'.php');

        if (file_exists($file)) {
            include_once $file;
        }
    }
}

<?php

/**
 * Class WPCF7_FormTag.
 */
class WPCF7_FormTag implements ArrayAccess
{
    public $type;
    public $basetype;
    public $name = '';
    public $options = [];
    public $raw_values = [];
    public $values = [];
    public $pipes;
    public $labels = [];
    public $attr = '';
    public $content = '';

    /**
     * WPCF7_FormTag constructor.
     *
     * @param array $tag
     */
    public function __construct($tag = [])
    {
        if (is_array($tag) || $tag instanceof self) {
            foreach ($tag as $key => $value) {
                if (property_exists(__CLASS__, $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function is_required()
    {
        return '*' == substr($this->type, -1);
    }

    /**
     * @param $opt
     *
     * @return bool
     */
    public function has_option($opt)
    {
        $pattern = sprintf('/^%s(:.+)?$/i', preg_quote($opt, '/'));

        return (bool) preg_grep($pattern, $this->options);
    }

    /**
     * @param $opt
     * @param string $pattern
     * @param bool $single
     *
     * @return array|bool|false|string
     */
    public function get_option($opt, $pattern = '', $single = false)
    {
        $preset_patterns = [
            'date' => '([0-9]{4}-[0-9]{2}-[0-9]{2}|today(.*))',
            'int' => '[0-9]+',
            'signed_int' => '-?[0-9]+',
            'class' => '[-0-9a-zA-Z_]+',
            'id' => '[-0-9a-zA-Z_]+',
        ];

        if (isset($preset_patterns[$pattern])) {
            $pattern = $preset_patterns[$pattern];
        }

        if ('' == $pattern) {
            $pattern = '.+';
        }

        $pattern = sprintf('/^%s:%s$/i', preg_quote($opt, '/'), $pattern);

        if ($single) {
            $matches = $this->get_first_match_option($pattern);

            if (!$matches) {
                return false;
            }

            return substr($matches[0], strlen($opt) + 1);
        }
        $matches_a = $this->get_all_match_options($pattern);

        if (!$matches_a) {
            return false;
        }

        $results = [];

        foreach ($matches_a as $matches) {
            $results[] = substr($matches[0], strlen($opt) + 1);
        }

        return $results;
    }

    /**
     * @return array|bool|false|string
     */
    public function get_id_option()
    {
        return $this->get_option('id', 'id', true);
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function get_class_option($default = '')
    {
        if (is_string($default)) {
            $default = explode(' ', $default);
        }

        $options = array_merge((array) $default, (array) $this->get_option('class', 'class'));

        $options = array_filter(array_unique($options));

        return implode(' ', $options);
    }

    /**
     * @param string $default
     *
     * @return array|bool|false|mixed|string
     */
    public function get_size_option($default = '')
    {
        $option = $this->get_option('size', 'int', true);

        if ($option) {
            return $option;
        }

        $matches_a = $this->get_all_match_options('%^([0-9]*)/[0-9]*$%');

        foreach ((array) $matches_a as $matches) {
            if (isset($matches[1]) and '' !== $matches[1]) {
                return $matches[1];
            }
        }

        return $default;
    }

    /**
     * @param string $default
     *
     * @return array|bool|false|mixed|string
     */
    public function get_maxlength_option($default = '')
    {
        $option = $this->get_option('maxlength', 'int', true);

        if ($option) {
            return $option;
        }

        $matches_a = $this->get_all_match_options('%^(?:[0-9]*x?[0-9]*)?/([0-9]+)$%');

        foreach ((array) $matches_a as $matches) {
            if (isset($matches[1]) && '' !== $matches[1]) {
                return $matches[1];
            }
        }

        return $default;
    }

    /**
     * @param string $default
     *
     * @return array|bool|false|string
     */
    public function get_minlength_option($default = '')
    {
        $option = $this->get_option('minlength', 'int', true);

        if ($option) {
            return $option;
        }

        return $default;
    }

    /**
     * @param string $default
     *
     * @return array|bool|false|mixed|string
     */
    public function get_cols_option($default = '')
    {
        $option = $this->get_option('cols', 'int', true);

        if ($option) {
            return $option;
        }

        $matches_a = $this->get_all_match_options('%^([0-9]*)x([0-9]*)(?:/[0-9]+)?$%');

        foreach ((array) $matches_a as $matches) {
            if (isset($matches[1]) && '' !== $matches[1]) {
                return $matches[1];
            }
        }

        return $default;
    }

    /**
     * @param string $default
     *
     * @return array|bool|false|mixed|string
     */
    public function get_rows_option($default = '')
    {
        $option = $this->get_option('rows', 'int', true);

        if ($option) {
            return $option;
        }

        $matches_a = $this->get_all_match_options('%^([0-9]*)x([0-9]*)(?:/[0-9]+)?$%');

        foreach ((array) $matches_a as $matches) {
            if (isset($matches[2]) and '' !== $matches[2]) {
                return $matches[2];
            }
        }

        return $default;
    }

    /**
     * @param $opt
     *
     * @return array|bool|false|string
     */
    public function get_date_option($opt)
    {
        $option = $this->get_option($opt, 'date', true);

        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $option)) {
            return $option;
        }

        if (preg_match('/^today(?:([+-][0-9]+)([a-z]*))?/', $option, $matches)) {
            $number = isset($matches[1]) ? (int) $matches[1] : 0;
            $unit = isset($matches[2]) ? $matches[2] : '';

            if (!preg_match('/^(day|month|year|week)s?$/', $unit)) {
                $unit = 'days';
            }

            return gmdate('Y-m-d', strtotime(sprintf('today %1$s %2$s', $number, $unit)));
        }

        return false;
    }

    /**
     * @param string $default
     * @param string $args
     *
     * @return array|mixed|string
     */
    public function get_default_option($default = '', $args = '')
    {
        $args = wp_parse_args($args, [
            'multiple' => false,
            'shifted' => false,
        ]);

        $options = (array) $this->get_option('default');
        $values = [];

        if (empty($options)) {
            return $args['multiple'] ? $values : $default;
        }

        foreach ($options as $opt) {
            $opt = sanitize_key($opt);

            if ('user_' == substr($opt, 0, 5) and is_user_logged_in()) {
                $primary_props = ['user_login', 'user_email', 'user_url'];
                $opt = in_array($opt, $primary_props) ? $opt : substr($opt, 5);

                $user = wp_get_current_user();
                $user_prop = $user->get($opt);

                if (!empty($user_prop)) {
                    if ($args['multiple']) {
                        $values[] = $user_prop;
                    } else {
                        return $user_prop;
                    }
                }
            } elseif ('post_meta' == $opt and in_the_loop()) {
                if ($args['multiple']) {
                    $values = array_merge($values, get_post_meta(get_the_ID(), $this->name));
                } else {
                    $val = (string) get_post_meta(get_the_ID(), $this->name, true);

                    if (strlen($val)) {
                        return $val;
                    }
                }
            } elseif ('get' == $opt and isset($_GET[$this->name])) {
                $vals = (array) $_GET[$this->name];
                $vals = array_map('wpcf7_sanitize_query_var', $vals);

                if ($args['multiple']) {
                    $values = array_merge($values, $vals);
                } else {
                    $val = isset($vals[0]) ? (string) $vals[0] : '';

                    if (strlen($val)) {
                        return $val;
                    }
                }
            } elseif ('post' == $opt and isset($_POST[$this->name])) {
                $vals = (array) $_POST[$this->name];
                $vals = array_map('wpcf7_sanitize_query_var', $vals);

                if ($args['multiple']) {
                    $values = array_merge($values, $vals);
                } else {
                    $val = isset($vals[0]) ? (string) $vals[0] : '';

                    if (strlen($val)) {
                        return $val;
                    }
                }
            } elseif ('shortcode_attr' == $opt) {
                if ($contact_form = WPCF7_ContactForm::get_current()) {
                    $val = $contact_form->shortcode_attr($this->name);

                    if (strlen($val)) {
                        if ($args['multiple']) {
                            $values[] = $val;
                        } else {
                            return $val;
                        }
                    }
                }
            } elseif (preg_match('/^[0-9_]+$/', $opt)) {
                $nums = explode('_', $opt);

                foreach ($nums as $num) {
                    $num = absint($num);
                    $num = $args['shifted'] ? $num : $num - 1;

                    if (isset($this->values[$num])) {
                        if ($args['multiple']) {
                            $values[] = $this->values[$num];
                        } else {
                            return $this->values[$num];
                        }
                    }
                }
            }
        }

        if ($args['multiple']) {
            $values = array_unique($values);

            return $values;
        }

        return $default;
    }

    /**
     * @param string $args
     *
     * @return mixed|void
     */
    public function get_data_option($args = '')
    {
        $options = (array) $this->get_option('data');

        return apply_filters('wpcf7_form_tag_data_option', null, $options, $args);
    }

    /**
     * @param int $default
     *
     * @return float|int
     */
    public function get_limit_option($default = 1048576)
    { // 1048576 = 1 MB
        $pattern = '/^limit:([1-9][0-9]*)([kKmM]?[bB])?$/';

        $matches = $this->get_first_match_option($pattern);

        if ($matches) {
            $size = (int) $matches[1];

            if (!empty($matches[2])) {
                $kbmb = strtolower($matches[2]);

                if ('kb' == $kbmb) {
                    $size *= 1024;
                } elseif ('mb' == $kbmb) {
                    $size *= 1024 * 1024;
                }
            }

            return $size;
        }

        return (int) $default;
    }

    /**
     * @param $pattern
     *
     * @return bool
     */
    public function get_first_match_option($pattern)
    {
        foreach ((array) $this->options as $option) {
            if (preg_match($pattern, $option, $matches)) {
                return $matches;
            }
        }

        return false;
    }

    /**
     * @param $pattern
     *
     * @return array
     */
    public function get_all_match_options($pattern)
    {
        $result = [];

        foreach ((array) $this->options as $option) {
            if (preg_match($pattern, $option, $matches)) {
                $result[] = $matches;
            }
        }

        return $result;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (property_exists(__CLASS__, $offset)) {
            $this->{$offset} = $value;
        }
    }

    /**
     * @param mixed $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        if (property_exists(__CLASS__, $offset)) {
            return $this->{$offset};
        }

        return null;
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return property_exists(__CLASS__, $offset);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
    }
}
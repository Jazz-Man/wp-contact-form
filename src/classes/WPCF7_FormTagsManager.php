<?php

class WPCF7_FormTagsManager
{
    private static $instance;

    private $tag_types = [];
    private $scanned_tags; // Tags scanned at the last time of scan()

    private function __construct()
    {
    }

    public static function get_instance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_scanned_tags()
    {
        return $this->scanned_tags;
    }

    public function add($tag, $func, $features = '')
    {
        if (!is_callable($func)) {
            return;
        }

        if (true === $features) { // for back-compat
            $features = ['name-attr' => true];
        }

        $features = wp_parse_args($features, []);

        $tags = array_filter(array_unique((array) $tag));

        foreach ($tags as $tag) {
            $tag = $this->sanitize_tag_type($tag);

            if (!$this->tag_type_exists($tag)) {
                $this->tag_types[$tag] = [
                    'function' => $func,
                    'features' => $features,
                ];
            }
        }
    }

    public function tag_type_exists($tag)
    {
        return isset($this->tag_types[$tag]);
    }

    public function tag_type_supports($tag, $feature)
    {
        $feature = array_filter((array) $feature);

        if (isset($this->tag_types[$tag]['features'])) {
            return (bool) array_intersect(
                array_keys(array_filter($this->tag_types[$tag]['features'])),
                $feature);
        }

        return false;
    }

    public function collect_tag_types($feature = null, $invert = false)
    {
        $tag_types = array_keys($this->tag_types);

        if (empty($feature)) {
            return $tag_types;
        }

        $output = [];

        foreach ($tag_types as $tag) {
            if (!$invert && $this->tag_type_supports($tag, $feature)
                 || $invert && !$this->tag_type_supports($tag, $feature)) {
                $output[] = $tag;
            }
        }

        return $output;
    }

    public function remove($tag)
    {
        unset($this->tag_types[$tag]);
    }

    public function normalize($content)
    {
        if (empty($this->tag_types)) {
            return $content;
        }

        return preg_replace_callback(
            '/'.$this->tag_regex().'/s',
            [$this, 'normalize_callback'],
            $content);
    }

    public function replace_all($content)
    {
        return $this->scan($content, true);
    }

    public function scan($content, $replace = false)
    {
        $this->scanned_tags = [];

        if (empty($this->tag_types)) {
            if ($replace) {
                return $content;
            }

            return $this->scanned_tags;
        }

        if ($replace) {
            return preg_replace_callback(
                '/'.$this->tag_regex().'/s',
                [$this, 'replace_callback'],
                $content);
        }
        preg_replace_callback(
                '/'.$this->tag_regex().'/s',
                [$this, 'scan_callback'],
                $content);

        return $this->scanned_tags;
    }

    public function filter($input, $cond)
    {
        if (is_array($input)) {
            $tags = $input;
        } elseif (is_string($input)) {
            $tags = $this->scan($input);
        } else {
            $tags = $this->scanned_tags;
        }

        if (empty($tags)) {
            return [];
        }

        $cond = wp_parse_args($cond, [
            'type' => [],
            'name' => [],
            'feature' => '',
        ]);

        $type = array_filter((array) $cond['type']);
        $name = array_filter((array) $cond['name']);
        $feature = is_string($cond['feature']) ? trim($cond['feature']) : '';

        if (0 === strpos($feature, '!')) {
            $feature_negative = true;
            $feature = trim(substr($feature, 1));
        } else {
            $feature_negative = false;
        }

        $output = [];

        foreach ($tags as $tag) {
            $tag = new WPCF7_FormTag($tag);

            if ($type and !in_array($tag->type, $type, true)) {
                continue;
            }

            if ($name and !in_array($tag->name, $name, true)) {
                continue;
            }

            if ($feature) {
                if (!$this->tag_type_supports($tag->type, $feature)
                     and !$feature_negative) {
                    continue;
                }
                if ($this->tag_type_supports($tag->type, $feature)
                           and $feature_negative) {
                    continue;
                }
            }

            $output[] = $tag;
        }

        return $output;
    }

    private function sanitize_tag_type($tag)
    {
        $tag = preg_replace('/[^a-zA-Z0-9_*]+/', '_', $tag);
        $tag = rtrim($tag, '_');

        return strtolower($tag);
    }

    private function normalize_callback($m)
    {
        // allow [[foo]] syntax for escaping a tag
        if ('[' == $m[1] and ']' == $m[6]) {
            return $m[0];
        }

        $tag = $m[2];

        $attr = trim(preg_replace('/[\r\n\t ]+/', ' ', $m[3]));
        $attr = strtr($attr, ['<' => '&lt;', '>' => '&gt;']);

        $content = trim($m[5]);
        $content = str_replace("\n", '<WPPreserveNewline />', $content);

        return $m[1].'['.$tag
                  .($attr ? ' '.$attr : '')
                  .($m[4] ? ' '.$m[4] : '')
                  .']'
                  .($content ? $content.'[/'.$tag.']' : '')
                  .$m[6];
    }

    private function tag_regex()
    {
        $tagnames = array_keys($this->tag_types);
        $tagregexp = implode('|', array_map('preg_quote', $tagnames));

        return '(\[?)'
               .'\[('.$tagregexp.')(?:[\r\n\t ](.*?))?(?:[\r\n\t ](\/))?\]'
               .'(?:([^[]*?)\[\/\2\])?'
               .'(\]?)';
    }

    private function replace_callback($m)
    {
        return $this->scan_callback($m, true);
    }

    private function scan_callback($m, $replace = false)
    {
        // allow [[foo]] syntax for escaping a tag
        if ('[' == $m[1] and ']' == $m[6]) {
            return substr($m[0], 1, -1);
        }

        $tag = $m[2];
        $attr = $this->parse_atts($m[3]);

        $scanned_tag = [
            'type' => $tag,
            'basetype' => trim($tag, '*'),
            'name' => '',
            'options' => [],
            'raw_values' => [],
            'values' => [],
            'pipes' => null,
            'labels' => [],
            'attr' => '',
            'content' => '',
        ];

        if (is_array($attr)) {
            if (is_array($attr['options'])) {
                if ($this->tag_type_supports($tag, 'name-attr')
                     and !empty($attr['options'])) {
                    $scanned_tag['name'] = array_shift($attr['options']);

                    if (!wpcf7_is_name($scanned_tag['name'])) {
                        return $m[0]; // Invalid name is used. Ignore this tag.
                    }
                }

                $scanned_tag['options'] = (array) $attr['options'];
            }

            $scanned_tag['raw_values'] = (array) $attr['values'];

            if (WPCF7_USE_PIPE) {
                $pipes = new WPCF7_Pipes($scanned_tag['raw_values']);
                $scanned_tag['values'] = $pipes->collect_befores();
                $scanned_tag['pipes'] = $pipes;
            } else {
                $scanned_tag['values'] = $scanned_tag['raw_values'];
            }

            $scanned_tag['labels'] = $scanned_tag['values'];
        } else {
            $scanned_tag['attr'] = $attr;
        }

        $scanned_tag['values'] = array_map('trim', $scanned_tag['values']);
        $scanned_tag['labels'] = array_map('trim', $scanned_tag['labels']);

        $content = trim($m[5]);
        $content = preg_replace("/<br[\r\n\t ]*\\/?>$/m", '', $content);
        $scanned_tag['content'] = $content;

        $scanned_tag = apply_filters('wpcf7_form_tag', $scanned_tag, $replace);

        $scanned_tag = new WPCF7_FormTag($scanned_tag);

        $this->scanned_tags[] = $scanned_tag;

        if ($replace) {
            $func = $this->tag_types[$tag]['function'];

            return $m[1].call_user_func($func, $scanned_tag).$m[6];
        }

        return $m[0];
    }

    private function parse_atts($text)
    {
        $atts = ['options' => [], 'values' => []];
        $text = preg_replace('/[\\x{00a0}\\x{200b}]+/u', ' ', $text);
        $text = stripcslashes(trim($text));

        $pattern = '%^([-+*=0-9a-zA-Z:.!?#$&@_/|\%\r\n\t ]*?)((?:[\r\n\t ]*"[^"]*"|[\r\n\t ]*\'[^\']*\')*)$%';

        if (preg_match($pattern, $text, $match)) {
            if (!empty($match[1])) {
                $atts['options'] = preg_split('/[\r\n\t ]+/', trim($match[1]));
            }

            if (!empty($match[2])) {
                preg_match_all('/"[^"]*"|\'[^\']*\'/', $match[2], $matched_values);
                $atts['values'] = wpcf7_strip_quote_deep($matched_values[0]);
            }
        } else {
            $atts = $text;
        }

        return $atts;
    }
}

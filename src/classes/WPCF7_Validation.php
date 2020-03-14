<?php

class WPCF7_Validation implements ArrayAccess
{
    private $invalid_fields = [];
    private $container = [];

    public function __construct()
    {
        $this->container = [
            'valid' => true,
            'reason' => [],
            'idref' => [],
        ];
    }

    public function invalidate($context, $message)
    {
        if ($context instanceof WPCF7_FormTag) {
            $tag = $context;
        } elseif (is_array($context)) {
            $tag = new WPCF7_FormTag($context);
        } elseif (is_string($context)) {
            $tags = wpcf7_scan_form_tags(['name' => trim($context)]);
            $tag = $tags ? new WPCF7_FormTag($tags[0]) : null;
        }

        $name = !empty($tag) ? $tag->name : null;

        if (empty($name)
        or !wpcf7_is_name($name)) {
            return;
        }

        if ($this->is_valid($name)) {
            $id = $tag->get_id_option();

            if (empty($id)
            or !wpcf7_is_name($id)) {
                $id = null;
            }

            $this->invalid_fields[$name] = [
                'reason' => (string) $message,
                'idref' => $id,
            ];
        }
    }

    public function is_valid($name = null)
    {
        if (!empty($name)) {
            return !isset($this->invalid_fields[$name]);
        }

        return empty($this->invalid_fields);
    }

    public function get_invalid_fields()
    {
        return $this->invalid_fields;
    }

    public function offsetSet($offset, $value)
    {
        if (isset($this->container[$offset])) {
            $this->container[$offset] = $value;
        }

        if ('reason' == $offset
        and is_array($value)) {
            foreach ($value as $k => $v) {
                $this->invalidate($k, $v);
            }
        }
    }

    public function offsetGet($offset)
    {
        if (isset($this->container[$offset])) {
            return $this->container[$offset];
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset)
    {
    }
}

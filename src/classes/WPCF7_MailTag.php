<?php

class WPCF7_MailTag
{
    private $tag;
    private $tagname = '';
    private $name = '';
    private $options = [];
    private $values = [];
    private $form_tag;

    public function __construct($tag, $tagname, $values)
    {
        $this->tag = $tag;
        $this->name = $this->tagname = $tagname;

        $this->options = [
            'do_not_heat' => false,
            'format' => '',
        ];

        if (!empty($values)) {
            preg_match_all('/"[^"]*"|\'[^\']*\'/', $values, $matches);
            $this->values = wpcf7_strip_quote_deep($matches[0]);
        }

        if (preg_match('/^_raw_(.+)$/', $tagname, $matches)) {
            $this->name = trim($matches[1]);
            $this->options['do_not_heat'] = true;
        }

        if (preg_match('/^_format_(.+)$/', $tagname, $matches)) {
            $this->name = trim($matches[1]);
            $this->options['format'] = $this->values[0];
        }
    }

    public function tag_name()
    {
        return $this->tagname;
    }

    public function field_name()
    {
        return $this->name;
    }

    public function get_option($option)
    {
        return $this->options[$option];
    }

    public function values()
    {
        return $this->values;
    }

    public function corresponding_form_tag()
    {
        if ($this->form_tag instanceof WPCF7_FormTag) {
            return $this->form_tag;
        }

        if ($submission = WPCF7_Submission::get_instance()) {
            $contact_form = $submission->get_contact_form();
            $tags = $contact_form->scan_form_tags([
                'name' => $this->name,
                'feature' => '! zero-controls-container',
            ]);

            if ($tags) {
                $this->form_tag = $tags[0];
            }
        }

        return $this->form_tag;
    }
}

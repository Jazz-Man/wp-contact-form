<?php

class WPCF7_ConfigValidator
{
    public const error = 100;
    public const error_maybe_empty = 101;
    public const error_invalid_mailbox_syntax = 102;
    public const error_email_not_in_site_domain = 103;
    public const error_html_in_message = 104;
    public const error_multiple_controls_in_label = 105;
    public const error_file_not_found = 106;
    public const error_unavailable_names = 107;
    public const error_invalid_mail_header = 108;
    public const error_deprecated_settings = 109;
    public const error_file_not_in_content_dir = 110;
    public const error_unavailable_html_elements = 111;
    public const error_attachments_overweight = 112;

    private $contact_form;
    private $errors = [];

    public function __construct(WPCF7_ContactForm $contact_form)
    {
        $this->contact_form = $contact_form;
    }

    public static function get_doc_link($error_code = '')
    {
        $url = __('https://contactform7.com/configuration-errors/',
            'contact-form-7');

        if ('' !== $error_code) {
            $error_code = strtr($error_code, '_', '-');

            $url = sprintf('%s/%s', untrailingslashit($url), $error_code);
        }

        return esc_url($url);
    }

    public function contact_form()
    {
        return $this->contact_form;
    }

    public function is_valid()
    {
        return !$this->count_errors();
    }

    public function count_errors($args = '')
    {
        $args = wp_parse_args($args, [
            'section' => '',
            'code' => '',
        ]);

        $count = 0;

        foreach ($this->errors as $key => $errors) {
            if (preg_match('/^mail_[0-9]+\.(.*)$/', $key, $matches)) {
                $key = sprintf('mail.%s', $matches[1]);
            }

            if ($args['section']
            and $key != $args['section']
            and preg_replace('/\..*$/', '', $key, 1) != $args['section']) {
                continue;
            }

            foreach ($errors as $error) {
                if (empty($error)) {
                    continue;
                }

                if ($args['code'] and $error['code'] != $args['code']) {
                    continue;
                }

                ++$count;
            }
        }

        return $count;
    }

    public function collect_error_messages()
    {
        $error_messages = [];

        foreach ($this->errors as $section => $errors) {
            $error_messages[$section] = [];

            foreach ($errors as $error) {
                if (empty($error['args']['message'])) {
                    $message = $this->get_default_message($error['code']);
                } elseif (empty($error['args']['params'])) {
                    $message = $error['args']['message'];
                } else {
                    $message = $this->build_message(
                        $error['args']['message'],
                        $error['args']['params']);
                }

                $link = '';

                if (!empty($error['args']['link'])) {
                    $link = $error['args']['link'];
                }

                $error_messages[$section][] = [
                    'message' => $message,
                    'link' => esc_url($link),
                ];
            }
        }

        return $error_messages;
    }

    public function build_message($message, $params = '')
    {
        $params = wp_parse_args($params, []);

        foreach ($params as $key => $val) {
            if (!preg_match('/^[0-9A-Za-z_]+$/', $key)) { // invalid key
                continue;
            }

            $placeholder = '%'.$key.'%';

            if (false !== stripos($message, $placeholder)) {
                $message = str_ireplace($placeholder, $val, $message);
            }
        }

        return $message;
    }

    public function get_default_message($code)
    {
        switch ($code) {
            case self::error_maybe_empty:
                return __('There is a possible empty field.', 'contact-form-7');
            case self::error_invalid_mailbox_syntax:
                return __('Invalid mailbox syntax is used.', 'contact-form-7');
            case self::error_email_not_in_site_domain:
                return __('Sender email address does not belong to the site domain.', 'contact-form-7');
            case self::error_html_in_message:
                return __('HTML tags are used in a message.', 'contact-form-7');
            case self::error_multiple_controls_in_label:
                return __('Multiple form controls are in a single label element.', 'contact-form-7');
            case self::error_invalid_mail_header:
                return __('There are invalid mail header fields.', 'contact-form-7');
            case self::error_deprecated_settings:
                return __('Deprecated settings are used.', 'contact-form-7');
            default:
                return '';
        }
    }

    public function add_error($section, $code, $args = '')
    {
        $args = wp_parse_args($args, [
            'message' => '',
            'params' => [],
        ]);

        if (!isset($this->errors[$section])) {
            $this->errors[$section] = [];
        }

        $this->errors[$section][] = ['code' => $code, 'args' => $args];

        return true;
    }

    public function remove_error($section, $code)
    {
        if (empty($this->errors[$section])) {
            return;
        }

        foreach ((array) $this->errors[$section] as $key => $error) {
            if (isset($error['code'])
            and $error['code'] == $code) {
                unset($this->errors[$section][$key]);
            }
        }

        if (empty($this->errors[$section])) {
            unset($this->errors[$section]);
        }
    }

    public function validate()
    {
        $this->errors = [];

        $this->validate_form();
        $this->validate_mail('mail');
        $this->validate_mail('mail_2');
        $this->validate_messages();
        $this->validate_additional_settings();

        do_action('wpcf7_config_validator_validate', $this);

        return $this->is_valid();
    }

    public function save()
    {
        if ($this->contact_form->initial()) {
            return;
        }

        delete_post_meta($this->contact_form->id(), '_config_errors');

        if ($this->errors) {
            update_post_meta($this->contact_form->id(), '_config_errors',
                $this->errors);
        }
    }

    public function restore()
    {
        $config_errors = get_post_meta(
            $this->contact_form->id(), '_config_errors', true);

        foreach ((array) $config_errors as $section => $errors) {
            if (empty($errors)) {
                continue;
            }

            if (!is_array($errors)) { // for back-compat
                $code = $errors;
                $this->add_error($section, $code);
            } else {
                foreach ((array) $errors as $error) {
                    if (!empty($error['code'])) {
                        $code = $error['code'];
                        $args = isset($error['args']) ? $error['args'] : '';
                        $this->add_error($section, $code, $args);
                    }
                }
            }
        }
    }

    public function replace_mail_tags_with_minimum_input($matches)
    {
        // allow [[foo]] syntax for escaping a tag
        if ('[' == $matches[1] && ']' == $matches[4]) {
            return substr($matches[0], 1, -1);
        }

        $tag = $matches[0];
        $tagname = $matches[2];
        $values = $matches[3];

        $mail_tag = new WPCF7_MailTag($tag, $tagname, $values);
        $field_name = $mail_tag->field_name();

        $example_email = 'example@example.com';
        $example_text = 'example';
        $example_blank = '';

        $form_tags = $this->contact_form->scan_form_tags(
            ['name' => $field_name]);

        if ($form_tags) {
            $form_tag = new WPCF7_FormTag($form_tags[0]);

            $is_required = ($form_tag->is_required() || 'radio' == $form_tag->type);

            if (!$is_required) {
                return $example_blank;
            }

            if (wpcf7_form_tag_supports($form_tag->type, 'selectable-values')) {
                if ($form_tag->pipes instanceof WPCF7_Pipes) {
                    if ($mail_tag->get_option('do_not_heat')) {
                        $before_pipes = $form_tag->pipes->collect_befores();
                        $last_item = array_pop($before_pipes);
                    } else {
                        $after_pipes = $form_tag->pipes->collect_afters();
                        $last_item = array_pop($after_pipes);
                    }
                } else {
                    $last_item = array_pop($form_tag->values);
                }

                if ($last_item and wpcf7_is_mailbox_list($last_item)) {
                    return $example_email;
                }

                return $example_text;
            }

            if ('email' == $form_tag->basetype) {
                return $example_email;
            }

            return $example_text;
        }   // maybe special mail tag
        // for back-compat
        $field_name = preg_replace('/^wpcf7\./', '_', $field_name);

        if ('_site_admin_email' == $field_name) {
            return get_bloginfo('admin_email', 'raw');
        }
        if ('_user_agent' == $field_name) {
            return $example_text;
        }
        if ('_user_email' == $field_name) {
            return $this->contact_form->is_true('subscribers_only')
                    ? $example_email
                    : $example_blank;
        }
        if ('_user_' == substr($field_name, 0, 6)) {
            return $this->contact_form->is_true('subscribers_only')
                    ? $example_text
                    : $example_blank;
        }
        if ('_' == substr($field_name, 0, 1)) {
            return '_email' == substr($field_name, -6)
                    ? $example_email
                    : $example_text;
        }

        return $tag;
    }

    public function validate_form()
    {
        $section = 'form.body';
        $form = $this->contact_form->prop('form');
        $this->detect_multiple_controls_in_label($section, $form);
        $this->detect_unavailable_names($section, $form);
        $this->detect_unavailable_html_elements($section, $form);
    }

    public function detect_multiple_controls_in_label($section, $content)
    {
        $pattern = '%<label(?:[ \t\n]+.*?)?>(.+?)</label>%s';

        if (preg_match_all($pattern, $content, $matches)) {
            $form_tags_manager = WPCF7_FormTagsManager::get_instance();

            foreach ($matches[1] as $insidelabel) {
                $tags = $form_tags_manager->scan($insidelabel);
                $fields_count = 0;

                foreach ($tags as $tag) {
                    $is_multiple_controls_container = wpcf7_form_tag_supports(
                        $tag->type, 'multiple-controls-container');
                    $is_zero_controls_container = wpcf7_form_tag_supports(
                        $tag->type, 'zero-controls-container');

                    if ($is_multiple_controls_container) {
                        $fields_count += count($tag->values);

                        if ($tag->has_option('free_text')) {
                            ++$fields_count;
                        }
                    } elseif ($is_zero_controls_container) {
                        $fields_count += 0;
                    } elseif (!empty($tag->name)) {
                        ++$fields_count;
                    }

                    if (1 < $fields_count) {
                        return $this->add_error($section,
                            self::error_multiple_controls_in_label, [
                                'link' => self::get_doc_link('multiple_controls_in_label'),
                            ]
                        );
                    }
                }
            }
        }

        return false;
    }

    public function detect_unavailable_names($section, $content)
    {
        $public_query_vars = ['m', 'p', 'posts', 'w', 'cat',
            'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence',
            'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order',
            'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second',
            'name', 'category_name', 'tag', 'feed', 'author_name', 'static',
            'pagename', 'page_id', 'error', 'attachment', 'attachment_id',
            'subpost', 'subpost_id', 'preview', 'robots', 'taxonomy', 'term',
            'cpage', 'post_type', 'embed', ];

        $form_tags_manager = WPCF7_FormTagsManager::get_instance();
        $ng_named_tags = $form_tags_manager->filter($content,
            ['name' => $public_query_vars]);

        $ng_names = [];

        foreach ($ng_named_tags as $tag) {
            $ng_names[] = sprintf('"%s"', $tag->name);
        }

        if ($ng_names) {
            $ng_names = array_unique($ng_names);

            return $this->add_error($section,
                self::error_unavailable_names,
                [
                    'message' =>
                        /* translators: %names%: a list of form control names */
                        __('Unavailable names (%names%) are used for form controls.', 'contact-form-7'),
                    'params' => ['names' => implode(', ', $ng_names)],
                    'link' => self::get_doc_link('unavailable_names'),
                ]
            );
        }

        return false;
    }

    public function detect_unavailable_html_elements($section, $content)
    {
        $pattern = '%(?:<form[\s\t>]|</form>)%i';

        if (preg_match($pattern, $content)) {
            return $this->add_error($section,
                self::error_unavailable_html_elements,
                [
                    'message' => __('Unavailable HTML elements are used in the form template.', 'contact-form-7'),
                    'link' => self::get_doc_link('unavailable_html_elements'),
                ]
            );
        }

        return false;
    }

    public function validate_mail($template = 'mail')
    {
        $components = (array) $this->contact_form->prop($template);

        if (!$components) {
            return;
        }

        if ('mail' != $template
        and empty($components['active'])) {
            return;
        }

        $components = wp_parse_args($components, [
            'subject' => '',
            'sender' => '',
            'recipient' => '',
            'additional_headers' => '',
            'body' => '',
            'attachments' => '',
        ]);

        $callback = [$this, 'replace_mail_tags_with_minimum_input'];

        $subject = $components['subject'];
        $subject = new WPCF7_MailTaggedText($subject,
            ['callback' => $callback]);
        $subject = $subject->replace_tags();
        $subject = wpcf7_strip_newline($subject);
        $this->detect_maybe_empty(sprintf('%s.subject', $template), $subject);

        $sender = $components['sender'];
        $sender = new WPCF7_MailTaggedText($sender,
            ['callback' => $callback]);
        $sender = $sender->replace_tags();
        $sender = wpcf7_strip_newline($sender);

        if (!$this->detect_invalid_mailbox_syntax(sprintf('%s.sender', $template), $sender)
        and !wpcf7_is_email_in_site_domain($sender)) {
            $this->add_error(sprintf('%s.sender', $template),
                self::error_email_not_in_site_domain, [
                    'link' => self::get_doc_link('email_not_in_site_domain'),
                ]
            );
        }

        $recipient = $components['recipient'];
        $recipient = new WPCF7_MailTaggedText($recipient,
            ['callback' => $callback]);
        $recipient = $recipient->replace_tags();
        $recipient = wpcf7_strip_newline($recipient);

        $this->detect_invalid_mailbox_syntax(
            sprintf('%s.recipient', $template), $recipient);

        $additional_headers = $components['additional_headers'];
        $additional_headers = new WPCF7_MailTaggedText($additional_headers,
            ['callback' => $callback]);
        $additional_headers = $additional_headers->replace_tags();
        $additional_headers = explode("\n", $additional_headers);
        $mailbox_header_types = ['reply-to', 'cc', 'bcc'];
        $invalid_mail_header_exists = false;

        foreach ($additional_headers as $header) {
            $header = trim($header);

            if ('' === $header) {
                continue;
            }

            if (!preg_match('/^([0-9A-Za-z-]+):(.*)$/', $header, $matches)) {
                $invalid_mail_header_exists = true;
            } else {
                $header_name = $matches[1];
                $header_value = trim($matches[2]);

                if (in_array(strtolower($header_name), $mailbox_header_types)) {
                    $this->detect_invalid_mailbox_syntax(
                        sprintf('%s.additional_headers', $template),
                        $header_value, [
                            'message' => __('Invalid mailbox syntax is used in the %name% field.', 'contact-form-7'),
                            'params' => ['name' => $header_name], ]);
                } elseif (empty($header_value)) {
                    $invalid_mail_header_exists = true;
                }
            }
        }

        if ($invalid_mail_header_exists) {
            $this->add_error(sprintf('%s.additional_headers', $template),
                self::error_invalid_mail_header, [
                    'link' => self::get_doc_link('invalid_mail_header'),
                ]
            );
        }

        $body = $components['body'];
        $body = new WPCF7_MailTaggedText($body,
            ['callback' => $callback]);
        $body = $body->replace_tags();
        $this->detect_maybe_empty(sprintf('%s.body', $template), $body);

        if ('' !== $components['attachments']) {
            $attachables = [];

            $tags = $this->contact_form->scan_form_tags(
                ['type' => ['file', 'file*']]
            );

            foreach ($tags as $tag) {
                $name = $tag->name;

                if (false === strpos($components['attachments'], "[{$name}]")) {
                    continue;
                }

                $limit = (int) $tag->get_limit_option();

                if (empty($attachables[$name])
                or $attachables[$name] < $limit) {
                    $attachables[$name] = $limit;
                }
            }

            $total_size = array_sum($attachables);

            $has_file_not_found = false;
            $has_file_not_in_content_dir = false;

            foreach (explode("\n", $components['attachments']) as $line) {
                $line = trim($line);

                if ('' === $line
                or '[' == substr($line, 0, 1)) {
                    continue;
                }

                $has_file_not_found = $this->detect_file_not_found(
                    sprintf('%s.attachments', $template), $line
                );

                if (!$has_file_not_found
                and !$has_file_not_in_content_dir) {
                    $has_file_not_in_content_dir = $this->detect_file_not_in_content_dir(
                        sprintf('%s.attachments', $template), $line
                    );
                }

                if (!$has_file_not_found) {
                    $path = path_join(WP_CONTENT_DIR, $line);
                    $total_size += (int) @filesize($path);
                }
            }

            $max = 25 * 1024 * 1024; // 25 MB

            if ($max < $total_size) {
                $this->add_error(sprintf('%s.attachments', $template),
                    self::error_attachments_overweight,
                    [
                        'message' => __('The total size of attachment files is too large.', 'contact-form-7'),
                        'link' => self::get_doc_link('attachments_overweight'),
                    ]
                );
            }
        }
    }

    public function detect_invalid_mailbox_syntax($section, $content, $args = '')
    {
        $args = wp_parse_args($args, [
            'link' => self::get_doc_link('invalid_mailbox_syntax'),
            'message' => '',
            'params' => [],
        ]);

        if (!wpcf7_is_mailbox_list($content)) {
            return $this->add_error($section,
                self::error_invalid_mailbox_syntax, $args);
        }

        return false;
    }

    public function detect_maybe_empty($section, $content)
    {
        if ('' === $content) {
            return $this->add_error($section,
                self::error_maybe_empty, [
                    'link' => self::get_doc_link('maybe_empty'),
                ]
            );
        }

        return false;
    }

    public function detect_file_not_found($section, $content)
    {
        $path = path_join(WP_CONTENT_DIR, $content);

        if (!is_readable($path)
        or !is_file($path)) {
            return $this->add_error($section,
                self::error_file_not_found,
                [
                    'message' => __('Attachment file does not exist at %path%.', 'contact-form-7'),
                    'params' => ['path' => $content],
                    'link' => self::get_doc_link('file_not_found'),
                ]
            );
        }

        return false;
    }

    public function detect_file_not_in_content_dir($section, $content)
    {
        $path = path_join(WP_CONTENT_DIR, $content);

        if (!wpcf7_is_file_path_in_content_dir($path)) {
            return $this->add_error($section,
                self::error_file_not_in_content_dir,
                [
                    'message' => __('It is not allowed to use files outside the wp-content directory.', 'contact-form-7'),
                    'link' => self::get_doc_link('file_not_in_content_dir'),
                ]
            );
        }

        return false;
    }

    public function validate_messages()
    {
        $messages = (array) $this->contact_form->prop('messages');

        if (!$messages) {
            return;
        }

        if (isset($messages['captcha_not_match'])
        and !wpcf7_use_really_simple_captcha()) {
            unset($messages['captcha_not_match']);
        }

        foreach ($messages as $key => $message) {
            $section = sprintf('messages.%s', $key);
            $this->detect_html_in_message($section, $message);
        }
    }

    public function detect_html_in_message($section, $content)
    {
        $stripped = wp_strip_all_tags($content);

        if ($stripped != $content) {
            return $this->add_error($section,
                self::error_html_in_message,
                [
                    'link' => self::get_doc_link('html_in_message'),
                ]
            );
        }

        return false;
    }

    public function validate_additional_settings()
    {
        $deprecated_settings_used =
            $this->contact_form->additional_setting('on_sent_ok') ||
            $this->contact_form->additional_setting('on_submit');

        if ($deprecated_settings_used) {
            return $this->add_error('additional_settings.body',
                self::error_deprecated_settings,
                [
                    'link' => self::get_doc_link('deprecated_settings'),
                ]
            );
        }
    }
}

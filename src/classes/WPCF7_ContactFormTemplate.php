<?php

/**
 * Class WPCF7_ContactFormTemplate.
 */
class WPCF7_ContactFormTemplate
{
    /**
     * @param string $prop
     *
     * @return mixed|void
     */
    public static function get_default($prop = 'form')
    {
        if ('form' == $prop) {
            $template = self::form();
        } elseif ('mail' == $prop) {
            $template = self::mail();
        } elseif ('mail_2' == $prop) {
            $template = self::mail_2();
        } elseif ('messages' == $prop) {
            $template = self::messages();
        } else {
            $template = null;
        }

        return apply_filters('wpcf7_default_template', $template, $prop);
    }

    /**
     * @return string
     */
    public static function form()
    {
        $template = sprintf('
<label> %2$s %1$s
    [text* your-name] </label>

<label> %3$s %1$s
    [email* your-email] </label>

<label> %4$s
    [text your-subject] </label>

<label> %5$s
    [textarea your-message] </label>

[submit "%6$s"]', __('(required)', 'contact-form-7'), __('Your Name', 'contact-form-7'),
            __('Your Email', 'contact-form-7'), __('Subject', 'contact-form-7'), __('Your Message', 'contact-form-7'),
            __('Send', 'contact-form-7'));

        return trim($template);
    }

    /**
     * @return array
     */
    public static function mail()
    {
        return [
            'subject' => sprintf(/* translators: 1: blog name, 2: [your-subject] */ _x('%1$s "%2$s"',
                'mail subject', 'contact-form-7'), get_bloginfo('name'), '[your-subject]'),
            'sender' => sprintf('%s <%s>', get_bloginfo('name'), self::from_email()),
            'body' => /* translators: %s: [your-name] <[your-email]> */
                sprintf(__('From: %s', 'contact-form-7'),
                    '[your-name] <[your-email]>')."\n" /* translators: %s: [your-subject] */.sprintf(__('Subject: %s',
                    'contact-form-7'), '[your-subject]')."\n\n".__('Message Body:',
                    'contact-form-7')."\n".'[your-message]'."\n\n".'-- '."\n".sprintf(/* translators: 1: blog name, 2: blog URL */ __('This e-mail was sent from a contact form on %1$s (%2$s)',
                    'contact-form-7'), get_bloginfo('name'), get_bloginfo('url')),
            'recipient' => get_option('admin_email'),
            'additional_headers' => 'Reply-To: [your-email]',
            'attachments' => '',
            'use_html' => 0,
            'exclude_blank' => 0,
        ];
    }

    /**
     * @return array
     */
    public static function mail_2()
    {
        return [
            'active' => false,
            'subject' => sprintf(/* translators: 1: blog name, 2: [your-subject] */ _x('%1$s "%2$s"',
                'mail subject', 'contact-form-7'), get_bloginfo('name'), '[your-subject]'),
            'sender' => sprintf('%s <%s>', get_bloginfo('name'), self::from_email()),
            'body' => __('Message Body:',
                    'contact-form-7')."\n".'[your-message]'."\n\n".'-- '."\n".sprintf(/* translators: 1: blog name, 2: blog URL */ __('This e-mail was sent from a contact form on %1$s (%2$s)',
                    'contact-form-7'), get_bloginfo('name'), get_bloginfo('url')),
            'recipient' => '[your-email]',
            'additional_headers' => sprintf('Reply-To: %s', get_option('admin_email')),
            'attachments' => '',
            'use_html' => 0,
            'exclude_blank' => 0,
        ];
    }

    /**
     * @return mixed|string|void
     */
    public static function from_email()
    {
        $admin_email = get_option('admin_email');
        $sitename = strtolower($_SERVER['SERVER_NAME']);

        if (wpcf7_is_localhost()) {
            return $admin_email;
        }

        if (0 === strpos($sitename, 'www.')) {
            $sitename = substr($sitename, 4);
        }

        if (strpbrk($admin_email, '@') == '@'.$sitename) {
            return $admin_email;
        }

        return 'wordpress@'.$sitename;
    }

    /**
     * @return array
     */
    public static function messages()
    {
        $messages = [];

        foreach (wpcf7_messages() as $key => $arr) {
            $messages[$key] = $arr['default'];
        }

        return $messages;
    }
}

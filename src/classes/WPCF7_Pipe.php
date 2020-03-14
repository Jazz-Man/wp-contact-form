<?php

class WPCF7_Pipe
{
    public $before = '';
    public $after = '';

    public function __construct($text)
    {
        $text = (string) $text;

        $pipe_pos = strpos($text, '|');

        if (false === $pipe_pos) {
            $this->before = $this->after = trim($text);
        } else {
            $this->before = trim(substr($text, 0, $pipe_pos));
            $this->after = trim(substr($text, $pipe_pos + 1));
        }
    }
}

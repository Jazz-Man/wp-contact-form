<?php

/**
 * Class WPCF7_Pipes.
 */
class WPCF7_Pipes
{
    private $pipes = [];

    /**
     * WPCF7_Pipes constructor.
     */
    public function __construct(array $texts)
    {
        foreach ($texts as $text) {
            $this->add_pipe($text);
        }
    }

    /**
     * @param $before
     *
     * @return mixed
     */
    public function do_pipe($before)
    {
        foreach ($this->pipes as $pipe) {
            if ($pipe->before == $before) {
                return $pipe->after;
            }
        }

        return $before;
    }

    /**
     * @return array
     */
    public function collect_befores()
    {
        $befores = [];

        foreach ($this->pipes as $pipe) {
            $befores[] = $pipe->before;
        }

        return $befores;
    }

    /**
     * @return array
     */
    public function collect_afters()
    {
        $afters = [];

        foreach ($this->pipes as $pipe) {
            $afters[] = $pipe->after;
        }

        return $afters;
    }

    /**
     * @return bool
     */
    public function zero()
    {
        return empty($this->pipes);
    }

    /**
     * @return mixed|null
     */
    public function random_pipe()
    {
        if ($this->zero()) {
            return null;
        }

        return $this->pipes[array_rand($this->pipes)];
    }

    /**
     * @param $text
     */
    private function add_pipe($text)
    {
        $pipe = new WPCF7_Pipe($text);
        $this->pipes[] = $pipe;
    }
}

<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly\Module;

abstract class Base {
    protected $options;

    public function __construct(array $options = []) {
        $this->options = $options;
    }

    abstract function registerHooks();
}

<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly;

class Plugin {
    protected $options = [];

    /** @var Module\Base[] $modules */
    protected $modules = null;

    function __construct($options) {
        $this->options = $options;
    }

    function registerHooks() {
        $this->modules = [
            new Module\AdminPageAuthorize(),
            new Module\AdminPageSettings(),
            new Module\PostTypeAuthorizedChannel(),
        ];

        // register module hooks
        foreach($this->modules as $module) {
            $module->registerHooks();
        }
    }
}

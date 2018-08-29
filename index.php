<?php
/*
Plugin Name: YouTube API Only
Plugin URI: https://github.com/massedge/wordpress-plugin-youtube-api-only
Description: Wordpress plugin that provides a configuration interface for YouTube API integration. It's meant to be used by developers who want to integrate YouTube API in their own themes/plugins.
Version: 0.1.0
Author: Mass Edge Inc.
Author URI: https://www.massedge.com/
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// ensure all dependencies met before fully initializing plugin code
require 'lib/DependencyCheck.php';
if (!(new MassEdgeWordPressPluginYouTubeApiOnlyDependencyCheck(__FILE__))->run()) return;

define('MASSEDGE_WORDPRESS_PLUGIN_YOUTUBE_API_ONLY_PLUGIN_PATH', __FILE__);
require 'run.php';

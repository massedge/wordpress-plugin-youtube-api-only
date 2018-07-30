<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly\Module;

use MassEdge\WordPress\Plugin\YouTubeApiOnly\API;

class PostTypeAuthorizedChannel extends Base {
    function registerHooks() {
        add_action('init', [$this, 'register_post_type'], 9);
    }

    function register_post_type() {
        register_post_type(API::POST_TYPE_AUTHORIZED_YOUTUBE_CHANNEL, [
            'labels' => [
                'name' => __('Authorized YouTube Channels'),
                'singular_name' => __('Authorized YouTube Channel'),
                'add_new' => __('Add New'),
                'add_new_item' => __('Add New Authorized YouTube Channel'),
                'edit_item' => __('Edit Authorized YouTube Channel'),
                'new_item' => __('New Authorized YouTube Channel'),
                'view_item' => __('View Authorized YouTube Channel'),
                'all_items' => __( 'All Authorized YouTube Channels'),
                'search_items' => __('Search Authorized YouTube Channels'),
                'not_found' => __('No authorized YouTube channels found'),
                'not_found_in_trash' => __('No authorized YouTube channels found in Trash'),
            ],
            'public' => true,
            'publicly_queryable' => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'menu_icon' => 'dashicons-playlist-video',
            'supports' => [
                'title',
                'revisions',
            ],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => false,
                'edit_published_posts' => false,
            ],
            'map_meta_cap' => true,
        ]);
    }
}
<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly;

class API {
    const OPTION_NAME_API_KEY = 'mass-edge-youtube-api-only-api-key';
    const OPTION_NAME_CLIENT_ID = 'mass-edge-youtube-api-only-client-id';
    const OPTION_NAME_CLIENT_SECRET = 'mass-edge-youtube-api-only-client-secret';

    static function setApiKey($value) {
        update_option(self::OPTION_NAME_API_KEY, $value);
    }

    static function getApiKey() {
        return get_option(self::OPTION_NAME_API_KEY);
    }

    static function setClientId($value) {
        update_option(self::OPTION_NAME_CLIENT_ID, $value);
    }

    static function getClientId() {
        return get_option(self::OPTION_NAME_CLIENT_ID);
    }

    static function setClientSecret($value) {
        update_option(self::OPTION_NAME_CLIENT_SECRET, $value);
    }

    static function getClientSecret() {
        return get_option(self::OPTION_NAME_CLIENT_SECRET);
    }
}

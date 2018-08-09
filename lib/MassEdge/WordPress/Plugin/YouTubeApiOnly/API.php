<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly;

class API {
    const OPTION_NAME_API_KEY = 'mass-edge-youtube-api-only-api-key';
    const OPTION_NAME_CLIENT_ID = 'mass-edge-youtube-api-only-client-id';
    const OPTION_NAME_CLIENT_SECRET = 'mass-edge-youtube-api-only-client-secret';

    const POST_TYPE_AUTHORIZED_YOUTUBE_CHANNEL = 'me_ytapi_auth_chanl';

    /**
     * @return \Google_Client
     */
    static function getAuthClient() {
        $clientId = self::getClientId();
        $clientSecret = self::getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Client ID and Client Secret must be set in order to create the OAuth client.');
        }

        $client = new \Google_Client();
        $client->setClientId(API::getClientId());
        $client->setClientSecret(API::getClientSecret());

        $client->addScope(\Google_Service_YouTube::YOUTUBE_READONLY);
        $client->addScope(\Google_Service_YouTube::YOUTUBE_UPLOAD);
        $client->addScope(\Google_Service_YouTube::YOUTUBE_FORCE_SSL);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        return $client;
    }

    /**
     * @param \WP_Post $authChannel
     * @param boolean $refreshToken
     * @return \Google_Client
     */
    static function getAuthClientByAuthChannel(\WP_Post $authChannel, $refreshToken = true) {
        $client = self::getAuthClient();
        $accessTokenStr = get_post_meta($authChannel->ID, 'access_token', true);

        if (empty($accessTokenStr)) {
            throw new \Exception(sprintf('Access token not defined for authenticated channel. (authChannelId: %d)', $authChannel->ID));
        }

        $accessToken = json_decode($accessTokenStr, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception(sprintf('Failed to parse access token. (authChannelId: %d, jsonError: %d)', $authChannel->ID, json_last_error_msg()));
        }

        $client->setAccessToken($accessToken);

        if ($refreshToken && $client->isAccessTokenExpired()) {
            $result = $client->fetchAccessTokenWithRefreshToken();

            if (!empty($result['error'])) {
                throw new \Exception(sprintf('Failed to refresh token. (error: %s, errorDescription: %s)', $result['error'], $result['error_description']));
            }

            $accessToken = $result;
            self::updateAuthorizedChannelMeta($authChannel, $accessToken);
        }

        return $client;
    }

    static function addAuthorizedChannel(array $accessToken, \Google_Service_YouTube_Channel $channel) {
        $post = self::getAuthorizedChannelByChannelId($channel->getId());

        $result = wp_insert_post([
            'ID' => ($post) ? $post->ID : null,
            'post_title' => $channel->getSnippet()->getTitle(),
            'post_type' => self::POST_TYPE_AUTHORIZED_YOUTUBE_CHANNEL,
            'post_status' => 'publish',
            'meta_input' => [
                'channel_id' => $channel->getId(),
            ],
        ], true);

        if ($result instanceof \WP_Error) {
            throw new \Exception($result->get_error_message());
        }

        $authChannel = get_post($result);

        self::updateAuthorizedChannelMeta($authChannel, $accessToken);
    }

    static function getAuthorizedChannelByChannelId($channelId) {
        $query = new \WP_Query();

        $posts = $query->query([
            'post_type' => self::POST_TYPE_AUTHORIZED_YOUTUBE_CHANNEL,
            'meta_query' => [[
                'key' => 'channel_id',
                'value' => $channelId,
            ]]
        ]);

        return (sizeof($posts) > 0) ? $posts[0] : null;
    }

    private static function updateAuthorizedChannelMeta(\WP_Post $authChannel, array $accessToken) {
        $result = update_post_meta($authChannel->ID, 'access_token', json_encode($accessToken));

        if (false === $result) {
            throw new \Exception(sprintf('Failed to update authorized channel meta. (authChannel: %d, metaKey: %s)', $authChannel->ID, 'access_token'));
        }

        $result = update_post_meta($authChannel->ID, 'access_token_expires_at', $accessToken['created'] + $accessToken['expires_in']);

        if (false === $result) {
            throw new \Exception(sprintf('Failed to update authorized channel meta. (authChannel: %d, metaKey: %s)', $authChannel->ID, 'access_token_expires_at'));
        }
    }

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

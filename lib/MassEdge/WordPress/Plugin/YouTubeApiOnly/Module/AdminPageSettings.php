<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly\Module;

use MassEdge\WordPress\Plugin\YouTubeApiOnly\API;

class AdminPageSettings extends Base {

    const REQUIRED_CAPABILITY = 'upload_plugins';
    const MENU_SLUG = 'mass-edge-youtube-api-only';

    const FIELD_SUBMIT = 'massedge-wp-plugin-youtube-api-ape-submit';
    const FIELD_NONCE_SUBMIT_ACTION = 'massedge-wp-youtube-api-ape-submit';
    const FIELD_API_KEY = 'api_key';
    const FIELD_CLIENT_ID = 'client_id';
    const FIELD_CLIENT_SECRET = 'client_secret';

    private $errors = [];

    function registerHooks() {
        add_action('admin_menu', [$this, 'add_submenu_page']);
        add_action('admin_init', [$this, 'process_form']);
    }
    
    function add_submenu_page() {
        add_submenu_page(
            'options-general.php',
            'YouTube API',
            'YouTube API',
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            [$this, 'page']
        );
    }

    function process_form() {
        // check if download submitted
        if (empty($_POST[self::FIELD_SUBMIT])) return;

        // capability check
        if (!current_user_can(self::REQUIRED_CAPABILITY)) return;

        // nonce check
        if (!check_admin_referer(self::FIELD_NONCE_SUBMIT_ACTION)) return;

        $apiKey = trim($_POST[self::FIELD_API_KEY]);
        $clientId = trim($_POST[self::FIELD_CLIENT_ID]);
        $clientSecret = trim($_POST[self::FIELD_CLIENT_SECRET]);
        
        // validate api
        if (!empty($apiKey)) {
            $errors = $this->validateApiKey($apiKey);

            if (!empty($errors)) {
                foreach($errors as $error) {
                    $this->errors[] = sprintf('Error validating API Key. (%s)', $error);
                }
            }
        }

        // validate oauth
        if ( (empty($clientId) && !empty($clientSecret)) || (!empty($clientId) && empty($clientSecret))) {
            $this->errors[] = 'Client ID and Client Secret both have to either be specified or empty.';
        } else if (!empty($clientId) && !empty($clientSecret)) {
            // TODO: is it possible to validate id/secret without doing full OAuth?
        }

        // don't save anything if there are errors
        if (!empty($this->errors)) return;

        // store data
        API::setApiKey($apiKey);
        API::setClientId($clientId);
        API::setClientSecret($clientSecret);
        
		wp_redirect(self::getUri());
		die();
    }

    private function validateApiKey($apiKey) {
        $client = new \Google_Client();
        $client->setDeveloperKey($apiKey);

        $youtube = new \Google_Service_YouTube($client);

        try {
            $response = $youtube->videos->listVideos('id', array(
                'chart' => 'mostpopular',
                'maxResults' => 1,
            ));
        } catch(\Google_Service_Exception $ex) {
            return array_map(function($error) {
                return sprintf('domain: %s, reason: %s, message: %s', $error['domain'], $error['reason'], $error['message']);
            }, $ex->getErrors());
        }

        return [];
    }

    function page() {
        $isPost = !empty($_POST[self::FIELD_SUBMIT]);

        $apiKey = ($isPost) ? $_POST[self::FIELD_API_KEY] : API::getApiKey();
        $clientId = ($isPost) ? $_POST[self::FIELD_CLIENT_ID] : API::getClientId();
        $clientSecret = ($isPost) ? $_POST[self::FIELD_CLIENT_SECRET] : API::getClientSecret();

        ob_start();
        ?>
<div class="wrap">
    <h1>YouTube API</h1>


    <?php if (!empty($this->errors)): ?>
        <ul style="color: red;">
        <?php foreach($this->errors as $error): ?>
        <li><?php echo esc_html($error) ?>
        <?php endforeach ?>
        </ul>
    <?php endif ?>

    <br>

    <form action="<?php echo esc_attr(self::getUri()) ?>" method="post">

        <h2>API Key</h2>
        <p>Identifies your project using a simple API key to check quota and access.</p>

        <table class="form-table">
            <tr>
                <th>API Key</th>
                <td>
                    <input
                        type="text"
                        name="<?php echo esc_attr(self::FIELD_API_KEY) ?>"
                        value="<?php echo esc_attr($apiKey) ?>"
                        autocomplete="off"
                        size="50"
                    />
                </td>
            </tr>
        </table>

        <br>

        <h2>OAuth client ID</h2>
        <p>Requests user consent so your app can access the user's data.</p>
        <p>When creating add the following url to "Authorized redirect URIs": <br /><?php echo esc_html(AdminPageAuthorize::getRedirectUri()) ?></p>

        <table class="form-table">
            <tr>
                <th>Client ID</th>
                <td>
                    <input
                        type="text"
                        name="<?php echo esc_attr(self::FIELD_CLIENT_ID) ?>"
                        value="<?php echo esc_attr($clientId) ?>"
                        autocomplete="off"
                        size="50"
                    />
                </td>
            </tr>
            <tr>
                <th>Client Secret</th>
                <td>
                    <input
                        type="text"
                        name="<?php echo esc_attr(self::FIELD_CLIENT_SECRET) ?>"
                        value="<?php echo esc_attr($clientSecret) ?>"
                        autocomplete="off"
                        size="50"
                    />
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary" name="<?php echo self::FIELD_SUBMIT ?>" value="1">Save</button>
        </p>

        <?php wp_nonce_field(self::FIELD_NONCE_SUBMIT_ACTION) ?>
    </form>
</div>
        <?php
        echo ob_get_clean();
    }
    

    static function getUri() {
        return admin_url(sprintf('options-general.php?page=%s', self::MENU_SLUG));
    }
}

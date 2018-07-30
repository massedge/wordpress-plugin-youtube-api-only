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

    const AUTHORIZE_QUERY_ARG = 'authorize';
    const AUTHORIZE_QUERY_ARG_AUTH_CODE = 'code';
    const TRANSIENT_NOTICE_WITH_USER_ID = '%d-me-ytapi-config-notice';

    private $errors = [];

    function registerHooks() {
        add_action('admin_menu', [$this, 'add_submenu_page']);
        add_action('admin_init', [$this, 'process_form']);
        add_action('admin_init', [$this, 'authorize']);
        add_action('admin_notices', [$this, 'admin_notices']);
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
        
		wp_redirect($this->getUri());
		die();
    }

    function authorize() {
        if (empty($_GET[self::AUTHORIZE_QUERY_ARG])) return;
        if (empty($_GET[self::AUTHORIZE_QUERY_ARG_AUTH_CODE])) return;

        $authCode = $_GET[self::AUTHORIZE_QUERY_ARG_AUTH_CODE];

        $client = API::getAuthClient();
        $client->setRedirectUri($this->getRedirectUri());
        $result = $client->authenticate($authCode);

        if (!empty($result['error'])) {
            $this->setNotice(sprintf('Failed to authorize channel. %s (%s)', $result['error_description'], $result['error']), 'error');
        } else {
            $accessToken = $result;

            $youtube = new \Google_Service_YouTube($client);

            /** @var \Google_Service_YouTube_ChannelListResponse[] $response */
            $response = $youtube->channels->listChannels('snippet,contentDetails,statistics', [
                'mine' => true,
            ]);

            /** @var \Google_Service_YouTube_Channel[] $channels */
            $channels = $response->getItems();

            if (sizeof($channels) <= 0) {
                $this->setNotice('No channels are associated with this account.', 'error');
            } else {
                $channel = $channels[0];

                try {
                    API::addAuthorizedChannel($accessToken, $channel);

                    $this->setNotice(sprintf('Successfully authorized \'%s\' channel.', $channel->getSnippet()->getTitle()), 'success');
                } catch(\Exception $ex) {
                    $this->setNotice($ex->getMessage(), 'error');
                }
            }
        }
        
		wp_redirect($this->getUri());
		die();
    }

    private function getNoticeTransient() {
        return sprintf(self::TRANSIENT_NOTICE_WITH_USER_ID, get_current_user_id());
    }

    private function setNotice($notice, $type) {
        $value = json_encode([
            'text' => $notice,
            'type' => $type,
        ]);

        return set_transient($this->getNoticeTransient(), $value);
    }

    private function getNotice() {
        $transient = get_transient($this->getNoticeTransient());

        if ($transient) {
            delete_transient($this->getNoticeTransient());

            return json_decode($transient, true);
        }

        return null;
    }

    function admin_notices() {
        $notice = $this->getNotice();
        if (empty($notice)) return;

        $message = sprintf(
            '<div class="%1$s"><p>%2$s</p></div>',
            'notice is-dismissible notice-' . esc_attr($notice['type']),
            $notice['text']
        );

        echo $message;
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

    <form action="<?php echo esc_attr($this->getUri()) ?>" method="post">

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
        <p>When creating add the following url to "Authorized redirect URIs": <br /><?php echo esc_html($this->getRedirectUri()) ?></p>

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

    <?php if (!$isPost && API::getClientId() && API::getClientSecret()): ?>
    <table class="form-table">
        <tr>
            <th>Authorize Channel</th>
            <td>
                <a href="<?php echo esc_attr($this->getAuthUrl()) ?>">Authorize</a>
                <p>
                    This will ask you to give authorization for this plugin
                    to access/modify a YouTube channel.
                </p>
            </td>
        </tr>
    </table>
    <?php endif ?>
</div>
        <?php
        echo ob_get_clean();
    }

    function getAuthUrl() {
        $client = API::getAuthClient();
        $client->setRedirectUri($this->getRedirectUri());
        return $client->createAuthUrl();
    }

    function authenticate($authCode) {
        $client = API::getAuthClient();
        $accessToken = $client->authenticate($authCode);
        return $accessToken;
    }
    

    function getUri() {
        return admin_url(sprintf('options-general.php?page=%s', self::MENU_SLUG));
    }

    function getRedirectUri() {
        return add_query_arg(self::AUTHORIZE_QUERY_ARG, 'yes', $this->getUri());
    }
}

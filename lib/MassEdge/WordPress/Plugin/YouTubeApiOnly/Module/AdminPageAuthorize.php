<?php

namespace MassEdge\WordPress\Plugin\YouTubeApiOnly\Module;

use MassEdge\WordPress\Plugin\YouTubeApiOnly\API;

class AdminPageAuthorize extends Base {

    const REQUIRED_CAPABILITY = 'publish_pages';
    const MENU_SLUG = 'mass-edge-youtube-api-only-authorize';

    const AUTHORIZE_QUERY_ARG = 'authorize';
    const AUTHORIZE_QUERY_ARG_AUTH_CODE = 'code';
    const TRANSIENT_NOTICE_WITH_USER_ID = '%d-me-ytapi-config-notice';

    function registerHooks() {
        if (!API::getClientId() || !API::getClientSecret()) return;
        add_action('admin_menu', [$this, 'add_submenu_page']);
        add_action('admin_init', [$this, 'authorize']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }
    
    function add_submenu_page() {
        add_submenu_page(
            'edit.php?post_type=' . API::POST_TYPE_AUTHORIZED_YOUTUBE_CHANNEL,
            'Authorize YouTube Channel',
            'Authorize Channel',
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            [$this, 'page']
        );
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

    function page() {
        ob_start();
        ?>
<div class="wrap">
    <h1>YouTube API</h1>

    <?php if (API::getClientId() && API::getClientSecret()): ?>
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
    

    static function getUri() {
        return admin_url(sprintf('edit.php?post_type=%s&page=%s', API::POST_TYPE_AUTHORIZED_YOUTUBE_CHANNEL, self::MENU_SLUG));
    }

    static function getRedirectUri() {
        return add_query_arg(self::AUTHORIZE_QUERY_ARG, 'yes', self::getUri());
    }
}

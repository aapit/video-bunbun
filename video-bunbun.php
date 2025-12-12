<?php
/**
Plugin Name:  Video BunBun
Plugin URI:   https://github.com/aapit/video-bunbun
Description:  Synchronizes uploaded videos with Bunny.net Stream service
              Note: Turn on Security / Direct Play
Version:      1.0.0
Author:       David Spreekmeester
Author URI:   https://spreekmeester.nl
*/
if (!defined('ABSPATH')) {
    exit;
}
use Microsoft\Kiota\Abstractions\ApiException;

require_once __DIR__ . '/vendor/autoload.php';

class BunnyStreamVideoLib {
    protected $_client;
    protected $_accessKey;
    protected $_libraryId;

    function __construct($accessKey, $libraryId) {
        $this->_client = new \GuzzleHttp\Client();
        $this->_accessKey = $accessKey;
        $this->_libraryId = $libraryId;
    }

    /**
      * @param string $title
      * @return array
      **/
    function getVideo($videoId) {
        $url = $this->_createRequestUrl($videoId);
        $response = $this->_client->request('GET', $url, [
            'headers' => $this->_createRequestHeaders(),
        ]);
        if ($response->getStatusCode() == '200') {
            return json_decode($response->getBody());
        }
    }

    /**
      * @param string $title
      * @return int
      **/
    function createVideo($title) {
        $url = $this->_createRequestUrl();
        $response = $this->_client->request('POST', $url, [
            'headers' => $this->_createRequestHeaders(),
            'body' => '{"title":"' . $title . '"}',
        ]);

        if ($response->getStatusCode() == '200') {
            $respArr = json_decode($response->getBody());
            return $respArr->guid;
        } else throw new Exception($response->getStatusCode());
    }

    function uploadVideo($videoId, $filePath) {
        $url = $this->_createRequestUrl($videoId);
        $response = $this->_client->request('PUT', $url, [
            'headers' => $this->_createRequestHeaders(false),
            'body' => fopen($filePath, 'rb'),
        ]);

        if ($response->getStatusCode() == '200') {
            $respArr = json_decode($response->getBody());
            return $respArr;
        } else throw new Exception($response->getStatusCode());
    }

    protected function _createRequestHeaders($addContentType = true) {
        $headers = array(
            'AccessKey'    => $this->_accessKey,
            'accept'       => 'application/json',
        );
        if ($addContentType) {
            $headers['content-type'] = 'application/json';
        }
        return $headers;
    }

    protected function _createRequestUrl($videoId = null) {
        $url = 'https://video.bunnycdn.com/library/'
            . $this->_libraryId . '/videos';
        if ($videoId) {
            $url .= '/' . $videoId;
        }
        return $url;
    }
}

// __________ SYNC AFTER UPLOAD _____________
function bunbun_after_upload($attachmentId) {
    $apiKey = get_option('bun_api_key');
    $libraryId = get_option('bun_library_id');

    if (!$apiKey) {
        error_log("Error: API key missing in plugin settings.");
        return;
    }
    if (!$libraryId) {
        error_log("Error: Library ID missing in plugin settings.");
        return;
    }

    $mimeType = get_post_mime_type($attachmentId);
    if (strpos($mimeType, 'video') !== false) {
        $videoLib = new BunnyStreamVideoLib($apiKey, $libraryId);
        $filePath = get_attached_file($attachmentId);
        $videoId = $videoLib->createVideo("Unnamed video");
        $response = $videoLib->uploadVideo($videoId, $filePath);
        $videoData = $videoLib->getVideo($videoId);
//error_log()
//print_r($videoData);
//die();
        update_post_meta($attachmentId, 'bun_video_id', $videoId);
    }

}

add_action('add_attachment', 'bunbun_after_upload');


// __________ CONFIG IN ADMIN _____________
function bun_settings_menu() {
    add_options_page(
        'Video BunBun Plugin Settings',
        'Video BunBun',
        'manage_options',
        'bun-plugin-settings',
        'bun_settings_page_html'
    );
}

function bun_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('bun_option_group');
            do_settings_sections('bun-plugin-settings');
            submit_button('Save');
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', 'bun_settings_menu');

function bun_register_settings() {
    // Stream API key
    register_setting(
        'bun_option_group',
        'bun_api_key',
        'sanitize_text_field'
    );

    add_settings_section(
        'bun_section_id',
        'Bunny.net settings',
        'bun_section_callback',
        'bun-plugin-settings'
    );

    add_settings_field(
        'bun_api_key_field_id',
        'Bunny Stream API key',
        'bun_api_key_callback',
        'bun-plugin-settings',
        'bun_section_id'
    );

    // Stream Library ID
    register_setting(
        'bun_option_group',
        'bun_library_id',
        'sanitize_text_field'
    );

    add_settings_field(
        'bun_library_id_field_id',
        'Bunny Stream Library ID',
        'bun_library_id_callback',
        'bun-plugin-settings',
        'bun_section_id'
    );

    // Stream CDN hostname
    register_setting(
        'bun_option_group',
        'bun_cdn_hostname',
        'sanitize_text_field'
    );

    add_settings_field(
        'bun_cdn_hostname_field_id',
        'Bunny Stream CDN hostname',
        'bun_cdn_hostname_callback',
        'bun-plugin-settings',
        'bun_section_id'
    );
}

function bun_section_callback() {
    echo '<p>Fill out the required keys and ids for the Bunny.net Stream API.</p><p>Don\'t forget to turn on Security &gt; Direct Play on Bunny.net.</p>';
}
function bun_api_key_callback() {
    $val = get_option('bun_api_key');

    ?>
    <input type="text"
           name="bun_api_key"
           value="<?php echo esc_attr($val); ?>"
           placeholder="Fill out your Bunny Stream API key here"
           style="width: 400px;" />
    <?php
}
function bun_library_id_callback() {
    $val = get_option('bun_library_id');

    ?>
    <input type="text"
           name="bun_library_id"
           value="<?php echo esc_attr($val); ?>"
           placeholder="Fill out your Bunny Stream Library ID here"
           style="width: 400px;" />
    <?php
}
function bun_cdn_hostname_callback() {
    $val = get_option('bun_cdn_hostname');

    ?>
    <input type="text"
           name="bun_cdn_hostname"
           value="<?php echo esc_attr($val); ?>"
           placeholder="Fill out your Bunny Stream CDN hostname here"
           style="width: 400px;" />
    <?php
}

add_action('admin_init', 'bun_register_settings');

// __________ DISPLAY ON FRONTEND _____________
/**
 * Filtert de waarde van het 'background_video' veld voordat deze wordt weergegeven.
 *
 * @param mixed $value De oorspronkelijke waarde van het ACF veld (waarschijnlijk een URL/ID).
 * @param int $post_id De ID van de post waar het veld bij hoort.
 * @param array $field De volledige array van het ACF veld.
 * @return mixed De gewijzigde waarde (URL, ID, of HTML).
 */
function bun_replace_acf_video_field($value, $postId, $field) {
    if (!is_array($value)) {
        return $value;
    }

    $attachment_id = isset($value['ID']) ? $value['ID'] : 0;

    if ($attachment_id) {
        //$newUrl = get_post_meta($attachment_id, '_mijn_verwerkte_video_url', true);

        $meta = get_post_meta($attachment_id);
        if (
            array_key_exists('bun_video_id', $meta) &&
            array_key_exists(0, $meta['bun_video_id'])
        ) {
            $videoId = $meta['bun_video_id'][0];
            print_r($meta);
            $hostname = 'iframe.mediadelivery.net';
            #$hostname = get_option('bun_cdn_hostname');
            $libraryId = get_option('bun_library_id');
            $newUrl = 'https://' . $hostname . '/play/' . $libraryId . '/' . $videoId;
            if ($newUrl) {
                $value['url'] = $newUrl;
            }
        }

    }

    return $value;
    /*
    echo "<pre>";
    echo "attachmentId: " . $attachmentId;
    echo '<br/>';
    print_r($field);
    echo "</pre>";
    */
}

// Koppel de functie aan de ACF filter voor jouw specifieke veldnaam
add_filter('acf/format_value/name=background_video', 'bun_replace_acf_video_field', 10, 3);

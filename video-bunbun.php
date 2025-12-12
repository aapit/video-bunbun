<?php
/**
Plugin Name:  Video BunBun
Plugin URI:   https://example.com
Description:  Synchroniseert geüploade videos met Bunny.net Stream
Version:      1.0.0
Author:       David Spreekmeester
Author URI:   https://spreekmeester.nl
*/
// Voorkom directe toegang tot het bestand
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
      die(print_r($respArr));
      return $respArr->guid;
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
        $videoId = $videoLib->createVideo("Testje 1");
        $response = $videoLib->uploadVideo($videoId, $filePath);
    }

    // Extra metadata toevoegen
    //update_post_meta($attachment_id, 'geüpload_door_script', true);
}

add_action('add_attachment', 'bunbun_after_upload');


// __________ INSTELLINGEN _____________
function bun_settings_menu() {
    add_options_page(
        'Mijn Plugin Instellingen',
        'Video BunBun',
        'manage_options',
        'mijn-plugin-instellingen',
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
        'API Configuratie',
        'bun_section_callback',
        'bun-plugin-settings'
    );

    add_settings_field(
        'bun_api_key_field_id',
        'Bunny.net Stream API key',
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

    add_settings_section(
        'bun_section_id',
        'Library ID',
        'bun_section_callback',
        'bun-plugin-settings'
    );

    add_settings_field(
        'bun_library_id_field_id',
        'Bunny.net Stream Library ID',
        'bun_library_id_callback',
        'bun-plugin-settings',
        'bun_section_id'
    );
}

function bun_section_callback() {
    echo '<p>Fill out the required keys and ids for the Bunny.net Stream API.</p>';
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

add_action('admin_init', 'bun_register_settings');

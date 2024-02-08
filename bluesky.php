<?php
/*
Plugin Name: Bluesky Social Integration
Description: Seamlessly Post Your WordPress Content on BlueSky
Version: 1.5.1
Author: Linus Rath
*/

include('admin-page.php');

function bluesky_save_postdata($post_id) {
    $new_value = isset($_POST['bluesky_field']) && $_POST['bluesky_field'] === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_bluesky_post_checkbox', $new_value);
}

add_action('save_post', 'bluesky_save_postdata');

function bluesky_add_custom_box() {
    add_meta_box(
        'bluesky_meta_box_id',
        'BlueSky Post Integration',
        'bluesky_custom_box_html',
        'post'
    );
}
add_action('add_meta_boxes', 'bluesky_add_custom_box');

add_action('add_meta_boxes', 'bluesky_add_custom_box');

function bluesky_save_default_meta_value($post_id, $post, $update) {
    if ($post->post_status == 'auto-draft' && get_option('bluesky_auto_post_new', 'no') === '1') {
        update_post_meta($post_id, '_bluesky_post_checkbox', 'yes');
    }
}
add_action('save_post', 'bluesky_save_default_meta_value', 10, 3);

function bluesky_custom_box_html($post) {
    $is_new_post = $post->post_status == 'auto-draft';
    $auto_post_enabled = get_option('bluesky_auto_post_new', 'no');

    $should_be_checked = ($is_new_post && $auto_post_enabled === '1') ? 'yes' : 'no';

    ?>
    <label for="bluesky_field">Post to BlueSky:</label>
    <input type="checkbox" id="bluesky_field" name="bluesky_field" value="yes" <?php checked($should_be_checked, 'yes'); ?>>
    <?php
}



function authenticate_bluesky() {
    $server_url = get_option('bluesky_server_url', 'https://bsky.social');

    $handle = get_option('bluesky_handle');
    $password = get_option('bluesky_password');
    
    $response = wp_remote_post($server_url . '/xrpc/com.atproto.server.createSession', [
        'body' => json_encode([
            'identifier' => $handle,
            'password' => $password,
        ]),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['accessJwt'] ?? false;
}

function post_to_bluesky($post_ID) {
    $bluesky_post = get_post_meta($post_ID, '_bluesky_post_checkbox', true);
    if ($bluesky_post !== 'yes') {
        return;
    }

    $delay_enabled = get_option('bluesky_delay_enabled', '0'); 
    if ($delay_enabled === '1') {
        $user_specified_delay = (int)get_option('bluesky_delay_duration', 1);
        $delay_duration = max(1, min(30, $user_specified_delay)) * 60;
        wp_schedule_single_event(time() + $delay_duration, 'bluesky_delayed_post_action', array($post_ID));
    } else {
        handle_delayed_post_action($post_ID);
    }
}

add_action('publish_post', 'post_to_bluesky');



add_action('bluesky_delayed_post_action', 'handle_delayed_post_action');

function handle_delayed_post_action($post_ID) {

    $bluesky_post = get_post_meta($post_ID, '_bluesky_post_checkbox', true);
    if ($bluesky_post !== 'yes') {
        return;
    }

    $temp_image = "";
    $token = authenticate_bluesky();
    if (!$token) {
        return;
    }

    $post = get_post($post_ID);
    $post_title = $post->post_title;
    $post_url = get_permalink($post_ID);

    $image_path = '';

    $post_image_url = get_the_post_thumbnail_url($post_ID, 'full');
    if ($post_image_url === false) {
        error_log("No featured image found for post ID $post_ID, checking for fallback image.");
        $fallback_image_url = get_option('bluesky_fallback_image');
        
        if ($fallback_image_url) {
            $post_image_url = $fallback_image_url;
        } else {
            error_log("No fallback image set, using placeholder.");
            $post_image_url = plugin_dir_url(__FILE__) . 'placeholder.png';
        }
    }

    if ($post_image_url) {
        if (strpos($post_image_url, site_url()) !== false) {
            $image_path = str_replace(site_url(), untrailingslashit(ABSPATH), $post_image_url);
        } else {
            $temp_image = download_url($post_image_url);
            if (!is_wp_error($temp_image)) {
                $image_path = $temp_image;
            } else {
                error_log("Error downloading image for post ID $post_ID: " . $temp_image->get_error_message());
                $image_path = plugin_dir_path(__FILE__) . 'placeholder.png';
            }
        }
    }
    $image_blob = '';
    if (file_exists($image_path)) {
        $image_content = file_get_contents($image_path);
        $image_mime_type = mime_content_type($image_path);

        if (strlen($image_content) <= 1000000) {
            $upload_response = wp_remote_post('https://bsky.social/xrpc/com.atproto.repo.uploadBlob', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => $image_mime_type,
                ],
                'body' => $image_content,
            ]);

            if (!is_wp_error($upload_response)) {
                $upload_body = json_decode(wp_remote_retrieve_body($upload_response), true);
                $image_blob = $upload_body['blob'] ?? '';
            }
        }

        if ($temp_image && !$use_placeholder) {
            unlink($temp_image);
        }
    }
    $embed_card = [
        '$type' => 'app.bsky.embed.external',
        'external' => [
            'uri' => $post_url,
            'title' => $post_title,
            'description' => '',
            'thumb' => $image_blob,
        ],
    ];

    $response = wp_remote_post('https://bsky.social/xrpc/com.atproto.repo.createRecord', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'repo' => get_option('bluesky_handle'),
            'collection' => 'app.bsky.feed.post',
            'record' => [
                'text' => $post_title,
                'createdAt' => current_time('Y-m-d\TH:i:s\Z'),
                'embed' => $embed_card,
            ],
        ]),
    ]);

    update_post_meta($post_ID, '_bluesky_post_checkbox', 'no');
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links');

function my_plugin_action_links($links) {
    $settings_link = '<a href="'. esc_url(get_admin_url(null, 'options-general.php?page=bluesky-integration/admin-page.php')) .'">Settings</a>';
    $support_link = '<a href="https://paypal.me/linusrath" target="_blank">Support this Plugin</a>';
    return $links;
}

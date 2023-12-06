<?php
/*
Plugin Name: Bluesky Social Integration
Description: Seamlessly Post Your WordPress Content on BlueSky
Version: 1.1
Author: Linus Rath
*/

// Admin page setup
include('admin-page.php');


function authenticate_bluesky() {
    $handle = get_option('bluesky_handle');
    $password = get_option('bluesky_password');
    
    $response = wp_remote_post('https://bsky.social/xrpc/com.atproto.server.createSession', [
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
    $post = get_post($post_ID);

    // Check if this is a revision or if the post is not published
    if (wp_is_post_revision($post_ID) || $post->post_status != 'publish') {
        return;
    }

    // Check if delay is enabled and get the delay duration
    $delay_enabled = get_option('bluesky_delay_enabled', '0'); // Default is '0' (disabled)
    $delay_duration = 10; // Default delay of 10 seconds

    if ($delay_enabled === '1') {
        // Get user-specified delay duration in minutes and convert to seconds
        $user_specified_delay = (int)get_option('bluesky_delay_duration', 1); // Default to 1 minute if not set
        $delay_duration = max(1, min(30, $user_specified_delay)) * 60; // Ensure the delay is between 1 and 30 minutes
    }

    // Schedule the event with the determined delay
    wp_schedule_single_event(time() + $delay_duration, 'bluesky_delayed_post_action', array($post_ID));

    // For debugging
    error_log("Scheduled post_to_bluesky with delay: " . $delay_duration . " seconds for post ID " . $post_ID);
}

add_action('publish_post', 'post_to_bluesky');


add_action('bluesky_delayed_post_action', 'handle_delayed_post_action');

function handle_delayed_post_action($post_ID) {
    $token = authenticate_bluesky();
    if (!$token) {
        return;
    }

    $post = get_post($post_ID);
    $post_title = $post->post_title;
    $post_url = get_permalink($post_ID);


    $temp_image = null;
    $use_placeholder = false;

    // Make sure the function download_url() is available
    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    // Get the URL of the post's featured image or use a placeholder
    $post_image_url = get_the_post_thumbnail_url($post_ID, 'full');
    if ($post_image_url === false) {
        error_log("No featured image found for post ID $post_ID, using placeholder.");
        $image_path = plugin_dir_path(__FILE__) . 'placeholder.png';
        $use_placeholder = true; 
    } else {
        error_log("Found featured image for post ID $post_ID: $post_image_url");
        $temp_image = download_url($post_image_url);
        if (is_wp_error($temp_image)) {
            error_log("Error downloading image for post ID $post_ID: " . $temp_image->get_error_message());
            $image_path = plugin_dir_path(__FILE__) . 'placeholder.png';
            $use_placeholder = true; // Set to true as fallback to the placeholder
        } else {
            $image_path = $temp_image;
        }
    }

    // Check and upload image
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

        // Clean up the temporary file
        if ($temp_image && !$use_placeholder) {
            unlink($temp_image);
        }
    }
    // Create and post embed card
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
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links');

function my_plugin_action_links($links) {
    $settings_link = '<a href="'. esc_url(get_admin_url(null, 'options-general.php?page=bluesky-integration/admin-page.php')) .'">Settings</a>';
    $support_link = '<a href="https://paypal.me/linusrath" target="_blank">Support this Plugin</a>';
    return $links;
}

<?php

function bluesky_plugin_menu() {
    add_menu_page('Bluesky Plugin Settings', 'Bluesky Settings', 'administrator', __FILE__, 'bluesky_plugin_settings_page' , 'dashicons-cloud');
}

add_action('admin_menu', 'bluesky_plugin_menu');

function bluesky_plugin_delay_settings() {
    register_setting('bluesky-plugin-settings-group', 'bluesky_delay_enabled');
    register_setting('bluesky-plugin-settings-group', 'bluesky_delay_duration');
}

add_action('admin_init', 'bluesky_plugin_delay_settings');

function bluesky_plugin_settings_page() {
    ?>
    <div class="wrap bluesky-settings-wrap">
        <h1>Bluesky Integration Settings</h1>
        <p>This plugin integrates your WordPress site with Bluesky. Set up your Bluesky account details and configure post publishing settings here.</p>
        
        <form method="post" action="options.php" class="bluesky-settings-form">
            <?php settings_fields('bluesky-plugin-settings-group'); ?>
            <?php do_settings_sections('bluesky-plugin-settings-group'); ?>
            
            <div class="bluesky-settings-field">
                <label for="bluesky_handle">Bluesky Handle</label>
                <input type="text" id="bluesky_handle" name="bluesky_handle" value="<?php echo esc_attr(get_option('bluesky_handle')); ?>" />
                <p class="description">Enter your Bluesky handle.</p>
            </div>
            
            <div class="bluesky-settings-field">
                <label for="bluesky_password">Bluesky App Password</label>
                <input type="password" id="bluesky_password" name="bluesky_password" value="<?php echo esc_attr(get_option('bluesky_password')); ?>" />
                <p class="description">Enter your Bluesky App Password. You can generate this password in the settings section of your Bluesky account.</p>
            </div>

            <div class="bluesky-settings-field">
        <label for="bluesky_fallback_image">Fallback Image</label>
        <input type="hidden" id="bluesky_fallback_image" name="bluesky_fallback_image" value="<?php echo esc_attr(get_option('bluesky_fallback_image')); ?>" />
        <button type="button" class="button" id="bluesky_fallback_image_button">Select Image</button>
        <div id="bluesky_fallback_image_preview" style="margin-top: 10px;">
            <?php if (get_option('bluesky_fallback_image')) : ?>
                <img src="<?php echo esc_url(get_option('bluesky_fallback_image')); ?>" style="max-width: 100px; max-height: 100px;" />
            <?php endif; ?>
        </div>
        <p class="description">Select an image to be used as the fallback when a post has no featured image.</p>
    </div>

    <script>
    jQuery(document).ready(function($){
        $('#bluesky_fallback_image_button').click(function(e) {
            e.preventDefault();

            var image_frame;
            if(image_frame){
                image_frame.open();
            }
            image_frame = wp.media({
                title: 'Select Media',
                multiple : false,
                library : {
                    type : 'image',
                }
            });

            image_frame.on('close',function() {
                var selection =  image_frame.state().get('selection').first().toJSON();
                $('#bluesky_fallback_image').val(selection.url);
                $('#bluesky_fallback_image_preview').html('<img src="'+selection.url+'" style="max-width: 100px; max-height: 100px;" />');
            });

            image_frame.on('open',function() {
                var selection =  image_frame.state().get('selection');
                var ids = $('#bluesky_fallback_image').val().split(',');
                ids.forEach(function(id) {
                    var attachment = wp.media.attachment(id);
                    attachment.fetch();
                    selection.add( attachment ? [ attachment ] : [] );
                });

            });

            image_frame.open();
        });
    });
    </script>


            <div class="bluesky-settings-field">
                <label for="bluesky_delay_enabled">Enable Delay</label>
                <input type="checkbox" id="bluesky_delay_enabled" name="bluesky_delay_enabled" value="1" <?php checked(1, get_option('bluesky_delay_enabled'), true); ?> />
                <p class="description">Enable this to delay the posting of new content to Bluesky. Useful for final edits after publishing..</p>
            </div>

            <div class="bluesky-settings-field">
                <label for="bluesky_delay_duration">Delay Duration (1-30 minutes)</label>
                <input type="number" id="bluesky_delay_duration" name="bluesky_delay_duration" value="<?php echo esc_attr(get_option('bluesky_delay_duration')); ?>" min="1" max="30" />
                <p class="description">Specify the delay duration in minutes. Only applicable if the delay is enabled.</p>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>

    <div class="bluesky-support-section">
        <h2>Support the Plugin Development</h2>
        <p>If you like this plugin and find it useful, consider supporting me:</p>
        
        <a href="https://paypal.me/linusrath" target="_blank" class="button button-primary">Support on PayPal</a>

        <br>
        <br>

        <h2>Contact or Feature Requests</h2>
        <p>Have a feature request or need assistance? Feel free to contact me:</p>
        <a href="mailto:info@linusrath.de">info@linusrath.de</a> or on Bluesky <a href="https://bsky.app/profile/linusrath.bsky.social">linusrath.bsky.social</a>
    </div>
    <?php

    wp_enqueue_media();
}

add_action('admin_head', 'bluesky_admin_styles');

function bluesky_admin_styles() {
    ?>
    <style>
        .bluesky-settings-wrap {
            max-width: 800px;
            margin-top: 20px;
        }


        .bluesky-settings-field {
            margin-bottom: 20px;
        }

        .bluesky-settings-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .bluesky-settings-field input[type="text"],
        .bluesky-settings-field input[type="password"],
        .bluesky-settings-field input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .bluesky-settings-field input[type="checkbox"] {
            margin-top: 5px;
        }

        .bluesky-settings-field .description {
            margin-top: 5px;
            font-style: italic;
            color: #666;
        }

        .bluesky-settings-wrap, .bluesky-support-section {
            max-width: 800px;
            margin-top: 20px;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }

        .bluesky-support-section h2 {
            margin-top: 0;
        }

        .bluesky-support-section a.button-primary:hover {
            background: #006799;
            border-color: #006799;
        }

        .bluesky-support-section a {
            text-decoration: none;
        }
    </style>
    <?php
}

add_action('admin_head', 'bluesky_admin_styles');

function bluesky_plugin_settings() {
    register_setting('bluesky-plugin-settings-group', 'bluesky_handle');
    register_setting('bluesky-plugin-settings-group', 'bluesky_password');
    register_setting('bluesky-plugin-settings-group', 'bluesky_fallback_image');
}

add_action('admin_init', 'bluesky_plugin_settings');

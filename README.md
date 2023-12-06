
# Bluesky Integration for WordPress: Tutorial

## Introduction

Welcome to the Bluesky Integration plugin for WordPress! This plugin is designed to seamlessly connect your WordPress site with Bluesky, a social networking platform. With this Integration, you can automatically post your WordPress content directly to Bluesky.

When you publish a new post on WordPress, the plugin shares it on your Bluesky feed. It also supports delayed posting, which means you can schedule your posts to appear on Bluesky at a later time. This is especially useful for final edits.

## Setting Up Real Cron Jobs for Reliable Scheduling

By default, WordPress uses a system known as WP-Cron to handle scheduling tasks. WP-Cron relies on site visits to trigger scheduled events. For sites with low traffic, this might lead to delays in executing scheduled tasks. To ensure reliable and timely execution, you can set up a real cron job on your server.

### Step 1: Disable WP-Cron

First, disable the built-in WP-Cron system by adding the following line to your `wp-config.php` file:

```php
define('DISABLE_WP_CRON', true);
```

### Step 2: Create a Real Cron Job

Access your server's crontab (usually in a Unix/Linux environment) and set up a real cron job to trigger WordPress's cron system.

1. **Open Crontab**: Access your server's command line and open crontab:

   ```
   crontab -e
   ```

2. **Add Cron Job**: Add the following line to run the cron job every minute:

   ```
   * * * * * wget -q -O - http://yourdomain.com/wp-cron.php >/dev/null 2>&1
   ```

Replace `http://yourdomain.com` with your actual domain name.

### Step 3: Verify

After setting up the cron job, monitor your scheduled tasks to ensure they are executing as expected.

## Using Bluesky Integration Plugin

After setting up real cron jobs, you can start using the Bluesky Integration plugin to its full potential.

### Automatic Posting

- When you publish a post on WordPress, it will automatically be shared on your Bluesky feed.
- Featured images and post titles are included for visually appealing posts.

### Delayed Posting

- Delayed posting allows you to schedule your WordPress posts to appear on Bluesky at a later time.
- To enable delayed posting, go to the plugin's settings in your WordPress admin panel and set the delay duration.

## Thanks for using this Plugin
For any questions, feature requests, or support, feel free to contact us at info@linusrath.de. If you like this plugin, consider supporting its development on [PayPal](https://paypal.me/linusrath). 

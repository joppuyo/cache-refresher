<?php

/*
 * Plugin Name: Cache Refresher
 * Description: A plugin to refresh your cache periodically or on demand
 * Version: 0.0.1
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

function cache_refresher_run()
{
    // For some reason `page` is not `publicly_queryable` ? So we need to do this explicitly.
    $default_post_types = ['post', 'page', 'attachment'];

    // This takes into account any custom post types
    $public_post_types = get_post_types(['publicly_queryable' => true]);
    $public_post_types = array_keys($public_post_types);

    $public_post_types = array_merge($default_post_types, $public_post_types);

    // Most people probably don't want media pages indexed and it will just slow down the process
    // on sites with a lot of media
    $public_post_types = array_diff($public_post_types, ['attachment']);

    $public_post_types = array_unique($public_post_types);

    // Allow user to filter the values
    $public_post_types = apply_filters('cache_refresher/post_types', $public_post_types);

    $query = new WP_Query([
        'post_type' => $public_post_types,
        'posts_per_page' => -1,
        'fields' => 'ids',
        'post_status' => ['publish', 'inherit']
    ]);

    $posts = $query->posts;

    $count = count($posts);

    cache_refresher_wp_cli_echo("Found $count posts to refresh.");

    foreach ($posts as $post) {
        $url = get_permalink($post);
        $next_action = as_next_scheduled_action('cache_refresher_process_post', [$post, $url]);
        if ($next_action === false) {
            as_enqueue_async_action('cache_refresher_process_post', [$post, $url]);
            cache_refresher_wp_cli_echo("Added $post ($url) to refresh queue");
        } else {
            cache_refresher_wp_cli_echo("Post $post ($url) is already queued.");
        }
    }
    cache_refresher_wp_cli_echo("All $count posts have been successfully processed");
}

function cache_refresher_wp_cli_echo($line) {
    if (class_exists('WP_CLI')) {
        WP_CLI::line($line);
    }
}

function cache_refresher_init()
{
    $cron_name = apply_filters('cache_refresher_cron_name', 'daily');
    add_action('cache_refresher_run_cron', 'cache_refresher_run');
    if (!wp_next_scheduled('cache_refresher_run_cron')) {
        $offset = current_time('timestamp', false) - time();
        $timestamp = strtotime('00:00:01') - $offset;
        wp_schedule_event($timestamp, $cron_name, 'cache_refresher_run_cron');
    }
}

function cache_refresher_process_post_function($post_id)
{
    $url = get_permalink($post_id);
    wp_remote_get($url);
}

add_action('cache_refresher_process_post', 'cache_refresher_process_post_function', 10, 2);

add_action('init', 'cache_refresher_init');

function cache_refresher_register_commands() {
    WP_CLI::add_command( 'cache-refresher refresh-all', 'cache_refresher_run' );
}

add_action('cli_init', 'cache_refresher_register_commands');

<?php

namespace NPX\CacheRefresher;

use WP_Query;
use WP_CLI;

class Plugin
{
    private static $instance;
    public $post_types;

    public static function get_instance(): Plugin
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    private function __construct()
    {
        add_action('init', [$this, 'cache_refresher_init'], 9);
        add_action('init', [$this, 'init_cron']);

        add_action('cache_refresher_process_post', [$this, 'cache_refresher_process_post_function'], 10, 2);

        add_action('save_post', [$this, 'refresh_post_on_save']);
        add_action('delete_post', [$this, 'refresh_post_on_save']);
        //add_action('update_post_meta', [$this, 'refresh_post_on_save'], 10, 4);

        Rest::get_instance();
        CLI::get_instance();
        Admin::get_instance();
    }

    function init_cron() {
        $cron_name = apply_filters('cache_refresher_cron_name', 'daily');
        add_action('cache_refresher_run_cron', [$this, 'cache_refresher_run']);
        if (!wp_next_scheduled('cache_refresher_run_cron')) {
            $offset = current_time('timestamp', false) - time();
            $timestamp = strtotime('00:00:01') - $offset;
            wp_schedule_event($timestamp, $cron_name, 'cache_refresher_run_cron');
        }
    }

    function cache_refresher_init()
    {
        $this->post_types = $this->get_post_types();

        error_log("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    }

    function cache_refresher_process_post_function($post_id)
    {
        $url = get_permalink($post_id);
        wp_remote_get($url);
    }

    function refresh_post_async($post_id)
    {
        $url = get_permalink($post_id);

        // This is based on how WordPress does it
        // https://github.com/WordPress/WordPress/blob/7f5d7f1b56087c3eb718da4bd81deb06e077bbbb/wp-includes/cron.php#L917
        $arguments = [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        ];

        wp_remote_get($url, $arguments);
    }

    function cache_refresher_wp_cli_echo($line) {
        if (class_exists('WP_CLI')) {
            WP_CLI::line($line);
        }
    }

    function get_post_types() {
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

        return $public_post_types;
    }

    function cache_refresher_run()
    {
        $public_post_types = $this->get_post_types();

        $query = new WP_Query([
                                  'post_type' => $public_post_types,
                                  'posts_per_page' => -1,
                                  'fields' => 'ids',
                                  'post_status' => ['publish', 'inherit']
                              ]);

        $posts = $query->posts;

        $count = count($posts);

        $this->cache_refresher_wp_cli_echo("Found $count posts to refresh.");

        foreach ($posts as $post) {
            $url = get_permalink($post);
            $next_action = as_next_scheduled_action('cache_refresher_process_post', [$post, $url]);
            if ($next_action === false) {
                as_enqueue_async_action('cache_refresher_process_post', [$post, $url]);
                $this->cache_refresher_wp_cli_echo("Added $post ($url) to refresh queue");
            } else {
                $this->cache_refresher_wp_cli_echo("Post $post ($url) is already queued.");
            }
        }
        $this->cache_refresher_wp_cli_echo("All $count posts have been successfully processed");
    }

    function random_string($length) {
        $random_string = '';
        for($i = 0; $i < $length; $i++) {
            $number = random_int(0, 36);
            $character = base_convert($number, 10, 36);
            $random_string = $random_string . $character;
        }

        return $random_string;
    }

    function get_key() {
        if (!get_option('cache_refresher_key')) {
            update_option('cache_refresher_key', $this->random_string(25));
        }
        return get_option('cache_refresher_key');
    }

    function refresh_post_on_save($post_id) {
        // Don't refresh post types we don't want
        if (!in_array(get_post_type($post_id), $this->post_types)) {
            return;
        }

        // Do async request
        $this->refresh_post_async($post_id);
    }

}

<?php

namespace NPX\CacheRefresher;

use WP_REST_Request;

class Rest
{
    private static $instance;

    public static function get_instance(): Rest
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
        add_action('rest_api_init', [$this, 'rest_api_init']);
    }

    public function rest_api_init()
    {
        register_rest_route(
            'cache-refresher/v1',
            '/refresh-all',
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_api_refresh_all'],
                'permission_callback' => function () {
                    return true;
                },
            ]
        );
    }

    public function rest_api_refresh_all(WP_REST_Request $request)
    {
        $token = $request->get_param('token');

        if (!$token) {
            return new \WP_REST_Response(
                [
                    'status' => 'error',
                    'message' => 'token_missing'
                ], 400
            );
        }

        $plugin = Plugin::get_instance();

        $database_token = $plugin->get_key();

        if (!hash_equals($database_token, $token)) {
            return new \WP_REST_Response(
                [
                    'status' => 'error',
                    'message' => 'incorrect_token'
                ], 403
            );
        }

        $plugin->cache_refresher_run();

        return new \WP_REST_Response(['status' => 'success']);
    }
}

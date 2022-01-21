<?php

namespace NPX\CacheRefresher;

class CLI
{
    private static $instance;

    public static function get_instance(): CLI
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
        add_action('cli_init', [$this, 'cache_refresher_register_commands']);
    }

    function cache_refresher_register_commands() {
        $plugin = Plugin::get_instance();
        WP_CLI::add_command( 'cache-refresher refresh-all', [$plugin, 'cache_refresher_run'] );
    }

}

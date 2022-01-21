<?php

namespace NPX\CacheRefresher;

use NPX\CacheRefresher\Plugin;

class Admin
{
    private static $instance;

    public static function get_instance(): Admin
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
        add_action('admin_init', [$this, 'settings_api_init']);
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    function settings_api_init() {
        add_settings_section(
            'cache_refresher_section',
            'General settings',
            [$this, 'setting_section_callback_function'],
            'cache_refresher'
        );

        add_settings_field(
            'cache_refresher_refresh_all_url',
            'Refresh All URL',
            [$this, 'refresh_all_url_callback'],
            'cache_refresher',
            'cache_refresher_section'
        );
    }

    function refresh_all_url_callback() {

        $plugin = Plugin::get_instance();

        $query = build_query(['token' => $plugin->get_key()]);

        $url = rest_url('cache-refresher/v1/refresh-all') . '?' . $query;

        echo '<input readonly style="width:100%" type="text" value="' . esc_html($url) .'" />';
    }

    function admin_menu() {
        add_options_page(
            "Cache Refresher",
            "Cache Refresher",
            'manage_options',
            'cache_refresher',
            [$this, 'settings_page']
        );
    }

    function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' .  esc_html( get_admin_page_title()) . '</h1>';
        echo '<form action="options.php" method="post">';
        settings_fields( 'cache_refresher' );
        do_settings_sections( 'cache_refresher' );
        submit_button( 'Save Settings' );
        echo '     </form>';
        echo '</div>';

    }

    function setting_section_callback_function()
    {
        echo '<p>General settings for the plugin.</p>';
    }

}

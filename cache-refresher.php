<?php

/*
 * Plugin Name: Cache Refresher
 * Description: A plugin to refresh your cache periodically or on demand
 * Version: 0.0.2
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

NPX\CacheRefresher\Plugin::get_instance();
# Cache Refresher

This is a WordPress plugin that allows you to refresh your cache periodically or on-demand.

It's mostly useful when used in conjunction with [microcaching](https://siipo.la/blog/never-miss-the-cache-with-nginx-microcaching) or for warming your cache after a deployment.

The plugin works in the background using a queue so it will refresh one page at the time and thus will hopefully avoid overloading your server.

## On demand

This allows you to refresh every page on your site. There are two methods you can use

### HTTP request

You can refresh every page on your site by sending a GET request to an URL. You can find this URL under Options and Cache Refresher. It looks something like this:

```
https://example.com/wp-json/cache-refresher/v1/refresh-all?token=gnjvb5th13ken07nmwov9gqnt
```

You can ping it using your CI pipeline or something. I may add a button to the UI to do this also.

### WP CLI

Run the following command

```
wp cache-refresher refresh-all
```

## Scheduled refreshing

This plugin will refresh all pages on your site every day at 00:01 in the site time zone. I will maybe add some option to disable or customize this in the future.

## Refresh on save

When you save a post, the plugin will ping the permalink of that post. I will maybe add some option to disable or customize this in the future.

# WP GitHub Plugin Updater

[![Build Status](https://travis-ci.com/inc2734/wp-github-plugin-updater.svg?branch=master)](https://travis-ci.com/inc2734/wp-github-plugin-updater)
[![Latest Stable Version](https://poser.pugx.org/inc2734/wp-github-plugin-updater/v/stable)](https://packagist.org/packages/inc2734/wp-github-plugin-updater)
[![License](https://poser.pugx.org/inc2734/wp-github-plugin-updater/license)](https://packagist.org/packages/inc2734/wp-github-plugin-updater)

## Install
```
$ composer require inc2734/wp-github-plugin-updater
```

## How to use
### Basic
```
<?php
$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
  plugin_basename( __FILE__ ),
  'user-name',
  'repository'
);
```

### Advanced
```
<?php
$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
  plugin_basename( __FILE__ ),
  'user-name',
  'repository',
  [
    'description_url'  => '', // URL for description tab content
    'installation_url' => '', // URL for installation tab content
    'faq_url'          => '', // URL for FAQ tab content
    'changelog_url'    => '', // URL for changelog tab content
    'screenshots_url'  => '', // URL for screenshots tab content
    'icons' => [
      'svg' => '', // svg URL. Square recommended
      '1x'  => '', // Image URL 64×64
      '2x'  => '', // Image URL 128×128
    ],
    'banners' => [
      'low'  => '', // Image URL 772×250
      'high' => '', // Image URL 1554×500
    ],
    'tested'       => '5.2.2', // Tested up WordPress version
    'requires_php' => '5.6.0', // Requires PHP version
    'requires'     => '5.0.0', // Requires WordPress version
  ]
);
```

## Filter hooks
### inc2734_github_plugin_updater_zip_url_<$user_name>/<$repository>

Customize downloaded package url.

```
add_filter(
  'inc2734_github_plugin_updater_zip_url_inc2734/snow-monkey-blocks',
  function( $url, $user_name, $repository, $tag_name ) {
    return $url;
  },
  10,
  4
);
```

### inc2734_github_plugin_updater_request_url_<$user_name>/<$repository>

Customize requested api url.

```
add_filter(
  'inc2734_github_plugin_updater_request_url_inc2734/snow-monkey-blocks',
  function( $url, $user_name, $repository ) {
    return $url;
  },
  10,
  3
);
```

### inc2734_github_plugin_updater_plugins_api_<$user_name>/<$repository>

Customize fields of `plugins_api`.

```
add_filter(
  'inc2734_github_plugin_updater_plugins_api_inc2734/snow-monkey-blocks',
  function( $obj, $response ) {
    return $obj;
  }
);
```

### inc2734_github_plugin_updater_repository_content_url_<$user_name>/<$repository>

Customize contents api url.

```
add_filter(
  'inc2734_github_plugin_updater_repository_content_url_inc2734/snow-monkey-blocks',
  function( $url, $user_name, $repository, $plugin_name ) {
    return $url;
  },
  10,
  4
);
```

### inc2734_github_plugin_updater_repository_content_headers_<$user_name>/<$repository>

Customize fields contents_api.

```
add_filter(
  'inc2734_github_plugin_updater_repository_content_headers_inc2734/snow-monkey-blocks',
  function( $headers ) {
    return $headers;
  }
);
```

### inc2734_github_plugin_updater_contributors_url_<$user_name>/<$repository>

Customize contributors api url.

```
add_filter(
  'inc2734_github_plugin_updater_contributors_url_inc2734/snow-monkey-blocks',
  function( $url, $user_name, $repository ) {
    return $url;
  },
  10,
  4
);
```

### inc2734_github_plugin_updater_zip_url

**Obsolete from v2.0.0**

Customize downloaded package url.

```
add_filter(
  'inc2734_github_plugin_updater_zip_url',
  function( $url, $user_name, $repository, $tag_name ) {
    if ( 'inc2734' === $user_name && 'snow-monkey-blocks' === $repository ) {
      return 'https://example.com/my-custom-updater-zip-url';
    }
    return $url;
  },
  10,
  4
);
```

### inc2734_github_plugin_updater_request_url

**Obsolete from v2.0.0**

Customize requested api url.

```
add_filter(
  'inc2734_github_plugin_updater_request_url',
  function( $url, $user_name, $repository ) {
    if ( 'inc2734' === $user_name && 'snow-monkey-blocks' === $repository ) {
      return 'https://example.com/my-custom-updater-request-url';
    }
    return $url;
  },
  10,
  3
);
```

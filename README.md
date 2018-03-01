# WP GitHub Plugin Updater

[![Build Status](https://travis-ci.org/inc2734/wp-github-plugin-updater.svg?branch=master)](https://travis-ci.org/inc2734/wp-github-plugin-updater)
[![Latest Stable Version](https://poser.pugx.org/inc2734/wp-github-plugin-updater/v/stable)](https://packagist.org/packages/inc2734/wp-github-plugin-updater)
[![License](https://poser.pugx.org/inc2734/wp-github-plugin-updater/license)](https://packagist.org/packages/inc2734/wp-github-plugin-updater)

## Install
```
$ composer require inc2734/wp-github-plugin-updater
```

## How to use
```
<?php
// When Using composer auto loader
$updater = new Inc2734\WP_GitHub_Plugin_Updater\GitHub_Plugin_Updater( plugin_basename( __FILE__ ), 'user-name', 'repository' );
```

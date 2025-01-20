<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

class Requester {

	/**
	 * Performs an HTTP request using the GET method and returns its response.
	 *
	 * @param string $url URL to retrieve.
	 * @param string $user_name GitHub user name.
	 * @param string $repository GitHub repository name.
	 * @return array|WP_Error
	 */
	public static function request( $url, $user_name, $repository ) {
		global $wp_version;

		$args = apply_filters(
			'inc2734_github_plugin_updater_requester_args',
			array(
				'user-agent' => 'WordPress/' . $wp_version,
				'timeout'    => 30,
				'headers'    => array(
					'Accept-Encoding' => '',
				),
			),
			$url,
			$user_name,
			$repository
		);

		return wp_remote_get( $url, $args );
	}
}

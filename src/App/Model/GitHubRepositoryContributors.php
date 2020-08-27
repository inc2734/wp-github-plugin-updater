<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use Inc2734\WP_GitHub_Plugin_Updater\App\Model\Requester;

class GitHubRepositoryContributors {

	protected $plugin_name;

	protected $user_name;

	protected $repository;

	protected $transient_name;

	public function __construct( $plugin_name, $user_name, $repository ) {
		$this->plugin_name = $plugin_name;
		$this->user_name   = $user_name;
		$this->repository  = $repository;
		$this->transient_name = sprintf( 'wp_github_plugin_updater_repository_contributors_data_%1$s', $this->plugin_name );
	}

	public function get() {
		$transient = get_transient( $this->transient_name );
		if ( false !== $transient ) {
			return $transient;
		}

		$response = $this->_request();
		$response = $this->_retrieve( $response );

		$contributors = [];

		if ( null !== $response ) {
			foreach ( $response as $contributor ) {
				$contributors[] = [
					'display_name' => $contributor->login,
					'avatar' => $contributor->avatar_url,
					'profile' => $contributor->html_url,
				];
			}
		}

		set_transient( $this->transient_name, $contributors, 0 );

		return $contributors;
	}

	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	protected function _retrieve( $response ) {
		global $pagenow;

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( '' === $response_code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 == $response_code ) {
			return $body;
		}

		$message = null !== $body && property_exists( $body, 'message' )
			? $body->message
			: __( 'Failed to get update response.', 'inc2734-wp-github-plugin-updater' );

		$error_message = sprintf(
			/* Translators: 1: Plugin name, 2: Error message  */
			__( '[%1$s] %2$s', 'inc2734-wp-github-plugin-updater' ),
			$this->plugin_name,
			$message
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Inc2734_WP_GitHub_Plugin_Updater error. [' . $response_code . '] ' . $error_message );
		}

		if ( ! in_array( $pagenow, [ 'update-core.php', 'plugins.php' ] ) ) {
			return null;
		}

		return new WP_Error(
			$response_code,
			$error_message
		);
	}

	protected function _request() {
		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/contributors',
			$this->user_name,
			$this->repository
		);

		$url = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_contributors_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository
		);

		return Requester::request( $url );
	}
}

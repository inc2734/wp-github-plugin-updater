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

		set_transient( $this->transient_name, $response, 0 );

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

		return $contributors;
	}

	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	protected function _retrieve( $response ) {
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! isset( $body->content ) ) {
			return null;
		}

		return base64_decode( $body->content );
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

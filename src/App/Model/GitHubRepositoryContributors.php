<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use WP_Error;
use Inc2734\WP_GitHub_Plugin_Updater\App\Model\Requester;

class GitHubRepositoryContributors {

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * GitHub user name.
	 *
	 * @var string
	 */
	protected $user_name;

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	protected $repository;

	/**
	 * Transient name.
	 *
	 * @var string
	 */
	protected $transient_name;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin basename.
	 * @param string $user_name GitHub user name.
	 * @param string $repository GitHub repository name.
	 */
	public function __construct( $plugin_name, $user_name, $repository ) {
		$this->plugin_name    = $plugin_name;
		$this->user_name      = $user_name;
		$this->repository     = $repository;
		$this->transient_name = sprintf( 'wp_github_plugin_updater_repository_contributors_data_%1$s', $this->plugin_name );
	}

	/**
	 * Get contributors.
	 *
	 * @return array|WP_Error
	 */
	public function get() {
		$transient = get_transient( $this->transient_name );
		if ( false !== $transient ) {
			return $transient;
		}

		$response = $this->_request();
		$response = $this->_retrieve( $response );

		$contributors = array();

		// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedElse
		if ( ! is_wp_error( $response ) && is_array( $response ) ) {
			foreach ( $response as $contributor ) {
				$contributors[] = array(
					'display_name' => $contributor->login,
					'avatar'       => $contributor->avatar_url,
					'profile'      => $contributor->html_url,
				);
			}

			set_transient( $this->transient_name, $contributors, 0 );
		} else {
			// @todo Since it is not essential for updating, the cache is not erased by the GItHub API rate limitation measures.
			// $this->delete_transient();
		}
		// phpcs:enable

		return $contributors;
	}

	/**
	 * Delete transient.
	 */
	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	/**
	 * Retrieve only the body from the raw response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return array|null|WP_Error
	 */
	protected function _retrieve( $response ) {
		global $pagenow;

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_code = $response_code ? $response_code : 503;
		if ( 200 !== (int) $response_code ) {
			return new WP_Error(
				$response_code,
				sprintf(
					'[%1$s] Failed to get GitHub repository contributors. HTTP status is "%2$s"',
					$this->plugin_name,
					$response_code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $body ) ) {
			return new WP_Error(
				$response_code,
				sprintf(
					'[%1$s] Failed to get GitHub repository contributors',
					$this->plugin_name
				)
			);
		}

		if ( ! in_array( $pagenow, array( 'update-core.php', 'plugins.php' ), true ) ) {
			return null;
		}

		return $body;
	}

	/**
	 * Request to GitHub contributors API.
	 *
	 * @return array|WP_Error
	 */
	protected function _request() {
		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/contributors',
			$this->user_name,
			$this->repository
		);

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
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
		// phpcs:enable

		return Requester::request( $url );
	}
}

<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use WP_Error;
use Inc2734\WP_GitHub_Plugin_Updater\App\Model\Requester;

class GitHubReleases {

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
		$this->transient_name = sprintf( 'wp_github_plugin_updater_%1$s', $this->plugin_name );
	}

	/**
	 * Get response of GitHub API.
	 *
	 * @param string|null $version Version.
	 * @return stdClass|WP_Error
	 */
	public function get( $version = null ) {
		$transient = get_transient( $this->transient_name );
		if ( is_object( $transient ) ) {
			$transient = (array) $transient;
		}
		if ( ! is_array( $transient ) ) {
			$transient = array();
		}

		if ( ! $version && ! empty( $transient['latest'] ) ) {
			return $transient['latest'];
		} elseif ( ! empty( $transient[ $version ] ) ) {
			return $transient[ $version ];
		}

		$response = $this->_request( $version );
		$response = $this->_retrieve( $response );

		if ( ! is_wp_error( $response ) ) {
			if ( ! $version ) {
				$transient['latest'] = $response;
			} else {
				$transient[ $version ] = $response;
			}
			set_transient( $this->transient_name, $transient, 60 * 5 );
		} else {
			$this->delete_transient();
		}

		return $response;
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
	 * @return stdClass|null|WP_Error
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
					'[%1$s] Failed to get update response. HTTP status is "%2$s"',
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
					'[%1$s] Failed to get update response.',
					$this->plugin_name
				)
			);
		}

		$body->package = ! empty( $body->tag_name )
			? $this->_get_zip_url( $body )
			: false;

		return $body;
	}

	/**
	 * Request to GitHub contributors API.
	 *
	 * @param string|null $version Version.
	 * @return array|WP_Error
	 */
	protected function _request( $version = null ) {
		$url = ! $version
			? sprintf(
				'https://api.github.com/repos/%1$s/%2$s/releases/latest',
				$this->user_name,
				$this->repository
			)
			: sprintf(
				'https://api.github.com/repos/%1$s/%2$s/releases/tags/%3$s',
				$this->user_name,
				$this->repository,
				$version
			);

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$url = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_request_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			$version
		);
		// phpcs:enable

		return Requester::request( $url );
	}

	/**
	 * Get remote zip URL.
	 *
	 * @throws \RuntimeException Invalid zip URL.
	 *
	 * @param stdClass $response Responser of GitHub API.
	 * @return string|false
	 */
	protected function _get_zip_url( $response ) {
		$url = false;

		if ( ! empty( $response->assets ) && is_array( $response->assets ) ) {
			if ( ! empty( $response->assets[0] ) && is_object( $response->assets[0] ) ) {
				if ( ! empty( $response->assets[0]->browser_download_url ) ) {
					$url = $response->assets[0]->browser_download_url;
				}
			}
		}

		$tag_name = isset( $response->tag_name ) ? $response->tag_name : null;

		if ( ! $url && $tag_name ) {
			$url = sprintf(
				'https://github.com/%1$s/%2$s/releases/download/%3$s/%2$s.zip',
				$this->user_name,
				$this->repository,
				$tag_name
			);

			$http_status_code = $this->_get_http_status_code( $url );
			if ( ! in_array( $http_status_code, array( 200, 302 ), true ) ) {
				$url = sprintf(
					'https://github.com/%1$s/%2$s/archive/%3$s.zip',
					$this->user_name,
					$this->repository,
					$tag_name
				);
			}
		}

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$url = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_zip_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			$tag_name
		);
		// phpcs:enable

		try {
			if ( ! $url ) {
				throw new \RuntimeException(
					sprintf(
						'[%1$s] Can\'t find zip URL for update.',
						$this->plugin_name
					)
				);
			}

			$http_status_code = $this->_get_http_status_code( $url );
			if ( ! in_array( (int) $http_status_code, array( 200, 302 ), true ) ) {
				throw new \RuntimeException(
					sprintf(
						'[%1$s] Can\'t find zip URL for update. HTTP status code is "%2$s"',
						$this->plugin_name,
						$http_status_code
					)
				);
			}
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		return $url;
	}

	/**
	 * Return http status code from $url.
	 *
	 * @param string $url Target url.
	 * @return int
	 */
	protected function _get_http_status_code( $url ) {
		$response = Requester::request( $url );
		return wp_remote_retrieve_response_code( $response );
	}
}

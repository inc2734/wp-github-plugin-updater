<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use Inc2734\WP_GitHub_Plugin_Updater\App\Model\Requester;

class GitHubRepositoryContent {

	protected $plugin_name;

	protected $user_name;

	protected $repository;

	protected $transient_name;

	public function __construct( $plugin_name, $user_name, $repository ) {
		$this->plugin_name = $plugin_name;
		$this->user_name   = $user_name;
		$this->repository  = $repository;
		$this->transient_name = sprintf( 'wp_github_plugin_updater_repository_data_%1$s', $this->plugin_name );
	}

	public function get() {
		$transient = get_transient( $this->transient_name );
		if ( false !== $transient ) {
			return $transient;
		}

		$response = $this->_request();
		$response = $this->_retrieve( $response );

		set_transient( $this->transient_name, $response, 0 );
		return $response;
	}

	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	/**
	 * @see https://developer.wordpress.org/reference/functions/get_file_data/
	 * @see https://developer.wordpress.org/reference/functions/get_plugin_data/
	 */
	public function get_headers() {
		$headers = [];

		$content = $this->get();

		$target_headers = [
			'RequiresWP'   => 'Requires at least',
			'RequiresPHP'  => 'Requires PHP',
			'Tested up to' => 'Tested up to',
		];

		if ( null !== $content ) {
			$content = substr( $content, 0, 8 * KB_IN_BYTES );
			$content = str_replace( "\r", "\n", $content );
		}

		foreach ( $target_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $content, $match ) && $match[1] ) {
				$headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$headers[ $field ] = '';
			}
		}

		return apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_repository_content_headers_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$headers
		);
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
			'https://api.github.com/repos/%1$s/%2$s/contents/%3$s',
			$this->user_name,
			$this->repository,
			basename( $this->plugin_name )
		);

		$url = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_repository_content_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			basename( $this->plugin_name )
		);

		return Requester::request( $url );
	}
}

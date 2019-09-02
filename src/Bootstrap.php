<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater;

use WP_Error;
use stdClass;
use Parsedown;
use Inc2734\WP_GitHub_Plugin_Updater\App\Model\Fields;

class Bootstrap {

	/**
	 * The plugin name
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * GitHub user name
	 *
	 * @var string
	 */
	protected $user_name;

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	protected $repository;

	/**
	 * Plugin data fields
	 *
	 * @var Fields
	 */
	protected $fields;

	/**
	 * @param string $plugin_name
	 * @param string $user_name
	 * @param string $repository
	 * @param array $fields Plugin data fields
	 */
	public function __construct( $plugin_name, $user_name, $repository, array $fields = [] ) {
		$this->plugin_name = $plugin_name;
		$this->user_name   = $user_name;
		$this->repository  = $repository;
		$this->fields      = new Fields( $fields );

		$upgrader = new App\Model\Upgrader( $plugin_name );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, '_pre_set_site_transient_update_plugins' ] );
		add_filter( 'upgrader_pre_install', [ $upgrader, 'pre_install' ], 10, 2 );
		add_filter( 'upgrader_source_selection', [ $upgrader, 'source_selection' ], 10, 4 );
		add_filter( 'plugins_api', [ $this, '_plugins_api' ], 10, 3 );
	}

	/**
	 * Overwirte site_transient_update_plugins from GitHub API
	 *
	 * @param false|array $transient
	 * @return false|array
	 */
	public function _pre_set_site_transient_update_plugins( $transient ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $this->plugin_name ) ) {
			return $transient;
		}

		$current  = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );
		$api_data = $this->_get_transient_api_data();

		if ( is_wp_error( $api_data ) ) {
			$this->_set_notice_error_about_github_api();
			return $transient;
		}

		if ( ! isset( $api_data->tag_name ) ) {
			return $transient;
		}

		if ( ! $this->_should_update( $current['Version'], $api_data->tag_name ) ) {
			return $transient;
		}

		$package = $this->_get_zip_url( $api_data );
		$http_status_code = $this->_get_http_status_code( $package );
		if ( ! $package || ! in_array( $http_status_code, [ 200, 302 ] ) ) {
			error_log( 'Inc2734_WP_GitHub_Plugin_Updater error. zip url not found. ' . $http_status_code . ' ' . $package );
			return $transient;
		}
		$transient_response = [
			'slug'        => $this->plugin_name,
			'plugin'      => $this->plugin_name,
			'new_version' => $api_data->tag_name,
			'url'         => $this->fields->get( 'homepage' ),
			'package'     => $package,
			'tested'      => $this->fields->get( 'tested' ),
			'icons'       => $this->fields->get( 'icons' ),
		];
		$transient_response = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_transient_response_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$transient_response
		);
		$transient->response[ $this->plugin_name ] = (object) $transient_response;

		return $transient;
	}

	/**
	 * Filters the object of the plugin which need to be upgraded.
	 *
	 * @param object $obj The object of the plugins-api.
	 * @param string $action The type of information being requested from the Plugin Install API.
	 * @param object $arg The arguments for the plugins-api.
	 * @return object The object of the plugins-api which is gotten from GitHub API.
	 */
	public function _plugins_api( $obj, $action, $arg ) {
		if ( 'query_plugins' !== $action && 'plugin_information' !== $action ) {
			return $obj;
		}

		if ( ! isset( $arg->slug ) || $arg->slug !== $this->plugin_name ) {
			return $obj;
		}

		$api_data = $this->_get_transient_api_data();
		if ( is_wp_error( $api_data ) ) {
			return $obj;
		}

		$current = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );
		$description_url = $this->fields->get( 'description_url' ) ? $this->fields->get( 'description_url' ) : WP_PLUGIN_DIR . '/' . dirname( $this->plugin_name ) . '/README.md';

		$sessions  = [
			'description'  => $this->_get_content_text( $description_url ),
			'installation' => $this->_get_content_text( $this->fields->get( 'installation_url' ) ),
			'faq'          => $this->_get_content_text( $this->fields->get( 'faq_url' ) ),
			'changelog'    => $this->_get_content_text( $this->fields->get( 'changelog_url' ) ),
			'screenshots'  => $this->_get_content_text( $this->fields->get( 'screenshots_url' ) ),
		];

		$obj               = new stdClass();
		$obj->slug         = $this->plugin_name;
		$obj->name         = esc_html( $current['Name'] );
		$obj->plugin_name  = esc_html( $current['Name'] );
		$obj->author       = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( $api_data->author->html_url ), esc_html( $api_data->author->login ) );
		$obj->version      = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', $api_data->html_url, $api_data->tag_name );
		$obj->last_updated = $api_data->published_at;
		$obj->sections     = $sessions;
		$obj->banners      = $this->fields->get( 'banners' );
		$obj->tested       = $this->fields->get( 'tested' );
		$obj->requires_php = $this->fields->get( 'requires_php' );
		$obj->requires     = $this->fields->get( 'requires' );

		$obj = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_plugins_api_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$obj
		);

		return $obj;
	}

	/**
	 * Set notice error about GitHub API using admin_notice hook
	 *
	 * @return void
	 */
	protected function _set_notice_error_about_github_api() {
		add_action(
			'admin_notices',
			function() {
				$api_data = $this->_get_transient_api_data();
				if ( ! is_wp_error( $api_data ) ) {
					return;
				}
				?>
				<div class="notice notice-error">
					<p>
						<?php echo esc_html( $api_data->get_error_message() ); ?>
					</p>
				</div>
				<?php
			}
		);
	}

	/**
	 * Return URL of new zip file
	 *
	 * @param object $remote Data from GitHub API
	 * @return string
	 */
	protected function _get_zip_url( $remote ) {
		$url = false;

		if ( ! empty( $remote->assets ) && is_array( $remote->assets ) ) {
			if ( ! empty( $remote->assets[0] ) && is_object( $remote->assets[0] ) ) {
				if ( ! empty( $remote->assets[0]->browser_download_url ) ) {
					$url = $remote->assets[0]->browser_download_url;
				}
			}
		}

		$tag_name = isset( $remote->tag_name ) ? $remote->tag_name : null;

		if ( ! $url && $tag_name ) {
			$url = sprintf(
				'https://github.com/%1$s/%2$s/archive/%3$s.zip',
				$this->user_name,
				$this->repository,
				$tag_name
			);
		}

		return apply_filters(
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
	}

	/**
	 * Return the data from the Transient API or GitHub API.
	 *
	 * @return object|WP_Error
	 */
	protected function _get_transient_api_data() {
		$transient_name = sprintf( 'wp_github_plugin_updater_%1$s', $this->plugin_name );
		$transient = get_transient( $transient_name );

		if ( false !== get_transient( $transient_name ) ) {
			return $transient;
		}

		$api_data = $this->_get_github_api_data();
		set_transient( $transient_name, $api_data, 60 * 5 );
		return $api_data;
	}

	/**
	 * Return the data from the GitHub API.
	 *
	 * @return object|WP_Error
	 */
	protected function _get_github_api_data() {
		global $pagenow;
		$response = $this->_request_github_api();
		if ( is_wp_error( $response ) ) {
			return $response;
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

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Inc2734_WP_GitHub_Plugin_Updater error. ' . $response_code . ' : ' . $message );
		}

		if ( $pagenow === 'update-core.php' || $pagenow === 'plugins.php' ) {
			$current = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );
			$error_message = sprintf(
				__( '[%1$s] %2$s', 'inc2734-wp-github-plugin-updater' ),
				$current['Name'],
				$message
			);
			return new WP_Error(
				$response_code,
				$error_message
			);
		}
		return null;
	}

	/**
	 * Get request to GitHub API
	 *
	 * @return json|WP_Error
	 */
	protected function _request_github_api() {
		global $wp_version;

		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/releases/latest',
			$this->user_name,
			$this->repository
		);

		return wp_remote_get(
			apply_filters(
				sprintf(
					'inc2734_github_plugin_updater_request_url_%1$s/%2$s',
					$this->user_name,
					$this->repository
				),
				$url,
				$this->user_name,
				$this->repository
			),
			[
				'user-agent' => 'WordPress/' . $wp_version,
				'headers'    => [
					'Accept-Encoding' => '',
				],
			]
		);
	}

	/**
	 * Sanitize version
	 *
	 * @param string $version
	 * @return string
	 */
	protected function _sanitize_version( $version ) {
		$version = preg_replace( '/^v(.*)$/', '$1', $version );
		return $version;
	}

	/**
	 * If remote version is newer, return true
	 *
	 * @param string $current_version
	 * @param string $remote_version
	 * @return bool
	 */
	protected function _should_update( $current_version, $remote_version ) {
		return version_compare(
			$this->_sanitize_version( $current_version ),
			$this->_sanitize_version( $remote_version ),
			'<'
		);
	}

	/**
	 * Return http status code from $url
	 *
	 * @param string $url
	 * @return int
	 */
	protected function _get_http_status_code( $url ) {
		global $wp_version;

		$response = wp_remote_head(
			$url,
			[
				'user-agent' => 'WordPress/' . $wp_version,
				'headers'    => [
					'Accept-Encoding' => '',
				],
			]
		);

		return wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Return content text
	 *
	 * @param string $url
	 * @return string
	 */
	protected function _get_content_text( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		$text = file_get_contents( $url );
		if ( $text === false ) {
			return '';
		}
		if ( 'md' === substr( $url, strrpos( $url, '.' ) + 1 ) ) {
			$parsedown = new Parsedown();
			$text = $parsedown->text( $text );
		}
		return $text;
	}
}

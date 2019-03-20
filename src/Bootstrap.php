<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater;

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
	 * @var array
	 */
	protected $fields = [];

	/**
	 * Cache of GitHub API data
	 *
	 * @var object
	 */
	protected $api_data;

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
		$this->fields      = $fields;

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

		$current = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );

		if ( is_null( $this->api_data ) ) {
			$this->api_data = $this->_get_transient_api_data();
		}

		if ( is_wp_error( $this->api_data ) ) {
			$this->_set_notice_error_about_github_api();
			return $transient;
		}

		if ( ! isset( $this->api_data->tag_name ) ) {
			return $transient;
		}

		if ( ! $this->_should_update( $current['Version'], $this->api_data->tag_name ) ) {
			return $transient;
		}

		$package = $this->_get_zip_url( $this->api_data );
		$http_status_code = $this->_get_http_status_code( $package );
		if ( ! $package || ! in_array( $http_status_code, [ 200, 302 ] ) ) {
			$this->api_data = new \WP_Error(
				$http_status_code,
				'Inc2734_WP_GitHub_Plugin_Updater error. zip url not found. ' . $package
			);
			return $transient;
		}

		$transient->response[ $this->plugin_name ] = (object) [
			'slug'        => $this->plugin_name,
			'plugin'      => $this->plugin_name,
			'new_version' => $this->api_data->tag_name,
			'url'         => ( ! empty( $this->fields['homepage'] ) ) ? $this->fields['homepage'] : '',
			'package'     => $package,
		];

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

		if ( is_null( $this->api_data ) ) {
			$transient_name = sprintf( 'wp_github_plugin_updater_%1$s', $this->plugin_name );
			if ( false === get_transient( $transient_name ) ) {
				$api_data = $this->_get_github_api_data();
				set_transient( $transient_name, $api_data, 60 * 5 );
				$this->api_data = $api_data;
			} else {
				$this->api_data = get_transient( $transient_name );
			}
		}

		if ( is_wp_error( $this->api_data ) ) {
			return $obj;
		}

		$parsedown = new \Parsedown();
		$current   = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );
		$readme    = '';

		if ( is_file( WP_PLUGIN_DIR . '/' . dirname( $this->plugin_name ) . '/README.md' ) ) {
			$readme = $parsedown->text( file_get_contents( WP_PLUGIN_DIR . '/' . dirname( $this->plugin_name ) . '/README.md' ) );
		}

		$obj               = new \stdClass();
		$obj->slug         = $this->plugin_name;
		$obj->name         = esc_html( $current['Name'] );
		$obj->plugin_name  = esc_html( $current['Name'] );
		$obj->author       = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( $this->api_data->author->html_url ), esc_html( $this->api_data->author->login ) );
		$obj->version      = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', $this->api_data->html_url, $this->api_data->tag_name );
		$obj->last_updated = $this->api_data->published_at;
		$obj->sections     = [ 'readme' => $readme ];

		return $obj;
	}

	/**
	 * Set notice error about GitHub API using admin_notice hook
	 *
	 * @return void
	 */
	protected function _set_notice_error_about_github_api() {
		if ( ! is_wp_error( $this->api_data ) ) {
			return;
		}

		add_action(
			'admin_notices',
			function() {
				?>
				<div class="notice notice-error">
					<p>
						<?php echo esc_html( $this->api_data->get_error_message() ); ?>
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
		if ( false === get_transient( $transient_name ) ) {
			$api_data = $this->_get_github_api_data();
			set_transient( $transient_name, $api_data, 60 * 5 );
		} else {
			$api_data = get_transient( $transient_name );
		}

		return $api_data;
	}

	/**
	 * Return the data from the GitHub API.
	 *
	 * @return object|WP_Error
	 */
	protected function _get_github_api_data() {
		$response = $this->_request_github_api();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 200 == $response_code ) {
			return $body;
		}

		return new \WP_Error(
			$response_code,
			'Inc2734_WP_GitHub_Plugin_Updater error. ' . $body->message
		);
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
}

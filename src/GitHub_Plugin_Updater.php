<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater;

class GitHub_Plugin_Updater {

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

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, '_pre_set_site_transient_update_plugins' ] );
		add_filter( 'upgrader_pre_install', [ $this, '_upgrader_pre_install' ], 10, 2 );
		add_filter( 'upgrader_source_selection', [ $this, '_upgrader_source_selection' ], 10, 4 );
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
			$this->_set_notice_error_about_github_api();
			return $transient;
		}

		if ( ! $this->_should_update( $current['Version'], $this->api_data->tag_name ) ) {
			return $transient;
		}

		$package = $this->_get_zip_url( $this->api_data );

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
	 * Correspondence when the plugin can not be updated
	 *
	 * @param bool $bool
	 * @param array $hook_extra
	 * @return bool|WP_Error.
	 */
	public function _upgrader_pre_install( $bool, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $this->plugin_name !== $hook_extra['plugin'] ) {
			return $bool;
		}

		global $wp_filesystem;

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_name;
		if ( ! $wp_filesystem->is_writable( $plugin_dir ) ) {
			return new \WP_Error();
		}

		return $bool;
	}

	/**
	 * Expand the plugin
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param WP_Upgrader $install
	 * @param array $args['hook_extra']
	 * @return $source|WP_Error.
	 */
	public function _upgrader_source_selection( $source, $remote_source, $install, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $this->plugin_name !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;

		$source_plugin_dir = untrailingslashit( WP_CONTENT_DIR ) . '/upgrade';
		if ( $wp_filesystem->is_writable( $source_plugin_dir ) && $wp_filesystem->is_writable( $source ) ) {
			if ( 0 < strpos( $this->plugin_name, '/' ) ) {
				$slug = trailingslashit( dirname( $this->plugin_name ) );
			} else {
				$slug = $this->plugin_name;
			}
			$newsource = trailingslashit( $source_plugin_dir ) . $slug;
			if ( $wp_filesystem->move( $source, $newsource, true ) ) {
				return $newsource;
			}
		}

		return new \WP_Error();
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

		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php echo esc_html( $this->api_data->get_error_message() ); ?>
				</p>
			</div>
			<?php
		} );
	}

	/**
	 * Return URL of new zip file
	 *
	 * @param object $remote Data from GitHub API
	 * @return string
	 */
	protected function _get_zip_url( $remote ) {
		if ( ! empty( $remote->assets ) && is_array( $remote->assets ) ) {
			if ( ! empty( $remote->assets[0] ) && is_object( $remote->assets[0] ) ) {
				if ( ! empty( $remote->assets[0]->browser_download_url ) ) {
					return $remote->assets[0]->browser_download_url;
				}
			}
		}

		return sprintf(
			'https://github.com/%1$s/%2$s/archive/%3$s.zip',
			$this->user_name,
			$this->repository,
			$remote->tag_name
		);
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
		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/releases/latest',
			$this->user_name,
			$this->repository
		);

		return wp_remote_get( $url, [
			'headers' => [
				'Accept-Encoding' => '',
			],
		] );
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
}

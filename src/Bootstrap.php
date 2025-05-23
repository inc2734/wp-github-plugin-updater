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
use Inc2734\WP_GitHub_Plugin_Updater\App\Model\GitHubReleases;
use Inc2734\WP_GitHub_Plugin_Updater\App\Model\GitHubRepositoryContent;
use Inc2734\WP_GitHub_Plugin_Updater\App\Model\GitHubRepositoryContributors;

class Bootstrap {

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	protected $slug;

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
	 * Plugin data fields.
	 *
	 * @var Fields
	 */
	protected $fields;

	/**
	 * @var GitHubReleases
	 */
	protected $github_releases;

	/**
	 * @var GitHubRepositoryContent
	 */
	protected $github_repository_content;

	/**
	 * @var GitHubRepositoryContributors
	 */
	protected $github_repository_contributors;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin basename.
	 * @param string $user_name GitHub user name.
	 * @param string $repository GitHub repository name.
	 * @param array  $fields Plugin data fields.
	 */
	public function __construct( $plugin_name, $user_name, $repository, array $fields = array() ) {
		$this->plugin_name = $plugin_name;
		$this->slug        = preg_replace( '|^([^/]+)?/.+$|', '$1', $plugin_name );
		$this->user_name   = $user_name;
		$this->repository  = $repository;
		$this->fields      = new Fields( $fields );

		load_textdomain( 'inc2734-wp-github-plugin-updater', __DIR__ . '/languages/' . get_locale() . '.mo' );

		$upgrader                             = new App\Model\Upgrader( $plugin_name );
		$this->github_releases                = new GitHubReleases( $plugin_name, $user_name, $repository );
		$this->github_repository_content      = new GitHubRepositoryContent( $plugin_name, $user_name, $repository );
		$this->github_repository_contributors = new GitHubRepositoryContributors( $plugin_name, $user_name, $repository );

		add_filter( 'extra_plugin_headers', array( $this, '_extra_plugin_headers' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, '_pre_set_site_transient_update_plugins' ) );
		add_filter( 'upgrader_pre_install', array( $upgrader, 'pre_install' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( $upgrader, 'upgrader_pre_download' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, '_plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, '_upgrader_process_complete' ), 10, 2 );
	}

	/**
	 * Filters extra file headers by plugin.
	 *
	 * @param array $headers Array of plugin headers.
	 */
	public function _extra_plugin_headers( $headers ) {
		if ( ! in_array( 'Tested up to', $headers, true ) ) {
			$headers[] = 'Tested up to';
		}
		return $headers;
	}

	/**
	 * Overwrite site_transient_update_plugins.
	 *
	 * @see https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
	 *
	 * @throws \RuntimeException Invalid response.
	 *
	 * @param false|array $transient Transient of update_plugins.
	 * @return false|array
	 */
	public function _pre_set_site_transient_update_plugins( $transient ) {
		global $wp_version;

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $this->plugin_name ) ) {
			return $transient;
		}

		if ( ! empty( $transient->no_update[ $this->plugin_name ] ) ) {
			return $transient;
		}

		$response = $this->github_releases->get();
		try {
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( $response->get_error_message() );
			}
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $transient;
		}

		if ( ! isset( $response->tag_name ) ) {
			return $transient;
		}

		if ( ! $response->package ) {
			return $transient;
		}

		$remote = $this->github_repository_content->get_headers( $response->tag_name );

		$update = (object) array(
			'id'           => $this->user_name . '/' . $this->repository . '/' . $this->plugin_name,
			'slug'         => $this->slug,
			'plugin'       => $this->plugin_name,
			'new_version'  => $response->tag_name,
			'url'          => $this->fields->get( 'homepage' ),
			'package'      => $response->package,
			'icons'        => $this->fields->get( 'icons' ) ? (array) $this->fields->get( 'icons' ) : false,
			'banners'      => $this->fields->get( 'banners' ),
			'tested'       => $this->fields->get( 'tested' ) ? $this->fields->get( 'tested' ) : $remote['Tested up to'],
			'requires_php' => $this->fields->get( 'requires_php' ) ? $this->fields->get( 'requires_php' ) : $remote['RequiresPHP'],
			'requires'     => $this->fields->get( 'requires' ) ? $this->fields->get( 'requires' ) : $remote['RequiresWP'],
		);

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$update = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_transient_response_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$update
		);
		// phpcs:enable

		$current = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );

		$current_version      = $current['Version'];
		$current_requires_wp  = $current['RequiresWP'];
		$current_requires_php = $current['RequiresPHP'];

		$env_info = array(
			'wp_version'  => $this->_sanitize_version( $wp_version ),
			'php_version' => $this->_sanitize_version( PHP_VERSION ),
		);

		$current_info = array(
			'version'      => $this->_sanitize_version( $current_version ),
			'requires_wp'  => $this->_sanitize_version( $current_requires_wp ),
			'requires_php' => $this->_sanitize_version( $current_requires_php ),
		);

		$new_info = array(
			'version'      => $this->_sanitize_version( $update->new_version ),
			'requires_wp'  => $this->_sanitize_version( $update->requires ),
			'requires_php' => $this->_sanitize_version( $update->requires_php ),
		);

		if ( ! static::should_update( $env_info, $current_info, $new_info ) ) {
			if ( false === $transient || null === $transient ) {
				$transient            = new stdClass();
				$transient->no_update = array();
			}
			$transient->no_update[ $this->plugin_name ] = $update;
		} else {
			if ( false === $transient || null === $transient ) {
				$transient           = new stdClass();
				$transient->response = array();
			}
			$transient->response[ $this->plugin_name ] = $update;
		}

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

		if ( ! isset( $arg->slug ) || $arg->slug !== $this->slug ) {
			return $obj;
		}

		$response = $this->github_releases->get();
		if ( is_wp_error( $response ) ) {
			return $obj;
		}

		$version = ! empty( $response->tag_name ) ? $response->tag_name : null;

		$current = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_name );
		$remote  = $this->github_repository_content->get_headers( $version );

		$obj               = new stdClass();
		$obj->slug         = $this->plugin_name;
		$obj->name         = esc_html( $current['Name'] );
		$obj->plugin_name  = esc_html( $current['Name'] );
		$obj->author       = ! empty( $response->author ) ?
			sprintf(
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( $response->author->html_url ),
				esc_html( $response->author->login )
			) :
			null;
		$obj->version      = ! empty( $response->html_url ) && $version ?
			sprintf(
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( $response->html_url ),
				esc_html( $version )
			) :
			null;
		$obj->last_updated = $response->published_at;
		$obj->requires     = esc_html( $remote['RequiresWP'] );
		$obj->requires_php = esc_html( $remote['RequiresPHP'] );
		$obj->tested       = esc_html( $remote['Tested up to'] );

		if ( ! empty( $response->assets ) && is_array( $response->assets ) ) {
			if ( ! empty( $response->assets[0] ) && is_object( $response->assets[0] ) ) {
				if ( ! empty( $response->assets[0]->download_count ) ) {
					$obj->active_installs = $response->assets[0]->download_count;
				}
			}
		}

		$contributors = $this->github_repository_contributors->get();
		if ( ! is_wp_error( $contributors ) ) {
			$obj->contributors = $contributors;
		} else {
			$obj->contributors = array();
		}

		$fields = array_keys( get_object_vars( $this->fields ) );
		foreach ( $fields as $field ) {
			if ( isset( $obj->$field ) ) {
				continue;
			}
			$obj->$field = $this->fields->get( $field );
		}

		if ( empty( $obj->sections ) ) {
			$description_url = $this->fields->get( 'description_url' )
				? $this->fields->get( 'description_url' )
				: WP_PLUGIN_DIR . '/' . dirname( $this->plugin_name ) . '/README.md';

			$obj->sections = array(
				'description'  => $this->_get_content_text( $description_url ),
				'installation' => $this->_get_content_text( $this->fields->get( 'installation_url' ) ),
				'faq'          => $this->_get_content_text( $this->fields->get( 'faq_url' ) ),
				'screenshots'  => $this->_get_content_text( $this->fields->get( 'screenshots_url' ) ),
				'changelog'    => $this->_get_content_text( $this->fields->get( 'changelog_url' ) ),
				'reviews'      => $this->_get_content_text( $this->fields->get( 'reviews_url' ) ),
				'other_notes'  => $this->_get_content_text( $this->fields->get( 'other_notes_url' ) ),
			);
		}

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$obj = apply_filters(
			sprintf(
				'inc2734_github_plugin_updater_plugins_api_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$obj,
			$response
		);
		// phpcs:enable

		$obj->external      = true;
		$obj->download_link = false;

		return $obj;
	}

	/**
	 * Fires when the upgrader process is complete.
	 *
	 * @param WP_Upgrader $upgrader_object WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
	 * @param array       $hook_extra Array of bulk item update data.
	 */
	public function _upgrader_process_complete( $upgrader_object, $hook_extra ) {
		if ( 'update' === $hook_extra['action'] && 'plugin' === $hook_extra['type'] ) {
			foreach ( $hook_extra['plugins'] as $plugin ) {
				if ( $plugin === $this->plugin_name ) {
					$this->github_releases->delete_transient();
					$this->github_repository_content->delete_transient();
				}
			}
		}
	}

	/**
	 * Sanitize version.
	 *
	 * @param string $version Version to check.
	 * @return string
	 */
	protected function _sanitize_version( $version ) {
		$version = preg_replace( '/^v(.*)$/', '$1', $version );
		return $version;
	}

	/**
	 * If remote version is newer, return true.
	 *
	 * @param array $env_info Environment info.
	 * @param array $current_info Current plugin info.
	 * @param array $new_info New plugin info.
	 * @return bool
	 */
	public static function should_update( $env_info, $current_info, $new_info ) {
		$version_ok = version_compare(
			$current_info['version'],
			$new_info['version'],
			'<'
		);

		// Check whether your current environment meets the WP version required by the new plugin.
		$wp_ok = true;
		if ( $new_info['requires_wp'] ) {
			$wp_ok = version_compare(
				$env_info['wp_version'],
				$new_info['requires_wp'],
				'>='
			);
		}

		// Check whether your current environment meets the PHP version required by the new plugin.
		$php_ok = true;
		if ( $new_info['requires_php'] ) {
			$php_ok = version_compare(
				$env_info['php_version'],
				$new_info['requires_php'],
				'>='
			);
		}

		return $version_ok && $wp_ok && $php_ok;
	}

	/**
	 * Return content text.
	 *
	 * @param string $url Name of the file to read.
	 * @return string
	 */
	protected function _get_content_text( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		$text = file_get_contents( $url );
		if ( false === $text ) {
			return '';
		}
		if ( 'md' === substr( $url, strrpos( $url, '.' ) + 1 ) ) {
			$parsedown = new Parsedown();
			$text      = $parsedown->text( $text );
		}
		return $text;
	}
}

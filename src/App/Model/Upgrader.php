<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use WP_Error;

class Upgrader {

	/**
	 * The plugin name
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * @param string $plugin_name
	 */
	public function __construct( $plugin_name ) {
		$this->plugin_name = $plugin_name;
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
	public function source_selection( $source, $remote_source, $install, $hook_extra ) {
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

		return new WP_Error();
	}

	/**
	 * Correspondence when the plugin can not be updated
	 *
	 * @param bool $bool
	 * @param array $hook_extra
	 * @return bool|WP_Error.
	 */
	public function pre_install( $bool, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $this->plugin_name !== $hook_extra['plugin'] ) {
			return $bool;
		}

		global $wp_filesystem;

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_name;
		if ( ! $wp_filesystem->is_writable( $plugin_dir ) ) {
			return new WP_Error();
		}

		return $bool;
	}
}

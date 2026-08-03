<?php
class GitHub_Plugin_Updater_Test extends WP_UnitTestCase {

	public function __construct() {
		parent::__construct();
	}

	public function set_up() {
		parent::set_up();

		include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
	}

	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * @test
	 */
	public function no_update_when_latest_version_is_installed() {
		$current = get_plugin_data( WP_PLUGIN_DIR . '/hello.php' );
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => $current['Version'],
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_plugins( false );

		$this->assertObjectHasProperty( 'no_update', $transient );
		$this->assertArrayHasKey( 'hello.php', $transient->no_update );
	}

	/**
	 * @test
	 */
	public function no_update_when_installed_version_is_newer() {
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '0.0.1',
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_plugins( false );

		$this->assertObjectHasProperty( 'no_update', $transient );
		$this->assertArrayHasKey( 'hello.php', $transient->no_update );
	}

	/**
	 * @test
	 */
	public function no_response_when_update_package_is_unavailable() {
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '1000000',
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_plugins( false );

		$this->assertFalse( $transient );
	}

	/**
	 * @test
	 */
	public function no_update_when_filtered_version_is_not_newer() {
		$filter_name = 'inc2734_github_plugin_updater_transient_response_inc2734/dummy-hello-dolly';
		$filter      = function( $update ) {
			$update->new_version = '0.0.1';
			return $update;
		};

		add_filter( $filter_name, $filter );

		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '1000000',
				'package'  => 'https://example.com/plugin.zip',
			)
		);

		$transient = $updater->_pre_set_site_transient_update_plugins( false );

		remove_filter( $filter_name, $filter );

		$this->assertObjectHasProperty( 'no_update', $transient );
		$this->assertArrayHasKey( 'hello.php', $transient->no_update );
		$this->assertSame( '0.0.1', $transient->no_update['hello.php']->new_version );
	}

	/**
	 * @test
	 */
	public function no_response_when_filtered_version_is_newer_but_package_is_unavailable() {
		$current     = get_plugin_data( WP_PLUGIN_DIR . '/hello.php' );
		$filter_name = 'inc2734_github_plugin_updater_transient_response_inc2734/dummy-hello-dolly';
		$filter      = function( $update ) {
			$update->new_version = '1000000';
			return $update;
		};

		add_filter( $filter_name, $filter );

		$updater = $this->get_updater(
			(object) array(
				'tag_name' => $current['Version'],
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_plugins( false );

		remove_filter( $filter_name, $filter );

		$this->assertFalse( $transient );
	}

	/**
	 * @test
	 */
	public function success_transmission() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$transient = apply_filters( 'pre_set_site_transient_update_plugins', false );
		$expected  = new stdClass();
		$expected->response = [
			'hello.php' => (object) [
				'id'           => 'inc2734/dummy-hello-dolly/hello.php',
				'plugin'       => 'hello.php',
				'new_version'  => '1000000',
				'url'          => false,
				'package'      => 'https://github.com/inc2734/dummy-hello-dolly/releases/download/1000000/dummy-hello-dolly-1000000.zip',
				'slug'         => 'hello.php',
				'tested'       => '5.5',
				'icons'        => false,
				'banners'      => false,
				'requires_php' => '5.6',
				'requires'     => '5.0',
			],
		];
		$this->assertEquals( $expected, $transient );
	}

	/**
	 * @test
	 */
	public function fail_transmission() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-norepo' );
		$transient = apply_filters( 'pre_set_site_transient_update_plugins', false );
		$this->assertFalse( $transient );
	}

	/**
	 * @test
	 */
	public function upgrader_pre_install() {
		$updater  = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dorry' );
		$upgrader = new Inc2734\WP_GitHub_Plugin_Updater\App\Model\Upgrader( 'hello.php' );

		$result = $upgrader->pre_install( true, [ 'plugin' => 'mw-wp-form/mw-wp-form.php' ] );
		$this->assertTrue( $result );

		$result = $upgrader->pre_install( true, [ 'plugin' => 'smart-custom-fields/smart-custom-fields.php' ] );
		$this->assertTrue( $result );

		if ( WP_Filesystem() ) {
			global $wp_filesystem;

			$wp_filesystem->move( WP_CONTENT_DIR . '/plugins/hello.php', WP_CONTENT_DIR . '/plugins/hello-dolly-org.php' );
			$result = $upgrader->pre_install( true, [ 'plugin' => 'hello.php' ] );
			$this->assertTrue( is_wp_error( $result ) );
			$wp_filesystem->move( WP_CONTENT_DIR . '/plugins/hello-dolly-org.php', WP_CONTENT_DIR . '/plugins/hello.php' );
		}
	}

	/**
	 * @test
	 */
	public function upgrader_process_complete() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$hook_extra_list = array(
			array( 'plugin' => 'hello.php' ),
			array( 'plugins' => array( 'hello.php' ) ),
		);

		foreach ( $hook_extra_list as $hook_extra ) {
			set_transient( 'wp_github_plugin_updater_hello.php', 'release' );
			set_transient( 'wp_github_plugin_updater_repository_data_hello.php', 'repository' );

			$updater->_upgrader_process_complete(
				null,
				array_merge(
					array(
						'action' => 'update',
						'type'   => 'plugin',
					),
					$hook_extra
				)
			);

			$this->assertFalse( get_transient( 'wp_github_plugin_updater_hello.php' ) );
			$this->assertFalse( get_transient( 'wp_github_plugin_updater_repository_data_hello.php' ) );
		}
	}

	/**
	 * @test
	 */
	public function upgrader_process_complete_ignores_other_plugins() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		set_transient( 'wp_github_plugin_updater_hello.php', 'release' );
		set_transient( 'wp_github_plugin_updater_repository_data_hello.php', 'repository' );

		$updater->_upgrader_process_complete(
			null,
			array(
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => 'example/example.php',
			)
		);

		$this->assertSame( 'release', get_transient( 'wp_github_plugin_updater_hello.php' ) );
		$this->assertSame( 'repository', get_transient( 'wp_github_plugin_updater_repository_data_hello.php' ) );

		delete_transient( 'wp_github_plugin_updater_hello.php' );
		delete_transient( 'wp_github_plugin_updater_repository_data_hello.php' );
	}

	/**
	 * Return an updater with stubbed GitHub responses.
	 *
	 * @param object $response GitHub release response.
	 * @return Inc2734\WP_GitHub_Plugin_Updater\Bootstrap
	 */
	private function get_updater( $response ) {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		$github_releases = new class( $response ) {
			private $response;

			public function __construct( $response ) {
				$this->response = $response;
			}

			public function get() {
				return $this->response;
			}
		};

		$github_repository_content = new class() {
			public function get_headers() {
				return array(
					'Tested up to' => '',
					'RequiresPHP'  => '',
					'RequiresWP'   => '',
				);
			}
		};

		$this->set_protected_property( $updater, 'github_releases', $github_releases );
		$this->set_protected_property( $updater, 'github_repository_content', $github_repository_content );

		return $updater;
	}

	/**
	 * Set a protected property.
	 *
	 * @param object $object Object to update.
	 * @param string $name Property name.
	 * @param mixed  $value Property value.
	 */
	private function set_protected_property( $object, $name, $value ) {
		$property = new ReflectionProperty( $object, $name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}
}

<?php
class GitHub_Plugin_Updater_Test extends WP_UnitTestCase {

	private $_upgrade_dir;

	public function __construct() {
		parent::__construct();

		$this->_upgrade_dir = untrailingslashit( WP_CONTENT_DIR ) . '/upgrade';
	}

	public function setup() {
		parent::setup();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
			include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
			$wp_filesystem = new WP_Filesystem_Direct( [] );
		}

		if ( ! file_exists( $this->_upgrade_dir ) ) {
			mkdir( $this->_upgrade_dir );
		}
	}

	public function tearDown() {
		parent::tearDown();

		if ( file_exists( $this->_upgrade_dir ) ) {
			system( 'rm -rf ' . $this->_upgrade_dir );
		}
	}

	public function test_success_transmission() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\GitHub_Plugin_Updater( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$transient = apply_filters( 'pre_set_site_transient_update_plugins', false );
		$expected  = new stdClass();
		$expected->response = [
			'hello.php' => (object) [
				'plugin'      => 'hello.php',
				'new_version' => '1000000',
				'url'         => '',
				'package'     => 'https://github.com/inc2734/dummy-hello-dolly/archive/1000000.zip',
				'slug'        => 'hello.php'
			],
		];
		$this->assertEquals( $expected, $transient );
	}

	public function test_fail_transmission() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\GitHub_Plugin_Updater( 'hello.php', 'inc2734', 'dummy-norepo' );
		$transient = apply_filters( 'pre_set_site_transient_update_plugins', false );
		$this->assertFalse( $transient );
	}

	public function test_upgrader_pre_install() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\GitHub_Plugin_Updater( 'hello.php', 'inc2734', 'dummy-hello-dorry' );

		$result = $updater->_upgrader_pre_install( true, [ 'plugin' => 'mw-wp-form/mw-wp-form.php' ] );
		$this->assertTrue( $result );

		$result = $updater->_upgrader_pre_install( true, [ 'plugin' => 'smart-custom-fields/smart-custom-fields.php' ] );
		$this->assertTrue( $result );

		rename( WP_CONTENT_DIR . '/plugins/hello.php', WP_CONTENT_DIR . '/plugins/hello-dolly-org.php' );
		$result = $updater->_upgrader_pre_install( true, [ 'plugin' => 'hello.php' ] );
		$this->assertTrue( is_wp_error( $result ) );
		rename( WP_CONTENT_DIR . '/plugins/hello-dolly-org.php', WP_CONTENT_DIR . '/plugins/hello.php' );
	}

	public function test_upgrader_source_selection() {
		touch( $this->_upgrade_dir . '/hello-xxx.php' );

		$updater = new Inc2734\WP_GitHub_Plugin_Updater\GitHub_Plugin_Updater( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		$newsource = $updater->_upgrader_source_selection(
			$this->_upgrade_dir . '/hello-xxx.php',
			$this->_upgrade_dir . '/hello-xxx.php',
			false,
			[ 'plugin' => 'mw-wp-form/mw-wp-form.php' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/hello-xxx.php', $newsource );

		$newsource = $updater->_upgrader_source_selection(
			$this->_upgrade_dir . '/hello-xxx.php',
			$this->_upgrade_dir . '/hello-xxx.php',
			false,
			[ 'plugin' => 'hello.php' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/hello.php', $newsource );
	}
}

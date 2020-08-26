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
				'package'      => 'https://github.com/inc2734/dummy-hello-dolly/archive/1000000.zip',
				'slug'         => 'hello.php',
				'tested'       => '',
				'icons'        => false,
				'banners'      => false,
				'requires_php' => '',
				'requires'     => '',
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

		rename( WP_CONTENT_DIR . '/plugins/hello.php', WP_CONTENT_DIR . '/plugins/hello-dolly-org.php' );
		$result = $upgrader->pre_install( true, [ 'plugin' => 'hello.php' ] );
		$this->assertTrue( is_wp_error( $result ) );
		rename( WP_CONTENT_DIR . '/plugins/hello-dolly-org.php', WP_CONTENT_DIR . '/plugins/hello.php' );
	}

	/**
	 * @test
	 */
	public function upgrader_source_selection() {
		touch( $this->_upgrade_dir . '/hello-xxx.php' );

		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$upgrader = new Inc2734\WP_GitHub_Plugin_Updater\App\Model\Upgrader( 'hello.php' );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/hello-xxx.php',
			$this->_upgrade_dir . '/hello-xxx.php',
			false,
			[ 'plugin' => 'mw-wp-form/mw-wp-form.php' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/hello-xxx.php', $newsource );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/hello-xxx.php',
			$this->_upgrade_dir . '/hello-xxx.php',
			false,
			[ 'plugin' => 'hello.php' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/hello.php', $newsource );
	}
}

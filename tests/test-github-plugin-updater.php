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
}

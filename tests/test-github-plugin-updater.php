<?php
/**
 * Class SampleTest
 *
 * @package wp-github-plugin-updater
 */
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
				'plugin'      => 'hello.php',
				'new_version' => '1000000',
				'url'         => '',
				'package'     => 'https://github.com/inc2734/dummy-hello-dolly/archive/1000000.zip',
				'slug'        => 'hello.php'
			],
		];
		$this->assertEquals( $expected, $transient );
	}

	public function fail_transmission() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-norepo' );
		$transient = apply_filters( 'pre_set_site_transient_update_plugins', false );
		$this->assertFalse( $transient );
	}

	public function upgrader_pre_install() {
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dorry' );

		$result = $updater->_upgrader_pre_install( true, [ 'plugin' => 'mw-wp-form/mw-wp-form.php' ] );
		$this->assertTrue( $result );

		$result = $updater->_upgrader_pre_install( true, [ 'plugin' => 'smart-custom-fields/smart-custom-fields.php' ] );
		$this->assertTrue( $result );

		rename( WP_CONTENT_DIR . '/plugins/hello.php', WP_CONTENT_DIR . '/plugins/hello-dolly-org.php' );
		$result = $updater->_upgrader_pre_install( true, [ 'plugin' => 'hello.php' ] );
		$this->assertTrue( is_wp_error( $result ) );
		rename( WP_CONTENT_DIR . '/plugins/hello-dolly-org.php', WP_CONTENT_DIR . '/plugins/hello.php' );
	}

	/**
	 * @test
	 */
	public function upgrader_source_selection() {
		touch( $this->_upgrade_dir . '/hello-xxx.php' );

		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

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

	/**
	 * @test
	 */
	public function get_http_status_code() {
		$class = new ReflectionClass( 'Inc2734\WP_GitHub_Plugin_Updater\Bootstrap' );
		$method = $class->getMethod( '_get_http_status_code' );
		$method->setAccessible( true );
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		$this->assertEquals(
			302,
			$method->invokeArgs(
				$updater,
				[
					'https://github.com/inc2734/dummy-hello-dolly/archive/1000000.zip',
				]
			)
		);
	}

	/**
	 * @test
	 */
	public function request_github_api() {
		$class = new ReflectionClass( 'Inc2734\WP_GitHub_Plugin_Updater\Bootstrap' );
		$method = $class->getMethod( '_request_github_api' );
		$method->setAccessible( true );
		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		add_filter(
			'inc2734_github_plugin_updater_request_url',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/github-api/response.json';
			}
		);

		$response = $method->invokeArgs(
			$updater,
			[]
		);
		$body = json_decode( wp_remote_retrieve_body( $response ) );

		$this->assertTrue( 0 === strpos( $body->assets[0]->browser_download_url, 'https://snow-monkey.2inc.org' ) );
	}
}

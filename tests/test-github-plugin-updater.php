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
				'url'         => true,
				'package'     => 'https://github.com/inc2734/dummy-hello-dolly/archive/1000000.zip',
				'slug'        => 'hello.php',
				'tested'      => true,
				'icons'       => false,
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
		$_request_github_api = $class->getMethod( '_request_github_api' );
		$_request_github_api->setAccessible( true );
		$_get_zip_url = $class->getMethod( '_get_zip_url' );
		$_get_zip_url->setAccessible( true );

		$updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		add_filter(
			'inc2734_github_plugin_updater_request_url_inc2734/dummy-hello-dolly',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/github-api/response.json';
			}
		);

		$response = $_request_github_api->invokeArgs( $updater, [] );
		$zip_url  = $_get_zip_url->invokeArgs( $updater, [ json_decode( wp_remote_retrieve_body( $response ) ) ] );
		$this->assertTrue( 0 === strpos( $zip_url, 'https://snow-monkey.2inc.org' ) );
	}

	/**
	 * @test
	 */
	public function get_zip_url() {
		$class = new ReflectionClass( 'Inc2734\WP_GitHub_Plugin_Updater\Bootstrap' );
		$dummy_request = (object) [ 'tag_name' => 1000000 ];
		$method = $class->getMethod( '_get_zip_url' );
		$method->setAccessible( true );

		$updater  = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$updater2 = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap( 'hello2.php', 'inc2734', 'dummy-hello-dolly2' );

		add_filter(
			'inc2734_github_plugin_updater_zip_url_inc2734/dummy-hello-dolly',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/dummy-hello-dolly.zip';
			}
		);

		add_filter(
			'inc2734_github_plugin_updater_zip_url_inc2734/dummy-hello-dolly2',
			function( $url ) {
				return $url;
			}
		);

		$zip_url = $method->invokeArgs( $updater, [ $dummy_request ] );
		$this->assertEquals( 'https://snow-monkey.2inc.org/dummy-hello-dolly.zip', $zip_url );

		$zip_url = $method->invokeArgs( $updater2, [ $dummy_request ] );
		$this->assertEquals( 'https://github.com/inc2734/dummy-hello-dolly2/archive/1000000.zip', $zip_url );
	}
}

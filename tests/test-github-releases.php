<?php
class GitHub_Plugin_Updater_Release_Test extends WP_UnitTestCase {

	/**
	 * @test
	 */
	public function request() {
		add_filter(
			'inc2734_github_plugin_updater_request_url_inc2734/dummy-hello-dolly',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/github-api/response.json';
			}
		);

		$github_releases = new Inc2734\WP_GitHub_Plugin_Updater\App\Model\GitHubReleases( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$response = $github_releases->get();
		$this->assertTrue( 0 === strpos( $response->package, 'https://snow-monkey.2inc.org' ) );
	}

	/**
	 * @test
	 */
	public function get_zip_url() {
		$github_releases  = new Inc2734\WP_GitHub_Plugin_Updater\App\Model\GitHubReleases( 'hello.php', 'inc2734', 'dummy-hello-dolly' );

		add_filter(
			'inc2734_github_plugin_updater_zip_url_inc2734/dummy-hello-dolly',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/dummy-hello-dolly.zip';
			}
		);

		$response = $github_releases->get();
		$this->assertEquals( 'https://snow-monkey.2inc.org/dummy-hello-dolly.zip', $response->package );
	}
}

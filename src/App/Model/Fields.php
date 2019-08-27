<?php
/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Plugin_Updater\App\Model;

class Fields {

	/**
	 * @var string
	 */
	protected $homepage = '';

	/**
	 * @var null|string
	 */
	protected $tested = null;

	/**
	 * @var array
	 */
	protected $icons = [];

	/**
	 * @var string
	 */
	protected $description_url = '';

	/**
	 * @var string
	 */
	protected $installation_url = '';

	/**
	 * @var string
	 */
	protected $faq_url = '';

	/**
	 * @var string
	 */
	protected $changelog_url = '';

	/**
	 * @var string
	 */
	protected $screenshots_url = '';

	/**
	 * @var array
	 */
	protected $banners = [];

	/**
	 * @var null|string
	 */
	protected $requires_php = null;

	/**
	 * @var null|string
	 */
	protected $requires = null;

	/**
	 * @param array $fields
	 */
	public function __construct( array $fields ) {
		foreach ( $fields as $field => $value ) {
			if ( property_exists( $this, $field ) ) {
				$this->$field = $value;
			}
		}
	}

	/**
	 * Return specific property
	 *
	 * @param string $field
	 * @return mixed
	 */
	public function get( $field ) {
		if ( property_exists( $this, $field ) ) {
			return $this->$field;
		}
		return false;
	}
}

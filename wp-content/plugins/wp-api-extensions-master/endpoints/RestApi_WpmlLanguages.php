<?php

/**
 * Retrieve the active WPML languages of a site
 */
class RestApi_WpmlLanguages {
	const URL = 'languages';
	private $baseUrl;

	public function __construct($pluginBaseUrl) {
		$this->baseUrl = $pluginBaseUrl . '/' . self::URL;
	}


	public function register_routes() {
		register_rest_route($this->baseUrl, '/wpml', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_wpml_languages'),
		));
	}

	public function get_wpml_languages() {
		$languages = apply_filters('wpml_active_languages', NULL, '');

		$result = array();
		foreach ($languages as $item) {
			$result[] = $this->prepare_item($item);
		}
		return $result;
	}

	private function prepare_item($language) {
		return [
			'id' => $language['id'],
			'code' => $language['code'],
			'native_name' => $language['native_name'],
			'country_flag_url' => $language['country_flag_url'],
		];
	}
}

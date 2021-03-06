<?php

class IntegreatMpdfAPI {

	public function __construct() {
		$this->custom_endpoint();
	}

	/**
	 * Add custom endpoint
	 */
	private function custom_endpoint() {
		add_action('rest_api_init', function () {
			register_rest_route( 'ig-mpdf/v1', 'pdf', [
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_pdf'],
				'args' => [
					'id' => [
						'required' => false,
						'validate_callback' => function($id) {
							return $this->is_valid($id);
						}
					],
					'url' => [
						'required' => false,
						'validate_callback' => function($url) {
							return $this->is_valid(url_to_postid(get_site_url('/') . $url));
						}
					],
				]
			]);
		});
	}

	/**
	 * Get pdf or return error if the request is not valid
	 *
	 * @param $request WP_REST_Request
	 * @return mixed
	 */
	public function get_pdf(WP_REST_Request $request) {
		$id = $request->get_param('id');
		$url = $request->get_param('url');
		if ($id !== null || $url !== null) {
			if ($id === null) {
				$id = url_to_postid(get_site_url('/') . $url);
			}
			// change current language to language of given post
			$GLOBALS['sitepress']->switch_lang(apply_filters('wpml_element_language_code', null, ['element_id' => $id, 'element_type' => 'page']), true);
			$page_ids = $this->get_children($id);
		} else {
			$page_ids = array_slice($this->get_children(0), 1);
		}
		$mpdf = new IntegreatMpdf($page_ids);
		try {
			$mpdf->get_pdf();
		} catch (\Mpdf\MpdfException $e) {
			return new WP_Error('mpdf-error', $e->getMessage(), ['status' => 500]);
		}
	}

	/**
	 * Get all page ids of the given page and all its children in the correct order
	 *
	 * @param $id int|string
	 * @return array
	 */
	private function get_children($id) {
		$direct_children = (new WP_Query([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_parent' => $id,
			'orderby' => 'menu_order post_title',
			'order' => 'ASC',
			'posts_per_page' => -1,
			'fields' => 'ids',
		]))->posts;
		if (empty($direct_children)) {
			return [$id];
		} else {
			return array_reduce(array_map([$this, 'get_children'], $direct_children), function ($all_children, $grand_children) {
				return array_merge($all_children, $grand_children);
			}, [$id]);
		}
	}

	/**
	 * Check whether a given $id is a valid page id for pdf generation
	 *
	 * @param $id int|string
	 * @return bool
	 */
	private function is_valid($id) {
		$page = get_post($id);
		return $page !== null && $page->post_type === 'page' && $page->post_status === 'publish';
	}

}

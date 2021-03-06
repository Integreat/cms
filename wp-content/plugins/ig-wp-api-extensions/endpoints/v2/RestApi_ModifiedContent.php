<?php

/**
 * Retrieve only content that has been modified since a given datetime
 */
abstract class RestApi_ModifiedContentV2 extends RestApi_ExtensionBase {
	const URL = 'modified_content';
	const FORCE_UPDATE_DATE = "2016-09-30T00:00:00+02:00";
	/**
	 * Match empty p html tags spanning the whole string.
	 *
	 * Examples matched:
	 *
	 *     <p></p>
	 *     <p></p><p></p>
	 *     <p>  </p>
	 *     <p></p>  <p></p>
	 *     \n
	 *     <p></p>\n<p></p>
	 *     <p></p>\n  <p></p>
	 *     <p></p>\r\n  <p></p>\r\n
	 *     <p>&nbsp; &nbsp;</p>\n
	 *     &nbsp; &nbsp;\n
	 *     <p>\n</p>
	 */
	const EMPTY_P_PATTERN = '#^((<p>(\s|&nbsp;|<br\s*/\s*>|[\\\n\\\r\s])*</p>)|([\\\n\\\r\s]|&nbsp;|<br\s*/\s*>))*$#';
	/** The return value for a content that is considered empty */
	const EMPTY_CONTENT = "";
	/** The string that indicates a line break in the excerpt */
	const EXCERPT_LINEBREAK_INDICATOR = " ";

	private $datetime_input_format = DateTime::ATOM;
	private $datetime_query_format = DateTime::ATOM;
	private $datetime_zone_gmt;

	public function __construct() {
		parent::__construct();
		$this->datetime_zone_gmt = new DateTimeZone('GMT');
		$this->remove_read_more_link();
		$this->wpml_helper = new WpmlHelper();
		$this->current_request = new stdClass();
	}

	public function register_routes($namespace) {
		$args = $this->get_route_args();
		parent::register_route($namespace,
			self::URL, $this->get_subpath(), [
				'callback' => [$this, 'get_modified_content'],
				'args' => $args
			]);
	}

	private function get_route_args() {
		return [
			'since' => [
				'required' => true,
				'validate_callback' => [$this, 'validate_datetime']
			]
		];
	}

	protected abstract function get_subpath();

	protected abstract function get_posts_type();

	public function validate_datetime($arg) {
		return $this->make_datetime($arg) !== false;
	}

	private function make_datetime($arg) {
		return DateTime::createFromFormat($this->datetime_input_format, $arg);
	}

	public function get_modified_content(WP_REST_Request $request) {
		return $this->get_modified_posts_by_type($this->get_posts_type(), $request);
	}

	private function get_modified_posts_by_type($type, WP_REST_Request $request) {
		$this->current_request->post_type = $type;
		$this->current_request->rest_request = $request;

		// array which does not contain published pages with an unpublished parent
		$post_ids = $this->get_post_ids_recursive(0);

		global $wpdb;
		$querystr = $this->build_query_string();
		$query_result = array_filter($wpdb->get_results($querystr, OBJECT), function ($post) use ($post_ids) {
			// filter pages which have an unpublished parent
			return in_array($post->ID, $post_ids);
		});

		if( $type == 'event' ) {
			$recurring_events_working = false;
			foreach ($query_result as $post) {
				if ($post->recurrence_id !== null) {
					$recurring_events_working = true;
					break;
				}
			}
			if (!$recurring_events_working) {
				// fetch the initial of several recurring events (the initial events have a different post_type)
				$this->current_request->post_type = 'event-recurring';
				$querystr = $this->build_query_string();
				$initial_events = $wpdb->get_results($querystr, OBJECT);
				// fetch the recurring events for every initial event
				$recurring_events = [];
				foreach( $initial_events as $initial_event ) {
					$this->current_request->post_type = 'event';
					$querystr = $this->build_query_string($initial_event);
					$recurring_events = array_merge($recurring_events, $wpdb->get_results($querystr, OBJECT));
				}
				$query_result = array_merge($query_result, $recurring_events);
			}
		}

		$result = [];
		foreach ($query_result as $post) {
			if(isset($_GET['no_trash']) && $_GET['no_trash'] == '1' && $post->post_status == "trash") {
				continue;
			}
			$result[] = $this->prepare_item($post);
		}

		return $result;
	}

	/**
	 * Builds the query string based on the result of the query helper methods.
	 *
	 * @param array $initial_event
	 * @return string
	 */
	protected function build_query_string($initial_event = null) {
		/**
		 * The approach is currently not unified - the helper methods return strings and arrays.
		 * We should implement at least a half-fledged query builder to cope with the different needs
		 * (or use an adequate framework).
		 */
		$select = $this->build_query_select();
		$from = $this->build_query_from($initial_event);
		$where = $this->build_query_where();
		$groups = $this->build_query_groups();
		$order_clauses = $this->build_query_order_clauses();

		$where_first = array_shift($where);
		$where_rest = $where;
		// filter the recurring events when the JOIN translations does not
		if( isset($initial_event) ) {
			$where_rest[] = "em_events.recurrence_id = {$initial_event->event_id}";
		}

		return
			"SELECT $select
			$from
			WHERE $where_first " .
			($where_rest ? "AND " . join(" AND ", $where_rest) : "") . " " .
			($groups ? "GROUP BY " . join(",", $groups) : "") . " " .
			($order_clauses ? "ORDER BY " . join(",", $order_clauses) : "");
	}

	/**
	 * @return string
	 */
	protected function build_query_select() {
		return "posts.ID, posts.post_title, posts.post_type, posts.post_status, posts.post_modified_gmt,
					posts.post_excerpt, posts.post_content, posts.post_parent, posts.menu_order, posts.guid";
	}

	/**
	 * @param array $initial_event
	 * @return string
	 */
	protected function build_query_from($initial_event = null) {
		global $wpdb;
		$current_language = ICL_LANGUAGE_CODE;
		// when the recurring events of an initial event are selected, join on the element_id of the initial event because recurring events don't have their own entries in the translations table
		return "FROM $wpdb->posts posts" . ( isset($initial_event) ? "
				JOIN {$wpdb->prefix}icl_translations translations
						ON translations.element_type = 'post_event-recurring'
						AND translations.element_id = '{$initial_event->ID}'
						AND translations.language_code = '$current_language'" : "
				JOIN {$wpdb->prefix}icl_translations translations
						ON translations.element_type = 'post_{$this->current_request->post_type}'
						AND translations.element_id = posts.ID
						AND translations.language_code = '$current_language'" );
	}

	/**
	 * @return array
	 */
	protected function build_query_where() {
		$since = $this->current_request->rest_request->get_param('since');
		if(strtotime($since) < strtotime(self::FORCE_UPDATE_DATE)) {
			//if the last update is after the deadline, pull all content by setting the since date to the beginning of 2015
			$since = "2015-01-01T00:00:00+02:00";
		}
		$last_modified_gmt = $this
			->make_datetime($since)
			->setTimezone($this->datetime_zone_gmt)
			->format($this->datetime_query_format);

		return [
			"post_type = '{$this->current_request->post_type}'",
			"post_modified_gmt >= '$last_modified_gmt'",
			"post_status IN ('publish', 'trash')"
		];
	}

	/**
	 * @return array
	 */
	protected function build_query_groups() {
		return [];
	}

	/**
	 * @return array
	 */
	protected function build_query_order_clauses() {
		return ["menu_order ASC", "post_title ASC"];
	}

	protected function prepare_item($post) {
		$post = apply_filters('wp_api_extensions_pre_post', $post);
		setup_postdata($post);
		$content = $this->prepare_content($post);
		$output_post = [
			'id' => $post->ID,
			'permalink' => $this->prepare_url($post),
			'title' => ( $post->post_status != "trash" ? $post->post_title : "" ),
			'type' => ( $post->post_type == 'event-recurring' ? 'event' : $post->post_type ),
			'status' => $post->post_status,
			'modified_gmt' => $post->post_modified_gmt,
			'excerpt' => ( $post->post_status != "trash" ? ($content === self::EMPTY_CONTENT ? self::EMPTY_CONTENT : $this->prepare_excerpt($post)) : ""),
			'content' => ( $post->post_status != "trash" ? $content : "" ),
			'parent' => $post->post_parent,
			'order' => $post->menu_order,
			'available_language_urls' => $this->wpml_helper->get_available_languages($post, 'map_post_to_foreign_language_url'),
			'available_languages' => $this->wpml_helper->get_available_languages($post, 'map_post_to_foreign_language_id'),
			'thumbnail' => $this->prepare_thumbnail($post),
		];
		$output_post = apply_filters('wp_api_extensions_output_post', $output_post);
		return $output_post;
	}

	protected function prepare_content($post) {
		$children = get_pages( array( 'child_of' => $post->ID ) );
		if( "" == $post->post_content && count( $children ) == 0 ) {
			$post->post_content = "empty";
		}
		return wpautop( $post->post_content );
	}

	protected function prepare_excerpt($post) {
		$excerpt = $post->post_excerpt ?:
			apply_filters('the_excerpt', apply_filters('get_the_excerpt', $post->post_excerpt));
		$excerpt = str_replace(["</p>", "\r\n", "\n", "\r", "<p>"],
			[self::EXCERPT_LINEBREAK_INDICATOR, self::EXCERPT_LINEBREAK_INDICATOR, self::EXCERPT_LINEBREAK_INDICATOR, "", ""],
			$excerpt);
		return trim($excerpt);
	}

	protected function prepare_thumbnail($post) {
		if (!has_post_thumbnail($post->ID)) {
			return null;
		}
		$image_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID));
		return $image_src[0];
	}

	protected function prepare_url($post){
		$page_id = $post->ID;
		$date = $post->post_modified_gmt;
		$date_1 = get_date_from_gmt($date,'Y/m/d');
		$date_2 = get_date_from_gmt($date,'Y/m');
																// EXAMPLES
		$url = get_permalink ($page_id, false);					// http://localhost/wordpress/muenchen/de/gesundheit/aerzte-und-ueberweisungen/
		$url_site = get_site_url();								// http://localhost/wordpress/muenchen
		$url_page = get_page_uri($page_id);						// 									      gesundheit/aerzte-und-ueberweisungen/
		$url_page_id = $post->guid;								// http://localhost/wordpress/muenchen/?page_id=67
		$url_date_1_name = $url_site.'/'.$date_1.'/'.$url_page;	// http://localhost/wordpress/muenchen/2015/10/14/gesundheit/aerzte-und-ueberweisungen
		$url_date_2_name = $url_site.'/'.$date_2.'/'.$url_page;	// http://localhost/wordpress/muenchen/2015/10/gesundheit/aerzte-und-ueberweisungen

		return [
			'url' => $url,
			'url_site' => $url_site,
			'url_page' => $url_page,
			'url_page_id' => $url_page_id,
			'url_date_1_name' => $url_date_1_name,
			'url_date_2_name' => $url_date_2_name,
		];
	}



	private function remove_read_more_link() {
		add_filter('excerpt_more', [$this, 'excerpt_no_read_more_link']);
	}

	public function excerpt_no_read_more_link() {
		return "";
	}
}

add_filter('get_delete_post_link', function ($link) {
	return str_replace("action=delete", "action=trash", $link);
});

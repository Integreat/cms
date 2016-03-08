<?php

class TranslationWpmlHelper {
	/**
	 * @param $post
	 * @param $target_language_code
	 * @return int|null the id of the post in the target language or null if no such post exists
	 */
	public function get_translated_post_id($post, $target_language_code) {
		return apply_filters('wpml_object_id', $post->ID, $post->post_type, FALSE, $target_language_code);
	}

	public function link_wpml($source_post_id, $source_post_type, $translated_post_id, $language_code) {
		global $sitepress;
		$wpml_post_type = 'post_' . $source_post_type;
		$source_trid = $sitepress->get_element_trid($source_post_id, $wpml_post_type);
		if (!$source_trid) {
			throw new RuntimeException("No source trid found for $wpml_post_type $source_post_id");
		}
		$sitepress->set_element_language_details($translated_post_id, $wpml_post_type, $source_trid, $language_code);
	}
	
	public function get_translation_post_parent( $post_id, $source_language_code, $target_language_code ) {
		global $wpdb;

		$query = "SELECT parent_target_translations.element_id
                    FROM {$wpdb->prefix}icl_translations parent_target_translations
                        JOIN {$wpdb->prefix}icl_translations post_translations
                                ON post_translations.element_id = '$post_id'
                        JOIN {$wpdb->prefix}icl_translations source_translations
                                ON source_translations.trid = post_translations.trid
                                AND source_translations.language_code = '$source_language_code'
                        JOIN {$wpdb->posts} posts
                                ON posts.ID = source_translations.element_id
                        JOIN {$wpdb->prefix}icl_translations parent_source_translations
                                ON parent_source_translations.element_id = posts.post_parent
                                ON parent_source_translations.trid = parent_target_translations.trid
                    WHERE parent_target_translations.language_code = '$target_language_code'";

		$query = "SELECT post_parent, post_status FROM $wpdb->posts p LEFT JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID WHERE t.trid IN (SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = '$post_id') AND t.language_code = '$source_language_code'";
		$result = $wpdb->get_results($query);
		
		if($result[0]->post_status == 'inherit')
		{
			$source_id = $result[0]->post_parent;
			$query = "SELECT post_parent, post_status FROM $wpdb->posts WHERE ID = '$source_id'";
			$result = $wpdb->get_results($query);
		}
		
		$source_parent_id = $result[0]->post_parent;
		
		$query = "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid IN (SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = '$source_parent_id') AND language_code = '$target_language_code'";
		$result = $wpdb->get_results($query);
		$translation_parent_id = $result[0]->element_id;

		return $translation_parent_id;
	}
}

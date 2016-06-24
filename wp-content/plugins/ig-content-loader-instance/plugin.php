<?php
/**
 * Plugin Name: Content Loader Instance
 * Description: Load content from another instance
 * Version: 0.1
 * Author: Sven Seeberg
 * Author URI: https://github.com/Integreat
 * License: MIT
 */


/**
 * Get Sprungbrett JSON-DATA, transform it to html code (cl_sb_json_to_html()) and send it to base-plugin (cl_save_content) with Parameters $parent_id , $html and $blog_id
 *
 */
function cl_in_update_content( $parent_id, $meta_value, $blog_id ) {

    // sprungbrett praktika -> ig-content-loader-sprungbrett
    if( $meta_value == "Sprungbrett Praktika" ) {
        
        $json = file_get_contents('http://localhost/json.txt');
        $json = json_decode($json, TRUE);
        $html = cl_sb_json_to_html($json);

        cl_save_content( $parent_id, $html, $blog_id);

        return;  
        
    }
}
add_action( 'cl_update_content','cl_sb_update_content', 10, 3 );

// registriert plugin in base and return meta infos
function cl_in_metabox_item( $array ) {
    $array[] = json_decode('{"id": "ig-content-loader-instance", "name": "Seite aus Fremdinstanz", "ajax_callback": "cl_in_metabox_ajax"}');
    return $array;
}
add_filter( 'cl_metabox_item', 'cl_in_metabox_item' );

function cl_in_metabox_ajax() {
	$selected_instance = $_POST['cl-metabox-instance-id'];
	$selected_post = $_POST['cl-metabox-instance-post-id'];

	/*
	 * Neither instance nor post has been selected. Return instance dropdown.
	 */
	if ( !$selected_instance && !$selected_post ) {
		echo cl_in_generate_instance_dropdown();
	}
	/*
	 * An instance has been selected but no page. Therefore return a dropdown with all pages in instance 
	 */
	elseif ( $selected_instance && !$selected_post ) {
		echo cl_in_generate_post_dropdown( $blog_id );
	}
	/*
	 * Instance and post selected. Needs no ajax but need to save and save_post
	 */ 
	elseif ( $selected_instance && $selected_post ) {
		
	}
	
}

function cl_in_add_js() {
?>
	<script type="text/javascript" >
	/*var sel = document.getElementById('sel');
	sel.onchange = function() {
		var show = document.getElementById('show');
		show.innerHTML = this.value;
	}
	jQuery(document).ready(function($) {

		var data = {
			'action': 'my_action',
			'whatever': 1234
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			alert('Got this from the server: ' + response);
		});
	});*/
	
	</script> <?php
}
add_action( 'cl_add_js', 'cl_in_add_js' );

function cl_in_instance_dropdown() {
	// get all blogs / instances (augsburg, regensburg, etc)
	$query = "SELECT blog_id FROM wp_blogs where blog_id > 1";
	$all_blogs = $wpdb->get_results($query);
	foreach( $all_blogs as $blog ){

	}
}
add_action( 'wp_ajax_cl_in_instance_dropdown', 'cl_in_instance_dropdown' );

function cl_in_post_dropdown() {
	$blog_id = $_POST['cl_in_blog_id'];
	// query all objects in db with meta_key = ig-content-loader-base
	$results = "select * from ".$wpdb->base_prefix.$blog_id."_postmeta where meta_key = 'ig-content-loader-base'";

	$result = $wpdb->get_results($results);

	foreach($result as $item) {
		$parent_id = "".$item->post_id;
		$meta_val = "".$item->meta_value;
		$blog_name = get_blog_details($blog_id)->blogname;

		do_action('cl_update_content', $parent_id, $meta_val, $blog_id, $blog_name);
	}
}
add_action( 'wp_ajax_cl_in_post_dropdown', 'cl_in_post_dropdown' );

?>
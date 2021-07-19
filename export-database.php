<?php
	$debug = false;

	$configuration = parse_ini_file("config.ini", true);

	// ** MySQL settings - You can get this info from your web host ** //
	$db_configuration = $configuration['database'];
	/** The name of the database for WordPress */
	define('DB_NAME', $db_configuration['name']);

	/** MySQL database username */
	define('DB_USER', $db_configuration['user']);

	/** MySQL database password */
	define('DB_PASSWORD', $db_configuration['password']);

	/** MySQL hostname */
	define('DB_HOST', $db_configuration['host']);

	/** Database Charset to use in creating database tables. */
	define('DB_CHARSET', $db_configuration['charset']);

	/** The Database Collate type. Don't change this if in doubt. */
	define('DB_COLLATE', $db_configuration['collate']);

	$table_prefix = 'wp_';

	define('REPL_DOMAIN_OLD', "cms.integreat-app.de");
	define('REPL_DOMAIN_NEW', "cms-dev.integreat-app.de");
	define('REPL_PATH_OLD', "/");
	define('REPL_PATH_NEW', "/");
	$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

	function now() {
		$objDateTime = new DateTime('NOW');
		return $objDateTime->format('c');
	}

	class MPTT {
		function __construct( $pk_offset = null, $id = null ) {
			$this->id = $id;
			$this->tree = array();
			if ( $pk_offset ) {
				$this->pk_counter = $pk_offset;
			} else {
				$this->pk_counter = 0;
			}
		}

		function add_node( $node_id, $parent_node_id = null ) {
			if ( sizeof( $this->tree ) == 0 && $parent_node_id == null ) {
				$this->tree[]	= array( "id" => $node_id, "parent" => null, "parent_pk" => null, "left" => 1, "right" => 2, "level" => 0, "pk" => $this->pk_counter );
			} elseif ( sizeof( $this->tree) > 0 && $parent_node_id ) {
				$parent = $this->get_parent( $parent_node_id );
				$this->increase_counts( $parent["right"] );
				$this->tree[] = array( "id" => $node_id, "parent" => $parent_node_id, "parent_pk" => $parent["pk"], "left" => $parent["right"], "right" => ( $parent["right"] + 1 ), "level" => $parent["level"] + 1, "pk" => $this->pk_counter );
			} else {
				return false;
			}
			$this->pk_counter++;
			return true;
		}

		function get_parent( $parent_node_id ) {
			for ( $i = 0; $i < sizeof( $this->tree ); $i++ ) {
				if ( $this->tree[$i]["id"] == $parent_node_id ) {
					return $this->tree[$i];
				}
			}
		}

		function increase_counts( $num ) {
			for ( $i = 0; $i < sizeof( $this->tree ); $i++ ) {
				if ( $this->tree[$i]["left"] >= $num ) {
					$this->tree[$i]["left"] = $this->tree[$i]["left"] + 2;
				}
				if ( $this->tree[$i]["right"] >= $num ) {
					$this->tree[$i]["right"] = $this->tree[$i]["right"] + 2;
				}
			}
		}

		function get_node( $node_id ) {
			foreach ( $this->tree as $key => $node ) {
				if ( $node["id"] == $node_id ) {
					return $node;
				}
			}
		}
  }

	abstract class DjangoModel {
		public $pk;
		public $fields = array();
		public static $pk_counter = array();

		function __construct( $pk = null ) {
			if ( !array_key_exists( $this->model, self::$pk_counter ) )
				self::$pk_counter[$this->model] = 1;
			if ( $pk == null )
				$this->pk = self::$pk_counter[$this->model];
			else
				$this->pk = $pk;
			self::$pk_counter[$this->model] ++;
		}

		function is_valid() {
			foreach ( $fields as $item ) {
				if ( $item == null )
					return false;
			}
			return true;
		}
	}

	class Region extends DjangoModel {
		public $model = "cms.region";

		function __construct( $blog ) {
			parent::__construct( (int)$blog->blog_id );
			$this->init_fields( $blog );
		}

		function init_fields( $blog ) {
			$this->fields["name"] = $blog->get_blog_option( "blogname" );
			$this->fields["slug"] = ( $blog->get_blog_option( "blogname") == "Integreat" ? "integreat" : str_replace( "/", "", $blog->path ) );
			$this->fields["aliases"] = ( $blog->get_integreat_setting( "aliases" ) != null ? $blog->get_integreat_setting( "aliases" ) : array() );
			$this->fields["status"] = ( $blog->get_integreat_setting( "disabled" ) == 1 ? "ARCHIVED" : ( $blog->get_integreat_setting( "hidden" ) == 0 ? "ACTIVE" : "HIDDEN" ) );
			$this->fields["latitude"] = $blog->get_integreat_setting( "latitude" );
			$this->fields["longitude"] = $blog->get_integreat_setting( "longitude" );
			$this->fields["postal_code"] = ( $blog->get_integreat_setting( "plz" ) != null ? $blog->get_integreat_setting( "plz" ) : 1);
			$this->fields["administrative_division"] = ( $blog->get_integreat_setting( "prefix" ) == "Landkreis" ? "RURAL_DISTRICT" : "MUNICIPALITY" );
			$this->fields["events_enabled"] = true;
			$this->fields["chat_enabled"] = true;
			$this->fields["push_notifications_enabled"] = ( $blog->get_integreat_setting( "push_notifications" ) == 1 ? true : false );
			$this->fields["admin_mail"] = "info@integreat-app.de";
			$this->fields["created_date"] = now();
			$this->fields["last_updated"] = now();
			$this->fields["statistics_enabled"] = ( strpos( $blog->get_blog_option( "active_plugins" ), "wp-piwik" ) ? true : false );
			$this->fields["matomo_token"] = ( $blog->get_blog_option( "wp-piwik_global-piwik_token" ) != null ? $blog->get_blog_option( "wp-piwik_global-piwik_token" ) : "");
		}
	}

	class Language extends DjangoModel {
		public $model = "cms.language";

		function __construct( $language ) {
			parent::__construct();
			$this->init_fields( $language );
		}

		function init_fields( $language ) {
			$this->fields = array(
				"slug"=>utf8_encode( $language["code"] ),
				"bcp47_tag"=>( $language["tag"] == "uz-uz" && $language["code"] == "ur" ? "ur-ur" : utf8_encode($language["tag"]) ),
				"english_name"=>utf8_encode( $language["english_name"] ),
				"native_name"=>utf8_encode( $language["native_name"] ),
				"text_direction"=>(in_array($language["code"], array('ar','fa','ckb')) ? "RIGHT_TO_LEFT" : "LEFT_TO_RIGHT"),
				"table_of_contents"=>"Inhaltsverzeichnis",
				"created_date"=>now(),
				"last_updated"=>now(),
			);
		}
	}

	class LanguageTreeNode extends DjangoModel {
		public $model = "cms.languagetreenode";

		function __construct( $blog, $language, $active, $mptt_node ) {
			$this->pk = $mptt_node["pk"];
			$this->init_fields( $blog, $language, $active, $mptt_node );
		}

		function init_fields( $blog, $language, $active, $mptt_node ) {
			$this->fields = array(
				"language"=>$language->pk,
				"parent"=>$mptt_node["parent_pk"],
				"region"=>$blog->blog_id,
				"active"=>$active,
				"created_date"=>now(),
				"last_updated"=>now(),
				"lft"=>$mptt_node["left"],
				"rght"=>$mptt_node["right"],
				"tree_id"=>$blog->blog_id,
				"level"=>$mptt_node["level"],
			);
		}
	}

	class Page extends DjangoModel {
		public $model = "cms.page";

		function __construct( $blog, $mptt_node, $page_tree_counter ) {
			$this->pk = $mptt_node["pk"];
			$this->init_fields( $blog, $mptt_node, $page_tree_counter );
		}

		function init_fields( $blog, $mptt_node, $page_tree_counter ) {
			$this->fields = array(
				"parent"=>$mptt_node["parent_pk"],
				"icon"=>null,
				"region"=>(int)$blog->blog_id,
				"explicitly_archived"=>false,
				"mirrored_page"=>null,
				"mirrored_page_first"=>null,
				"created_date"=>now(),
				"lft"=>$mptt_node["left"],
				"rght"=>$mptt_node["right"],
				"tree_id"=>$page_tree_counter,
				"level"=>$mptt_node["level"],
				"editors"=>[],
				"publishers"=>[],
			);
		}
	}

	class PageTranslation extends DjangoModel {
		public $model = "cms.pagetranslation";

		function __construct( $translation ) {
			parent::__construct();
			$this->init_fields( $translation );
		}

		function init_fields( $translation ) {
			$this->fields = array(
				"page"=>$translation["page"],
				"slug"=>utf8_encode($translation["slug"]),
				"title"=>mb_substr(utf8_encode($translation["title"]), 0, 250),
				"status"=>$translation["status"],
				"text"=>utf8_encode($translation["text"]),
				"language"=>$translation["language"],
				"currently_in_translation"=>$translation["currently_in_translation"],
				"version"=>$translation["version"],
				"minor_edit"=>$translation["minor_edit"],
				"creator"=>$translation["creator"],
				//"created_date"=>str_replace("0000-00-00","1970-01-01",$translation["created_date"]),
				"last_updated"=>str_replace("0000-00-00","1970-01-01",$translation["last_updated"]),
			);
		}
	}

	class DjangoFixtures {

		function __construct() {
			$this->object_list = array();
		}

		function append( $object ) {
			array_push( $this->object_list, $object );
		}

		function is_valid() {
			foreach ( $this->object_list as $object ) {
				if ( $object->is_valid == false ) {
					return false;
				}
				return true;
			}
		}

		function get_language_by_slug( $code ) {
			foreach ( $this->object_list as $object ) {
				if ( $object->model == "cms.language"  &&  $object->fields["slug"] == $code ) {
					return $object;
				}
			}
			return null;
		}

		function get_languagetreenode_by_language_pk( $blog_id, $pk ) {
			foreach ( $this->object_list as $object ) {
				if ( $object->model == "cms.languagetreenode"  && $object->pk == $pk && $object->fields["tree_id"] == $blog_id ) {
					return $object;
				}
			}
			return null;
		}

		function dump() {
			return json_encode( $this->object_list, JSON_PRETTY_PRINT );
		}
	}

	class WPBlog {
		function __construct( $db, $blog_id, $path ) {
			$this->db = $db;
			$this->blog_id = $blog_id;
			$this->path = $path;
			if ( $blog_id == 1 ) {
				$this->dbprefix = "wp_";
			} else {
				$this->dbprefix = "wp_".$blog_id."_";
			}
		}

		function get_blog_option( $option_name ) {
			$query = "SELECT option_value FROM " . $this->dbprefix . "options WHERE option_name='" . $option_name . "'";
			$result = $this->db->query( $query );
			while ( $row = $result->fetch_object() ) {
				return utf8_encode($row->option_value);
			}
		}

		function get_integreat_setting( $alias ) {
			$query = "SELECT value FROM " . $this->dbprefix . "ig_settings_config c LEFT JOIN wp_ig_settings s ON c.setting_id=s.id WHERE alias='$alias'";
			$result = $this->db->query( $query );
			while ( $row = $result->fetch_object() ) {
				return utf8_encode($row->value);
			}
		}

		function get_postmeta( $post_id, $meta_key ) {
			$query = "SELECT meta_value FROM " . $this->dbprefix . "postmeta WHERE post_id=$post_id AND meta_key='" . $meta_key . "'";
			$result = $this->db->query( $query );
			while ( $row = $result->fetch_object() ) {
				return $row->option_value;
			}
		}

		function get_languages() {
			$query = "SELECT code, english_name, name AS native_name, tag FROM " . $this->dbprefix ."icl_languages l LEFT JOIN " . $this->dbprefix . "icl_languages_translations t ON l.code=t.language_code WHERE t.display_language_code=l.code";
			$result = $this->db->query( $query );

			while ( $row = $result->fetch_object() ) {
				$languages[$row->code] = array( "code"=>$row->code, "english_name"=>$row->english_name, "native_name"=>$row->native_name, "tag"=>$row->tag );
			}
			return $languages;
		}

		function get_used_languages() {
			$query = "SELECT m.code, active FROM " . $this->dbprefix ."icl_locale_map m LEFT JOIN " . $this->dbprefix ."icl_languages l ON m.code=l.code";
			$result = $this->db->query( $query );
			$languages_active = array();
			while ( $row = $result->fetch_object() ) {
				$languages_active[$row->code] = $row->active;
			}
			return $languages_active;
		}

		function get_default_language() {
			$query = "SELECT option_value FROM " . $this->dbprefix ."options WHERE option_name='icl_sitepress_settings'";
			$result = $this->db->query( $query );
			while ( $row = $result->fetch_object() ) {
				$data = preg_replace_callback( '!s:(\d+):"(.*?)";!', function($m) {
					return 's:'.strlen($m[2]).':"'.$m[2].'";';
				}, $row->option_value );
				$var = unserialize( $data );
				if ( array_key_exists( "default_language", $var ) ) {
					return $var["default_language"];
				} else {
					return "de";
				}
			}
			return null;
		}

		function generate_language_tree( $treenode_pk_counter ) {
			$language_tree = new MPTT( $treenode_pk_counter );

			$used_languages = $this->get_used_languages();
			$default_language = $this->get_default_language();
			$language_tree->add_node(  $default_language );
			unset( $used_languages[$default_language] );
			foreach ( $used_languages as $lang_code => $active ) {
				$language_tree->add_node( $lang_code, $default_language );
			}
			return $language_tree;

		}

		function get_pages_for_language( $language_code, $parents ) {
			$query = "SELECT p.ID, p.post_parent FROM " . $this->dbprefix . "posts p LEFT JOIN (SELECT * FROM " . $this->dbprefix . "icl_translations WHERE element_type='post_page') t ON t.element_id=p.ID WHERE t.language_code='$language_code' AND p.post_parent IN (" . implode( ",", $parents ) . ") ORDER BY menu_order ASC" ;
			$result = $this->db->query( $query );
			$posts = [];
			while ( $row = $result->fetch_object() ) {
				$posts[] = ["id" => $row->ID, "parent" => $row->post_parent ];
			}
			return $posts;
		}

		function generate_page_tree( $page_tree_node_pk_counter, $root_page_id, $page_tree_counter ) {
			global $page_pk_map;
			global $page_wp_map;
			global $page_mirror_map;
			/* Get pages in hierarchical order, starting with a root page */
			$new_posts = $this->get_pages_for_language( $this->get_default_language(), [ $root_page_id ] );
			$posts = $new_posts;
			while ( ! empty( $new_posts ) ) {
				$new_posts = $this->get_pages_for_language( $this->get_default_language(), array_column( $new_posts, "id" ));
				$posts = array_merge( $posts, $new_posts );
			}
			$page_tree = new MPTT( $pk_offset = $page_tree_node_pk_counter, $id = $page_tree_counter );
			if ( $page_tree->add_node( $root_page_id, null ) ) {
				$page_pk_map[$this->blog_id][$root_page_id] = $page_tree->pk_counter - 1;
				$page_wp_map[$page_tree->pk_counter - 1] = array( "blog_id" => $this->blog_id, "post_id" => $root_page_id );
				$page_mirror_map[$this->blog_id][$root_page_id] = $this->get_mirrored_page( $root_page_id );
			} else {
				return false;
			}
			foreach ( $posts as $post ) {
				if ( $page_tree->add_node( $post["id"], $post["parent"] ) ) {
					$page_pk_map[$this->blog_id][$post["id"]] = $page_tree->pk_counter - 1;
					$page_wp_map[$page_tree->pk_counter - 1] = array( "blog_id" => $this->blog_id, "post_id" => $post["id"] );
					$page_mirror_map[$this->blog_id][$post["id"]] = $this->get_mirrored_page( $post["id"] );
				}
			}
			return $page_tree;
		}

		/* Get the post ID that is the first version (post parent) of the page in the given language */
		function get_page_language_root_id( $post_id, $language ) {
			$query = "SELECT element_id FROM " . $this->dbprefix . "icl_translations WHERE trid IN (SELECT trid FROM " . $this->dbprefix . "icl_translations WHERE element_type='post_page' AND element_id='" . $post_id . "') AND language_code = '" . $language . "'";
			$result = $this->db->query( $query );
			while ( $row = $result->fetch_object() ) {
				return $row->element_id;
			}
			return false;
		}

		function get_mirrored_page( $post_id ) {
			$query = "SELECT * FROM " . $this->dbprefix . "postmeta WHERE post_id=" . $post_id . " AND (meta_key='ig-attach-content-position' OR meta_key='ig-attach-content-blog' OR meta_key='ig-attach-content-page') AND meta_value IS NOT Null" ;
			$result = $this->db->query( $query );
			$source_page = array();
			while ( $row = $result->fetch_object() ) {
				if ( $row->meta_key == "ig-attach-content-position" ) {
					$source_page["pos"] = $row->meta_value;
				}
				elseif ( $row->meta_key == "ig-attach-content-blog" ) {
					$source_page["blog_id"] = $row->meta_value;
				}
				elseif ( $row->meta_key == "ig-attach-content-page" ) {
					$source_page["post_id"] = $row->meta_value;
				}
			}
			return $source_page;
		}

		/* Create array of all revisions in all translations */
		function get_page_translations( $mptt_node, $language, $language_pk ) {
			$parent = $this->get_page_language_root_id( $mptt_node["id"], $language );
			if ( !$parent ) {
				return [];
			}
			$query = "SELECT * FROM " . $this->dbprefix . "posts WHERE post_parent = " . $parent . " OR ID = " . $parent . " ORDER BY post_modified ASC" ;
			$result = $this->db->query( $query );
			$translations = [];
			$version = 1;
			$status = "DRAFT";
			while ( $row = $result->fetch_object() ) {
				if ( $row->post_status == "auto-draft" || $row->post_status == "draft" )
					$status = "DRAFT";
				elseif ( $row->post_status == "private" || $row->post_status == "trash" )
					$status = "REVIEW";
				elseif ( $row->post_status == "publish" )
					$status = "PUBLIC";
				else
					$status = $status; // inherit
				$page_translations[] = new PageTranslation([
					"page"=>$mptt_node["pk"],
					"slug"=>$row->post_name,
					"title"=>$row->post_title,
					"status"=>$status,
					"text"=>$row->post_content,
					"language"=>$language_pk,
					"currently_in_translation"=>false,
					"version"=>$version,
					"minor_edit"=>false,
					"creator"=>null,
					"created_date"=>$row->post_date_gmt,
					"last_updated"=>$row->post_modified_gmt,
				]);
				$version++;
			}
			return $page_translations;
		}
	}

	/* Get all blog IDs */
	$query = "SELECT blog_id, path FROM " . $table_prefix . "blogs";
	$result = $db->query( $query );
	while ( $row = $result->fetch_object() ) {
		$blogs[] = new WPBlog( $db, $row->blog_id, $row->path );
	}
	$fixtures = new DjangoFixtures();
	$languages = array();
	$language_pk_map = array(); // map a language code to the Django primary key
	$lang_tree_node_pk_counter = 1;
	$page_tree_node_pk_counter = 1;
	$page_tree_counter = 1;
	$page_pk_map = array(); // map a blog and post ID to the Django page primary key
	$page_wp_map = array(); // map Django page PKs to WP blog and post IDs
	$page_mirror_map = array(); // Map WP source to target page. source is the content source, target the page were the content is included.
	                            // structure: $page_mirror_map[tgt_blog_id][tgt_post_id] = array("blog_id"=>src_blog_id, "post_id"=>src_post_id, "pos_beg":bool).

	foreach ( $blogs as $blog ) {
		$region = new Region( $blog );
		fwrite(STDERR, "Exporting blog " . $blog->blog_id . "\n");
		$fixtures->append( $region );

		/* get available languages */
		foreach ( $blog->get_languages() as $blog_language ) {
			if( !array_key_exists( $blog_language["code"], $languages ) ) {
				$lang_fixture = new Language( $blog_language );
				$language_pk_map[$blog_language["code"]] = $lang_fixture->pk;
				$fixtures->append( $lang_fixture );
				$languages[$blog_language["code"]] = $blog_language;
			}
		}

		/* get used languages and create tree nodes */
		$language_tree = $blog->generate_language_tree( $lang_tree_node_pk_counter );
		$lang_tree_node_pk_counter = $language_tree->pk_counter;
		//fwrite(STDERR, "TreeNode PK Counter: " . $lang_tree_node_pk_counter . "\n");

		foreach ( $blog->get_used_languages() as $used_language => $active) {
			$mptt_node = $language_tree->get_node( $used_language );
			$lang = $fixtures->get_language_by_slug($used_language);
			if ( ! $lang ) { fwrite(STDERR, "Blog " . $blog->blog_id . ": Skipping language $used_language.\n"); continue; }
			$tree_node = new LanguageTreeNode( $blog, $lang, $active, $mptt_node );
			$fixtures->append( $tree_node );
		}
		
		/* get level 0 pages and generate a page tree for them */
		$new_posts = $blog->get_pages_for_language( $blog->get_default_language(), [ 0 ] );
		foreach ( $new_posts as $root_post ) {
			$page_tree = $blog->generate_page_tree( $page_tree_node_pk_counter, $root_post["id"], $page_tree_counter );
			$page_tree_node_pk_counter = $page_tree->pk_counter;
			foreach ( $page_tree->tree as $mptt_node ) {
				$page_tree_node = new Page( $blog, $mptt_node, $page_tree_counter );
				$fixtures->append( $page_tree_node );
				foreach ( $blog->get_used_languages() as $used_language => $active) {
					if ( ! array_key_exists( $used_language, $language_pk_map ) ) continue;
					foreach ( $blog->get_page_translations( $mptt_node, $used_language, $language_pk_map[$used_language] ) as $page_translation ) {
						$fixtures->append( $page_translation );
					}
				}
			}
			$page_tree_counter++;
		}
		//if ( $blog->blog_id >= 2 ) { break; }
	}

	// after all pages have been exported, loop over all fixtures with type page again and look up mirrored page PK
	fwrite(STDERR, "Fixing mirrored pages foreign keys.");
	foreach ( $fixtures->object_list as $key => $object ) {
		if ( $object->model == "cms.page") {
			$source_page = $page_mirror_map[$page_wp_map[$object->pk]["blog_id"]][$page_wp_map[$object->pk]["post_id"]];
			if ( ! $source_page ) continue;
			$object->fields["mirrored_page"] = $page_pk_map[$source_page["blog_id"]][$source_page["post_id"]];
			$object->fields["mirrored_page_first"] = ( $source_page["pos"] == "end" ? 0 : 1 );
			$fixtures->object_list[$key] = $object;
		}
	}

	fwrite(STDERR, "Dumping fixtures.");
	echo($fixtures->dump());
?>

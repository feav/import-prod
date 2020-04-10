<?php

/*
  Plugin Name: Flux Reader
  Plugin URI: 
  Description: Permettre la lecture des flux
  Version: 1.0
  Author: ARS GROUP
  Author URI: http://www.ars-agency.com
 */

define('WPIF_PLUGIN_FILE',__FILE__);
define('WPIF_DIR', plugin_dir_path(__FILE__));	 
define('WPIF_URL', plugin_dir_url(__FILE__));
define('WPIF_API_URL_SITE', get_site_url() . "/");

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

class WpImportFlux {
	private $post_type; 
	private $stape  = array();
	function __construct() {
		$this->post_type = 'wp_product_import';
		$this->stape  = array(
			'wait_download' => -1,
			'downloading' => 1,
		);

		add_action( 'init', array(&$this,'register_post_types'), 0 );
		add_filter( 'cron_schedules', array(&$this,'wpshout_add_cron_interval') );

		add_action('add_meta_boxes', array(&$this,'add_custom_box'));
		add_action('save_post', array(&$this,'save_postdata'));
	
		add_action('manage_'.$this->post_type.'_posts_custom_column', array(&$this, 'custom_flux_product_columns'), 15, 3);
		add_filter('manage_'.$this->post_type.'_posts_columns', array(&$this, 'flux_product_columns'), 15, 1);

		add_action("admin_menu", array(&$this,"plugin_setup_menu"));
		
		add_action( 'wp_ajax_import_flux_ajax_request', array(&$this,'ajax_callback') );
  		add_action( 'wp_ajax_nopriv_import_flux_ajax_request', array(&$this,'ajax_callback') );
  		

		add_action('cron_flux_reader', array(&$this,'flux_reader') );
		$args = array( false );
		if ( ! wp_next_scheduled( 'cron_flux_reader', $args ) ) {
		    wp_schedule_event( time(), 'everyminute', 'cron_flux_reader', $args );
		}
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array(&$this,'handle_custom_query_var'), 10, 2 );

	}  
	function ajax_callback() {
		    $module = "";
		    if(isset($_POST['function'])){
		    	$module	= trim($_POST['function']);
		    }
		    if(isset($_GET['function'])){
		    	$module	= trim($_GET['function']);
		    }

		    if($module=="remove_element"){
		 		unlink(__DIR__.'/flux/'.$_GET['url_file']);
		    	echo json_encode(array());
		    }else if($module=="get_files_to_update"){
		    	$dir_handle = opendir(WPIF_DIR.'/'.$_GET['dir']); 
		    	$list_files = array();
		    	while(($file_name = readdir($dir_handle)) !== false) {
		    		if($file_name !== '.' && $file_name !== '..' && $file_name !== 'archive' && $file_name !== '.DS_Store'){
		    			$list_files[] = $file_name;
		    		}
		    	}
		    	closedir($dir_handle);
		    	echo json_encode($list_files);
		    }
		    exit;
		  }

	function plugin_setup_menu(){
		add_submenu_page(
	        'edit.php?post_type='.$this->post_type,
	        __( 'Flux Importes' ),
	        __( 'Flux Importes' ),
	        'manage_woocommerce', // Required user capability
	        'import-product',
	        array(&$this,"import_flux_template")
	    );

	}
	function flux_product_columns($defaults ){
		$defaults['product_flux_etat'] = esc_html__('Etat du Flux', 'wp_manifestation_manage');
		$defaults['product_flux_url'] = esc_html__('Lien du Flux', 'wp_manifestation_manage');
		return $defaults;
	}

    function custom_flux_product_columns($column_name, $postid){
		if ( $column_name == 'product_flux_etat' ) {
		 	$name = get_post_meta($postid,  'flux_stape',  true );
			if($name ==-1){
				echo "<span style='background: #8f63f5;color: white;padding: 6px;'>En attente de Telechargement</span>";
			}else if($name == 1){
				echo "<span style='background: #f1cc0b;color: white;padding: 6px;'> Telechargement en Cours</span>";
			}else if($name ==2){
				echo "<span style='background: #63f597;color: white;padding: 6px;'>Telechargement Termine</span>";
			}else if($name == 3){
				echo "<span style='background: #0b4ff1;color: white;padding: 6px;'> Importation de Produits en cours</span>";
			}else if($name == 4){
				echo "<span style='background: #439c0e;color: white;padding: 6px;'> Importation de Produits Terminee</span>";
			}


		}else if ( $column_name == 'product_flux_url' ) {
		 	$name = get_post_meta($postid,  'flux_url',  true);
			echo $name;

		}
	}
	function runDownload(){
 		$args = array(
	        'meta_query'        => array(
	            array(
	                'key'       => 'flux_stape',
	                'value'     => -1
	            )
	        ),
	        'post_type'         => $this->post_type,
	        'posts_per_page'    => '1'
	    );
	   	
	    // run query ##
	    $posts = get_posts( $args );

	   	if(count($posts)){	
	   		$post_id = $posts[0]->ID;
			update_post_meta($post_id,"flux_stape",1);
			$url = get_post_meta($post_id, 'flux_url', true);
			$content = file_get_contents($url);
			file_put_contents(WPIF_DIR."/flux/flux_".$post_id.".xml", $content);
			update_post_meta($post_id,"flux_stape",2);
			return true;
	   	}
	   	return false;
	}
	function save_postdata($post_id){
	    if (get_post_type($post_id) === $this->post_type) {
		    $metas = array("flux_stape","flux_url");
		    foreach ($_POST as $key => $value) {
		   		if (in_array($key, $metas)) {
				   update_post_meta($post_id,$key,$value);
				}
	    	}
		}
	}
	function add_custom_box(){
		$screens = [$this->post_type, 'wporg_cpt'];
	    foreach ($screens as $screen) {
		    add_meta_box(
		        'manifestation_',           // Unique ID
		        'Details de la Manifestation',  // Box title
		        array($this,'wporg_custom_box_html'),  // Content callback, must be of type callable
		        $screen                   // Post type
	        );
	    }
	}
	function wporg_custom_box_html($post){
		include(WPIF_DIR.'template/html/admin-flux-detai.php');
	}
	function import_flux_template($post){
		include(WPIF_DIR.'template/html/admin-flux-import.php');

	}
	// Register Custom Post Type
	function register_post_types() {

			$labels = array(
				'name'                  => _x( 'Flux Produits', 'Post Type General Name', 'wp_manifestation_manage' ),
				'singular_name'         => _x( 'Flux Produits', 'Post Type Singular Name', 'wp_manifestation_manage' ),
				'menu_name'             => __( 'Flux Produits', 'wp_manifestation_manage' ),
				'name_admin_bar'        => __( 'Flux Produits', 'wp_manifestation_manage' ),
				'archives'              => __( 'Archive des Flux Produits', 'wp_manifestation_manage' ),
				'attributes'            => __( 'Attributs d\'une Flux Produits', 'wp_manifestation_manage' ),
				'parent_item_colon'     => __( 'Parent de la Flux Produits', 'wp_manifestation_manage' ),
				'all_items'             => __( 'Toutes les Flux Produits', 'wp_manifestation_manage' ),
				'add_new_item'          => __( 'Ajouter une Flux Produits', 'wp_manifestation_manage' ),
				'add_new'               => __( 'Ajouter une nouvelle', 'wp_manifestation_manage' ),
				'new_item'              => __( 'Nouvelle Flux Produits', 'wp_manifestation_manage' ),
				'edit_item'             => __( 'Editer la Flux Produits', 'wp_manifestation_manage' ),
				'update_item'           => __( 'Mettre a jour la Flux Produits', 'wp_manifestation_manage' ),
				'view_item'             => __( 'Voir la Flux Produits', 'wp_manifestation_manage' ),
				'view_items'            => __( 'Voir les Flux Produits', 'wp_manifestation_manage' ),
				'search_items'          => __( 'Rechercher une Flux Produits', 'wp_manifestation_manage' ),
				'not_found'             => __( 'Flux Produits non trouvee', 'wp_manifestation_manage' ),
				'not_found_in_trash'    => __( 'Pas trouve dans la corbeille', 'wp_manifestation_manage' ),
				'featured_image'        => __( 'Multiples Images', 'wp_manifestation_manage' ),
				'set_featured_image'    => __( 'Modifer l\'image', 'wp_manifestation_manage' ),
				'remove_featured_image' => __( 'Retirer l\'image', 'wp_manifestation_manage' ),
				'use_featured_image'    => __( 'Utiliser comme image', 'wp_manifestation_manage' ),
				'insert_into_item'      => __( 'inserer dans la Flux Produits', 'wp_manifestation_manage' ),
				'uploaded_to_this_item' => __( 'Charger dans la Flux Produits', 'wp_manifestation_manage' ),
				'items_list'            => __( 'Liste de Flux Produits', 'wp_manifestation_manage' ),
				'items_list_navigation' => __( 'Les Flux Produits', 'wp_manifestation_manage' ),
				'filter_items_list'     => __( 'Filtrer les Flux Produits', 'wp_manifestation_manage' ),
			);
			$args = array(
				'label'                 => __( 'Flux Produits', 'wp_manifestation_manage' ),
				'description'           => __( 'Les differentes Flux Produits', 'wp_manifestation_manage' ),
				'labels'                => $labels,
				'supports'              => array( 'title' ),
				'taxonomies'            => array( ),
				'hierarchical'          => true,
				'public'                => false,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'menu_position'         => 10,
				'menu_icon'             => 'dashicons-admin-comments',
				'show_in_admin_bar'     => true,
				'show_in_nav_menus'     => true,
				'can_export'            => true,
				'has_archive'           => 'flux-produits',
				'exclude_from_search'   => false,
				'publicly_queryable'    => true,
				'capability_type'       => 'post',
				'show_in_rest'          => true,
			);
			register_post_type( $this->post_type, $args );

		}
	function flux_reader(){
	
		file_put_contents(WPIF_DIR."/cron/cron_last_update.txt",date('y-m-d h:i:s') );

		global $woocommerce;
		if(!$this->runDownload()){
			$url = 'https://chichamania.belsis.cm/flux_example.xml';
			$args = array(
		        'meta_query'        => array(
		            array(
		                'key'       => 'flux_stape',
		                'value'     => 2
		            )
		        ),
		        'post_type'         => $this->post_type,
		        'posts_per_page'    => '1'
		    );
		   	
		    // run query ##
		    $posts = get_posts( $args );

		   	if(count($posts)){	
		   		$post_id = $posts[0]->ID;
				update_post_meta($post_id,"flux_stape",3);
				$url = WPIF_DIR."/flux/flux_".$post_id.".xml";

				try {
					
					$xml = new SimpleXMLElement($url, 0, true);
					
					$products = $xml->xpath( '/produits/produit' );

					foreach ($products as $key => $product) {

						$name = (string) $product->nom;
						$description = (string) $product->description;
						$reference_interne = (int) $product->reference_interne;
						$price = (float) $product->prix;
						$url_photo_grande = (string) $product->url_photo_grande;
						
						$categories_str = (string) $product->categorie;
						$categories = explode( '|', $categories_str);

						$category_ids = array();

						// check if parent product already exist
						$wc_products = wc_get_products( array( 'reference_interne' => $reference_interne ) );
						
						if( empty($wc_products) ){

							foreach ($categories as $key => $value) {
								$this->create_category( trim( $value ) );
								array_push( $category_ids, $this->get_category_id_by_name( trim($value) ) );
							}

							$wc_product = new WC_Product_Variable();
							$wc_product->set_name( $name );
							$wc_product->set_price( $price );
							$wc_product->set_regular_price( $price );

							$wc_product->set_category_ids( $category_ids );

							$wc_product_id = $wc_product->save();
							$this->generate_geatured_image( $url_photo_grande, $wc_product_id );

							update_post_meta( $wc_product_id, 'reference_interne', $reference_interne);

							// create variation
							$variation = new WC_Product_Variation( $wc_product_id );

							

						}
						
					}
					update_post_meta($post_id,"flux_stape",4);

				} catch (Exception $e) {
					
				}
		 	}
			
		}

	}
	function generate_geatured_image( $image_url, $post_id  ){
		    
		    $upload_dir = wp_upload_dir();
		    $image_data = file_get_contents($image_url);
		    $filename = basename($image_url);
		    if(wp_mkdir_p($upload_dir['path']))     $file = $upload_dir['path'] . '/' . $filename;
		    else                                    $file = $upload_dir['basedir'] . '/' . $filename;
		    file_put_contents($file, $image_data);

		    $wp_filetype = wp_check_filetype($filename, null );
		    $attachment = array(
		        'post_mime_type' => $wp_filetype['type'],
		        'post_title' => sanitize_file_name($filename),
		        'post_content' => '',
		        'post_status' => 'inherit'
		    );
		    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		    require_once(ABSPATH . 'wp-admin/includes/image.php');
		    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
		    $res2= set_post_thumbnail( $post_id, $attach_id );
		}


		function wpshout_add_cron_interval( $schedules ) {
		    $schedules['everyminute'] = array(
		            'interval'  => 160, // time in seconds
		            'display'   => 'Every Minute'
		    );
		    return $schedules;
		}

		function create_category( $category ){

			$term = get_term_by( 'name', $category, 'product_cat' );

			if( !$term ){ // term not found or taxonomy doesn't exist
				
				wp_insert_term( 
					$category,
					'product_cat'
				);
			}
		}

		function get_category_id_by_name( $category ){
			$term = get_term_by( 'name', $category, 'product_cat' );
			if( $term )
				return $term->term_id;	
			return 0;
		}




		function handle_custom_query_var( $query, $query_vars ) {
			if ( ! empty( $query_vars['reference_interne'] ) ) {
				$query['meta_query'][] = array(
					'key' => 'reference_interne',
					'value' => esc_attr( $query_vars['reference_interne'] ),
				);
			}

			return $query;
		}



}
new WpImportFlux();




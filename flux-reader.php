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
define( 'WPIF_PLUGIN_URL', plugins_url( '', __FILE__ ) );

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once( 'flux-helper.php' );

class WpImportFlux {
	private $post_type;
	private $stape  = array();

	private $downloading_step  = array(
		'waiting' 	=> 1,
		'inprogress'	=> 2,
		'cancelled'	=> 3,
		'completed'	=> 4
	);


	function my_register_route() {
	    register_rest_route( 'my-route', 'my-phrase', array(
	                    'methods' => 'GET',
	                    'callback' => array( $this, 'custom_phrase'),
	                )
	            );
	}

	function custom_phrase() {
		header("Content-type: application/xml",true,200);
		header("Content-Disposition: attachment; filename=data.xml");
		header("Pragma: no-cache");
		header("Expires: 0");
		echo file_get_contents( WPIF_DIR . '/tmp/flux/catv46292_20200416062124.xml') ;
		exit();
	}




	function __construct() {
		$this->post_type = 'wp_product_import';
		$this->stape  = array(
			'wait_download' => -1,
			'downloading' => 1,
		);

		add_filter( 'cron_schedules', array(&$this,'wpshout_add_cron_interval') );

		add_action( 'cron_flux_download', array( $this, 'download_flux' ), 10, 1 );

		//add_action( 'cron_flux_load_products', array( $this, 'import_products' ), 10, 1 );

		add_action( 'cron_flux_load_products_by_category', array( $this, 'import_products_by_category' ), 10, 2 );

		// add_action( 'cron_flux_update_products', array( $this, 'update_products_cron' ) );

		add_action( 'rest_api_init', array( $this, 'my_register_route' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_flux_mapping_scripts' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'flux_mapping_scripts' ), 9999 );

		add_action( 'init', array(&$this,'register_post_types'), 0 );
		add_action( 'init', array( $this, 'product_brand_taxonomy' ) );

		add_action('add_meta_boxes', array(&$this,'add_custom_box'));
		add_action('save_post', array( &$this,'save_postdata' ) );

		add_action( 'before_delete_post', array( $this, 'delete_postdata' ),  10,  1 );

		add_action('manage_'.$this->post_type.'_posts_custom_column', array(&$this, 'custom_flux_product_columns'), 15, 3);
		add_filter('manage_'.$this->post_type.'_posts_columns', array(&$this, 'flux_product_columns'), 15, 1);

		add_action("admin_menu", array(&$this,"plugin_setup_menu"));

		add_action( 'wp_ajax_import_flux_ajax_request', array(&$this,'ajax_callback') );
		add_action( 'wp_ajax_nopriv_import_flux_ajax_request', array(&$this,'ajax_callback') );

		add_action( 'wp_ajax_load_products', array( &$this, 'load_products') );
		add_action( 'wp_ajax_nopriv_load_products', array( &$this,'load_products') );

		add_action( 'woocommerce_after_register_taxonomy', array( &$this, 'register_product_attributes' ) );

		add_action('cron_flux_reader', array( &$this,'flux_reader' ) );

		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array(&$this,'handle_custom_query_var'), 10, 2 );

		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'after_add_to_cart_redirection' ), 5, 1 );

		add_action( 'admin_head', function(){
			?>
			<link href="https://fonts.googleapis.com/css?family=Poppins&display=swap" rel="stylesheet">
			<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
			<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
			<?php
		});

		// update scheduler
		add_action( 'wp', function(){
			$args = array();
			if ( !wp_next_scheduled( 'cron_flux_update_products', $args ) ) {
				wp_schedule_event( time( ), 'fifteendays', 'cron_flux_update_products', $args );
			}
			// var_dump( maybe_unserialize( get_post_meta( 25, 'flux_product_categories',  true ) ) );
			// var_dump( maybe_unserialize( get_post_meta( 25,  'flux_categories_indexes', true ) ) );
			// var_dump( get_post_meta( 25, 'already_created_25_1'  , true ) );
			
		});


	}


	function after_add_to_cart_redirection( $url ) {
		
		global $post;
		
		if( get_post_type( $post->ID ) == 'product' || get_post_type( $post->ID ) == 'product_variation' )
			$url = get_post_meta( $post->ID, 'url', true );

		WC()->cart->empty_cart(); // clear the cart

		return $url;
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
		/*
		add_submenu_page(
	        'edit.php?post_type='.$this->post_type,
	        __( 'Flux Importes' ),
	        __( 'Flux Importes' ),
	        'manage_woocommerce', // Required user capability
	        'import-product',
	        array(&$this,"import_flux_template")
	    );

			// Page to display mapping
			add_submenu_page(
				'edit.php?post_type='.$this->post_type, // Parent slug ( it's a custom post type )
				__( 'Flux Mapping' ), // Page title
				__( 'Flux Mapping' ), // Menu title
				'manage_woocommerce', // Required user capability
				'flux-mapping', // Menu slug
				array( $this, 'flux_mapping_template' ) // callback
			);
			*/
	}

	function flux_product_columns($defaults ){
		$defaults['product_flux_etat'] = esc_html__('Etat du Flux', 'wp_manifestation_manage');
		$defaults['product_flux_url'] = esc_html__('Lien du Flux', 'wp_manifestation_manage');
		return $defaults;
	}

    function custom_flux_product_columns($column_name, $postid){
		if ( $column_name == 'product_flux_etat' ) {
		 	$name = get_post_meta($postid,  'flux_stape',  true );

			if($name ==1){
				echo "<span style='background: #8f63f5; color: white;padding: 6px;'>En attente de Telechargement</span>";
			}else if($name == 2){
				echo "<span style='background: #f1cc0b; color: white;padding: 6px;'> Telechargement en Cours</span>";
			}else if($name ==3){
				echo "<span style='background: #ff0000; color: white;padding: 6px;'>Telechargement Termine</span>";
			}else if($name == 4){
				echo "<span style='background: #63f597; color: white;padding: 6px;'> Telechargement Termine</span>";
			}

			/*
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
			*/


		}else if ( $column_name == 'product_flux_url' ) {
		 	$name = get_post_meta($postid,  'flux_url',  true);
			echo $name;
		}
	}

	// Register Custom Taxonomy
	public function product_brand_taxonomy(){

		$labels = array(
		    'name'							=> _x( 'Marques', 'taxonomy general name', 'woocommerce' ),
		    'singular_name' 				=> _x( 'Marque', 'taxonomy singular name', 'woocommerce' ),
		    'search_items' 					=> __( 'Recherches des Marques', 'woocommerce' ),
		    'popular_items' 				=> __( 'Marques Populaires', 'woocommerce' ),
		    'all_items' 					=> __( 'Toutes les Marques', 'woocommerce' ),
		    'parent_item' 					=> null,
		    'parent_item_colon' 			=> null,
		    'edit_item' 					=> __( 'Editer la Marque', 'woocommerce' ),
		    'update_item' 					=> __( 'Mise à jour de la Marque', 'woocommerce' ),
		    'add_new_item' 					=> __( 'Ajouter une nouvelle Marque', 'woocommerce' ),
		    'new_item_name' 				=> __( 'Nom de la nouvelle Marque', 'woocommerce' ),
		    'separate_items_with_commas' 	=> __( 'Separate brand with commas', 'woocommerce' ),
		    'add_or_remove_items'			=> __( 'Add or remove brands', 'woocommerce' ),
		    'choose_from_most_used' 		=> __( 'Choose from the most used brands', 'woocommerce' ),
		    'menu_name'					 	=> __( 'Marques', 'woocommerce' )
		);

		// Now register the non-hierarchical taxonomy like tag
	  	register_taxonomy('product-brand', array('product'), array(
			    'hierarchical' 				=> true,
			    'meta_box_sanitize_cb'		=> 'taxonomy_meta_box_sanitize_cb_input' ,
			    'labels' 					=> $labels,
			    'show_ui' 					=> true,
			    'show_admin_column' 		=> true,
			    'update_count_callback' 	=> '_update_post_term_count',
			    'query_var' 				=> true,
			    'rewrite' 					=> array( 'slug' => 'marques' )
	  		)
	  	);

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

	// save post
	function save_postdata( $post_id ){
		
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		    return $post_id;

	    if (get_post_type($post_id) === $this->post_type) {

	    	$helper = new FluxHelper();
			
			if( isset( $_POST[ 'flux-status' ] ) ){
				$is_active = (int)$_POST[ 'flux-status' ];
				update_post_meta( $post_id, 'flux_status', $is_active );
			}else{
				update_post_meta( $post_id, 'flux_status', 0 );
			}
			
			if( isset( $_POST['flux_stape']))
				$flux_step = (int)$_POST['flux_stape'];

	    	if( $flux_step == -2){ // It's role is to stop import
	    		update_post_meta( $post_id, 'products_read', 0);
				update_post_meta( $post_id, 'current_flux_categories', '');
				update_post_meta( $post_id, 'activate_flux_mapping_update', 0 ); // delete update
				
	    		$args = array( $post_id );
				wp_clear_scheduled_hook( 'cron_flux_load_products', $args );
				wp_clear_scheduled_hook( 'cron_flux_load_products', $args );
				
				return $post_id;
	    	}
			
			$metas = array( 'flux_stape', 'flux_url' );
			foreach ($_POST as $key => $value) {
		   		if (in_array($key, $metas)) {
				   update_post_meta( $post_id, $key, $value );
				}
    		}

			if( isset( $_POST[ 'activate-flux-mapping-update' ] ) ){
				$is_active = (int)$_POST[ 'activate-flux-mapping-update' ];
				update_post_meta( $post_id, 'activate_flux_mapping_update', $is_active );
				if( $is_active ){
					update_post_meta( $post_id, 'products_read', 0);
					update_post_meta( $post_id, 'current_flux_categories', '');
					$args = array( $post_id );
					wp_clear_scheduled_hook( 'cron_flux_load_products', $args );
					wp_clear_scheduled_hook( 'cron_flux_load_products', $args );

					$this->reset_all_already_created( $post_id );
					update_post_meta( $post_id, 'current_started_cron', 0 );
				}
			}else{
				update_post_meta( $post_id, 'activate_flux_mapping_update', 0 );
			}
			
			// check if post is published
			if( get_post_status( $post_id ) == 'publish' && ( $flux_step == 1 || $flux_step == -1 )   ){ // publish and download isn't completed
				
				update_post_meta( $post_id, 'flux_stape', $this->downloading_step['inprogress'] );
				$this->create_cron( $post_id );

			}else if( get_post_status( $post_id ) == 'publish' && $flux_step == 4){ // published and download is completed

				// Manage meta data
				$flux_categories = array();
				$product_categories = array();
				$flux_product_categories = array();

				if( isset( $_POST['flux-categories'] ) ){
					$flux_categories = $_POST['flux-categories'];
				}
				foreach ( $flux_categories as $key => $value ) {
					$product_categories_name = 'product-categories-' . ( $key + 1 );
					if( isset( $_POST[ $product_categories_name ] ) ){
						array_push( $product_categories, $_POST[ $product_categories_name ] );
					}else{
						array_push( $product_categories, array(	) ); // to give same size to the arrays ( flux and product categories arrays)
					}
				}

				for ($i=0; $i < count( $flux_categories ) ; $i++) {
					$flux_product_categories[ $flux_categories[$i] ] = $product_categories[$i];
				}

				update_post_meta( $post_id, 'flux_product_categories', maybe_serialize( $flux_product_categories ) );

				update_post_meta( $post_id, 'max_inputs', count( $flux_product_categories ) );

				$is_positions_are_updated = get_post_meta( $post_id, 'is_positions_are_updated', true );

				if( isset( $_POST['update-flux-categories-positions'] )  || empty( $is_positions_are_updated ) ){
					
					update_post_meta( $post_id, 'is_positions_are_updated', '1' );

					$xml_products = $helper->get_xml_products( $post_id );
					$helper->save_categories_indexes( $post_id, $xml_products, $flux_categories );

				}

				
				for ($i=0; $i < $helper->get_max_async_cron(); $i++) { 


					if( isset( $flux_categories[ $i ] ) ){
						
						$current_started_cron = get_post_meta( $post_id, 'current_started_cron', true );

						if( empty($current_started_cron) ){
							$current_started_cron = 0;							
						}

						if( isset( $flux_categories[ $i ] ) ){
							
							$args = array( $post_id, $flux_categories[ $i ] );


							if ( !wp_next_scheduled( 'cron_flux_load_products_by_category', $args ) ) {
								wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products_by_category',  $args );
							}

							update_post_meta( $post_id, 'current_started_cron', ( $current_started_cron + 1 ) );	
						}
						
					}
				}
				
				

				// Manage importation
				$args = array( $post_id );
								
				if ( !wp_next_scheduled( 'cron_flux_load_products', $args ) ) {
					$args = array( $post_id );
					wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products', $args );
				}

			}else{
				update_post_meta( $post_id, 'flux_stape', $this->downloading_step['waiting'] );
			}
		}
		return $post_id;
	}


	public function call_next_cron( $post_id ){

		$is_end = true;

		$flux_product_categories_original = get_post_meta( $post_id, 'flux_product_categories', true );
		$flux_product_categories = maybe_unserialize( $flux_product_categories_original );
		$current_started_cron = get_post_meta( $post_id, 'current_started_cron', true );
		$counter = 0;

		if( $current_started_cron < count( $flux_product_categories )  ){
			
			foreach ( $flux_product_categories as $key => $flux_product_category ) {
				
				$counter ++;

				if( $counter == ( $current_started_cron + 1 ) ){
						
					$args = array( $post_id, $key );
					
					$is_end = true;

					if ( !wp_next_scheduled( 'cron_flux_load_products_by_category', $args ) ) {
						wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products_by_category',  $args );
					}

					update_post_meta( $post_id, 'current_started_cron', ( $current_started_cron + 1 ) );

					break;
				}
			}

		}else{
			// update_post_meta( $post_id, 'current_started_cron', 0 );
		}

		return $is_end;

	}

	public function reset_all_already_created( $post_id ){

		$flux_product_categories_original = get_post_meta( $post_id, 'flux_product_categories', true );
		$flux_product_categories = array();

		if( !empty( $flux_product_categories_original ))
			$flux_product_categories = maybe_unserialize( $flux_product_categories_original );

		$size = count( $flux_product_categories );

		for ($i=0; $i < $size ; $i++) { 
			update_post_meta( $post_id, 'already_created_'. $post_id . '_' . ( $i + 1 ) , 0 );		
		}

	}


	// delete post
	public function delete_postdata ( $post_id ){

		global $post_type;

		if ( $post_type == $this->post_type ) {

				if( file_exists( WPIF_DIR . "flux/flux_" . $post_id . ".xml" ) ){
						unlink( WPIF_DIR . "flux/flux_" . $post_id . ".xml" );
				}
				if( file_exists( WPIF_DIR . "flux/category_" . $post_id . ".txt" ) ){
						unlink( WPIF_DIR . "flux/category_" . $post_id . ".txt" );
				}
				if( file_exists( WPIF_DIR . "flux/progress_" . $post_id . ".txt"  ) ){
						unlink( WPIF_DIR . "flux/progress_" . $post_id . ".txt" );
				}
		}

	}

	// transition post status to checkf if post is pubelsh or not
	function flux_transition_post_status( string $new_status, $old_status, $post ){

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

			// flux mapping
			add_meta_box(
				'flux-mapping-container',
				'Mapping',
				array( $this, 'flux_mapping_meta_box_cb' ),
				$this->post_type,
				'advanced',
				'high',
				$callback_args = null
			);

			// flux mapping
			add_meta_box(
				'flux-mapping-configs',
				'Activate Update',
				array( $this, 'flux_mapping_configs_meta_box_cb' ),
				$this->post_type,
				'side',
				'low',
				$callback_args = null
			);
	}


	function wporg_custom_box_html($post){
		include(WPIF_DIR.'template/html/admin-flux-detai.php');
	}

	function import_flux_template($post){
		include(WPIF_DIR.'template/html/admin-flux-import.php');

	}

	function flux_mapping_meta_box_cb( $post ){

		// $this->create_categories_file( $post->ID );

		include( WPIF_DIR . 'template/html/admin-flux-mapping.php');
	}

	function flux_mapping_template( $post ){
			include(WPIF_DIR.'template/html/admin-flux-mapping.php');
	}

	function flux_mapping_configs_meta_box_cb( $post ){
		$is_activate =  get_post_meta( $post->ID, 'activate_flux_mapping_update', true );
		if( empty( $is_activate ) )
			$is_activate = 0;
		else
			$is_activate = 1;

		?>
			<div>
				<div>
					<label for="activate-flux-mapping-update">Activer ?</label>
					<input id="activate-flux-mapping-update" name="activate-flux-mapping-update" type="checkbox" value="<?php echo $is_activate;?>" <?php if($is_activate) echo 'checked'; ?>/>
				</div>
				<div>
					<label for="update-flux-categories-positions">Mettre à jour les positions des catégories?</label>
					<input id="update-flux-categories-positions" name="update-flux-categories-positions" type="checkbox"/>
				</div>
			</div>
		<?php
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


	public function register_product_attributes(){

		$color_taxonomy = 'pa_couleur';
		$gender_taxonomy = 'pa_genre';
		$sex_taxonomy = 'pa_sexe';
		$brand_taxonomy = 'pa_marque';
		$size_taxonomy = 'pa_taille';

		$is_color_taxonomy = false;
		$is_gender_taxonomy = false;
		$is_sex_taxonomy = false;
		$is_brand_taxonomy = false;
		$is_size_taxonomy = false;

		$attributes_taxonomies = wc_get_attribute_taxonomies();
		foreach ( $attributes_taxonomies as $key => $tanoxomy ) {
			if( $tanoxomy->attribute_name == 'couleur' ){
				$is_color_taxonomy = true;
				break;
			}
		}

		/*
		foreach ( $attributes_taxonomies as $key => $tanoxomy ) {
			if( $tanoxomy->attribute_name == 'genre' ){
				$is_gender_taxonomy = true;
				break;
			}
		}
		*/

		foreach ( $attributes_taxonomies as $key => $tanoxomy ) {
			if( $tanoxomy->attribute_name == 'sexe' ){
				$is_sex_taxonomy = true;
				break;
			}
		}

		foreach ( $attributes_taxonomies as $key => $tanoxomy ) {
			if( $tanoxomy->attribute_name == 'marque' ){
				$is_brand_taxonomy = true;
				break;
			}
		}

		foreach ( $attributes_taxonomies as $key => $tanoxomy ) {
			if( $tanoxomy->attribute_name == 'taille' ){
				$is_size_taxonomy = true;
				break;
			}
		}

		if( !taxonomy_exists( $color_taxonomy ) && !get_taxonomy( $color_taxonomy ) && !$is_color_taxonomy || !taxonomy_exists( 'couleur' ) ){
			$args = array(
				'name'         => "couleur",
				'slug'         => "couleur",
				'order_by'     => "menu_order",
				'has_archives' => "",
			);
			wc_create_attribute($args);
		}
		/*
		if( !taxonomy_exists( $gender_taxonomy ) && !get_taxonomy( $gender_taxonomy ) && !$is_gender_taxonomy || !taxonomy_exists( 'genre' )){
			$args = array(
				'name'         => "genre",
				'slug'         => "genre",
				'order_by'     => "menu_order",
				'has_archives' => "",
			);
			wc_create_attribute($args);
		}
		*/

		if( !taxonomy_exists( $sex_taxonomy ) && !get_taxonomy( $sex_taxonomy ) && !$is_sex_taxonomy || !taxonomy_exists( 'sexe' )){
			$args = array(
				'name'         => "sexe",
				'slug'         => "sexe",
				'order_by'     => "menu_order",
				'has_archives' => "",
			);
			wc_create_attribute($args);
		}

		if( !taxonomy_exists( $brand_taxonomy ) && !get_taxonomy( $brand_taxonomy ) && !$is_brand_taxonomy || !taxonomy_exists( 'marque' )){
			$args = array(
				'name'         => "marque",
				'slug'         => "marque",
				'order_by'     => "menu_order",
				'has_archives' => "",
			);
			wc_create_attribute($args);
		}

		if( !taxonomy_exists( $size_taxonomy ) && !get_taxonomy( $size_taxonomy ) && !$is_size_taxonomy || !taxonomy_exists( 'taille' )){
			$args = array(
				'name'         => "taille",
				'slug'         => "taille",
				'order_by'     => "menu_order",
				'has_archives' => "",
			);
			wc_create_attribute($args);
		}

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

				$schedules['fifteendays'] = array(
		            'interval'  => 1296000, // time in seconds
		            'display'   => 'Every Fifteen days'
		    );

				$schedules['weekly'] = array(
		            'interval'  => 604860, // time in seconds
		            'display'   => 'Every Week'
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


	/**
	* Background tasks
	*/

	// Create a cron
	function create_cron( $post_id ){

		$args = array( $post_id );

		if ( ! wp_next_scheduled( 'cron_flux_download', $args ) ) {
		    wp_schedule_event( time(), 'weekly',  'cron_flux_download', $args );
		}

	}

	// Delete a cron
	function delete_flux_download_cron( $post_id ){
		$args = array( $post_id );
		wp_clear_scheduled_hook( 'cron_flux_download', $args );
	}

	// Import file
	function download_flux( $post_id ){

		$url = get_post_meta( $post_id, 'flux_url', true );
		$output_path = WPIF_DIR . 'flux/flux_' . $post_id . '.xml';
		$progress_path = WPIF_DIR . 'flux/progress_'. $post_id . '.txt';

		file_put_contents( $progress_path, '' );
		$output_file = fopen( $output_path, 'w' );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 12000);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 6000);

		curl_setopt( $ch, CURLOPT_PROGRESSFUNCTION, array( &$this, 'progressCallback' ) );
		curl_setopt( $ch, CURLOPT_NOPROGRESS, false );
		curl_setopt( $ch, CURLOPT_FILE, $output_file );
		curl_exec( $ch );

		// verify HTTP status code
		if (curl_errno($ch)==0) {
		  switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
		    case 200:
					update_post_meta( $post_id, 'flux_stape', $this->downloading_step['completed'] );
					$this->create_categories_file( $post_id );
					// $this->delete_flux_download_cron( $post_id );
		      break;
		    default:
					update_post_meta( $post_id, 'flux_stape', $this->downloading_step['cancelled'] );
					// $this->delete_flux_download_cron( $post_id );
		  }
		}else{
			// $this->delete_flux_download_cron( $post_id );
			update_post_meta( $post_id, 'flux_stape', $this->downloading_step['cancelled'] );
		}
		curl_close($ch);
		fclose( $output_file );
		$this->delete_flux_download_cron( $post_id );
	}

	// Progess of importation
	function progressCallback($resource, $download_size, $downloaded, $upload_size, $uploaded){

		if (version_compare(PHP_VERSION, '5.5.0') < 0) {
				$uploaded = $upload_size;
				$upload_size = $downloaded;
				$downloaded = $download_size;
				$download_size = $resource;
		}
		$content = "$download_size, $downloaded";
		/* // TODO:
		echo curl_getinfo($resource, CURLINFO_CONTENT_LENGTH_DOWNLOAD ); // -1 Taille inconnu

		$fp = fopen( WPIF_DIR .'/flux/progress.txt', 'a' );
		fputs( $fp, "$content\n" );
		fclose( $fp );
		*/
	}

	public function create_categories_file( $post_id ){

		$url = WPIF_DIR. 'flux/flux_' . $post_id . '.xml';


		if( ! file_exists( $url ) ) // if xml file doesn't exist
			return ;

		try {
			$xml = new SimpleXMLElement( $url, 0, true );

			$products = $xml->xpath( '/catalog/product' ); // first flux
			if( !$products || empty( $products ) )
				$products = $xml->xpath( '/produits/produit' ); // second flux
			// TODO: third flux

			if( $products == false ) // xpath error occured
				return ;

			$content = '';
			$categories = array();
			$counter = 0;
			foreach ( $products as $key => $product ) {
				$category = (string) $product->category;
				if(empty($category))
					$category = (string) $product->categorie;
				if( !in_array( $category, $categories ) ){
					$categories[] = $category;
					$content .= $category . "\n";
				}
			}

		} catch (\Exception $e) {
				$content .= 'An error occured';
		}

		file_put_contents( WPIF_DIR. 'flux/category_' . $post_id . '.txt', $content);
	}

	public function import_products( $post_id  ){

		$helper = new FluxHelper();
		$xml_products = $helper->get_xml_products( $post_id );
		$products_read = get_post_meta( $post_id,  'products_read', true );
		$current_flux_categories = get_post_meta( $post_id,  'current_flux_categories', true );
		$counter = 0;

		$update_is_activated = get_post_meta( $post_id,  'activate_flux_mapping_update', true );

		if( empty( $products_read ) ){
			$products_read = 0;
		}

		$xml_products = array_slice( $xml_products, $products_read );

		$flux_product_categories = get_post_meta( $post_id, 'flux_product_categories', true );

		if( !empty( $flux_product_categories ) ){
			$flux_product_categories = maybe_unserialize( $flux_product_categories );
		}else{
			$flux_product_categories = array();
		}

		foreach ( $flux_product_categories as $flux_categories => $product_categories) {

			$counter ++;

			if( empty( $current_flux_categories ) ){
				$current_flux_categories = $flux_categories;
			}

			if(  $current_flux_categories == $flux_categories  ){

				$current_lines = $helper->create_products( $post_id, $xml_products, $flux_categories, $product_categories, $update_is_activated );
				$products_read += $current_lines;

				update_post_meta( $post_id, 'products_read', $products_read );
				update_post_meta( $post_id, 'current_flux_categories', $current_flux_categories );

				if( empty( $xml_products ) && $counter < count( $flux_product_categories ) ){
					$flux_product_categories_keys = array_keys( $flux_product_categories );
					$next_flux_categories = $flux_product_categories_keys[ $counter ];
					$tmp = get_post_meta( $post_id, 'tmp', true ) . $next_flux_categories;
					update_post_meta( $post_id, 'products_read', 0 );
					update_post_meta( $post_id, 'current_flux_categories', $next_flux_categories );

				}else if( count( $flux_product_categories ) == $counter && empty( $xml_products )  ){
					// all import is finished
					update_post_meta( $post_id, 'products_read', 0 );
					update_post_meta( $post_id, 'current_flux_categories', '' );

					$args = array( $post_id );
					wp_clear_scheduled_hook( 'cron_flux_load_products', $args );
					$this->delete_import_products_cron( $post_id );
					update_post_meta( $post_id, 'activate_flux_mapping_update', 0 ); // delete update
					return ;

				}
			}
		}

		$this->recall_import_products( $post_id );
	}

	public function import_products_by_category( $post_id, $flux_category ){

		$helper = new FluxHelper();
		$xml_products = $helper->get_xml_products( $post_id );
		
		$position = $helper->get_flux_category_position( $post_id, $flux_category );

		$flux_product_categories_original = get_post_meta( $post_id, 'flux_product_categories', true );
		$flux_product_categories = array();

		$all_indexes_original = get_post_meta( $post_id,  'flux_categories_indexes', true );
		$indexes = array();

		$product_categories = array(); 

		$update_is_activated = get_post_meta( $post_id,  'activate_flux_mapping_update', true );

		$already_created = get_post_meta( $post_id, 'already_created_'. $post_id . '_' . $position , true );

		if( empty( $already_created ) )
			$already_created = 0;

		if( !empty( $flux_product_categories_original ) )
			$flux_product_categories = maybe_unserialize( $flux_product_categories_original );

		if( isset( $flux_product_categories[ $flux_category ] ) )
			$product_categories = $flux_product_categories[ $flux_category ];

		if( !empty( $all_indexes_original ) )
			$all_indexes = maybe_unserialize( $all_indexes_original );

		if( isset( $all_indexes[ $flux_category ] ) )
			$indexes = $all_indexes[ $flux_category ];

		$indexes = array_slice( $indexes, $already_created );

		$created_number = $helper->create_products_from_indexes( $post_id, $xml_products, $flux_category, $product_categories, $update_is_activated , $indexes );


		if( $created_number === false ){
			// we need to reposition
			$args = array( $post_id, $flux_category );

			wp_clear_scheduled_hook( 'cron_flux_load_products_by_category', $args );
			update_post_meta( $post_id, 'already_created_'. $post_id . '_' . $position , 0 );

		}else{

			if( $created_number === 0 ){ // importation is finished for this category
				
				$args = array( $post_id, $flux_category );

				wp_clear_scheduled_hook( 'cron_flux_load_products_by_category', $args );

				update_post_meta( $post_id, 'already_created_'. $post_id . '_' . $position , 0 );				

				$is_end = $this->call_next_cron( $post_id );

			}else{

				$already_created += $created_number;

				update_post_meta( $post_id, 'already_created_'. $post_id . '_' . $position , $already_created );

				$this->recall_import_products_by_category( $post_id, $flux_category );	
			}

			
		}

	}

	
	public function recall_import_products_by_category( $post_id, $flux_category ){

		$args = array( $post_id, $flux_category );

		wp_clear_scheduled_hook( 'cron_flux_load_products_by_category', $args );

		if ( !wp_next_scheduled( 'cron_flux_load_products_by_category', $args ) ) {
				wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products_by_category', $args );
		}

	}

	/*
	public function recall_import_products_by_category( $post_id, $flux_category, $position ){

		$args = array( $post_id, $flux_category );

		wp_clear_scheduled_hook( 'cron_flux_load_products_by_category_' . $position, $args );
		wp_clear_scheduled_hook( 'cron_flux_load_products_by_category_' . $position, $args );

		if ( !wp_next_scheduled( 'cron_flux_load_products_by_category_' . $position, $args ) ) {
				wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products_by_category_' . $position, $args );
		}

	}
	*/


	public function update_products_cron(){

		$args = array(
			'post_type' => $this->post_type,
			'numberposts'	=> -1,
		);

		$posts = get_posts( $args );

		foreach ( $posts as $key => $post ) {

			update_post_meta( $post_id, 'activate_flux_mapping_update', 1 ); // we activate update
			$flux_status = get_post_meta( $post_id, 'flux_status', true );

			if( $flux_status ){

				$args = array( $post->ID );
				wp_clear_scheduled_hook( 'cron_flux_load_products', $args );

				if ( !wp_next_scheduled( 'cron_flux_load_products', $args ) ) {
						wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products', $args );
				}

			}
		}

	}


	public function recall_import_products( $post_id ){

		$args = array( $post_id );

		wp_clear_scheduled_hook( 'cron_flux_load_products', $args );

		if ( !wp_next_scheduled( 'cron_flux_load_products', $args ) ) {
				wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products', $args );
		}

	}


	public function delete_import_products_cron( $post_id ){
			wp_clear_scheduled_hook( 'cron_flux_load_products', array( $post_id ) );
	}


	public function load_products(){

		$flux_id = $flux_category = $product_categories = null;

		if( isset( $_POST[ 'flux_id' ] ) ){
			$flux_id = (int) $_POST[ 'flux_id' ];
		}
		if( isset( $_POST[ 'flux_id' ] ) ){
			$flux_category = $_POST[ 'flux_category' ];
		}
		if( isset( $_POST[ 'product_categories' ] ) ){
			$product_categories = $_POST[ 'product_categories' ];
		}
		$args = array( $flux_id, $flux_category, $product_categories );

		if ( !wp_next_scheduled( 'cron_flux_load_products', $args ) ) {
				wp_schedule_event( time( ), 'weekly', 'cron_flux_load_products', $args );
		}

		// wp_clear_scheduled_hook( 'cron_flux_load_products', $args );

		exit;
	}


	/**
	 * Scripts and Styles
	 */
	public function admin_flux_mapping_scripts(){

		wp_register_style(
			$handle = 'font-awesome-style',
			$src = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css',
			$deps = array(),
			$ver = false,
			$media = 'all'
		);

		wp_enqueue_style(
			$handle = 'admin-flux-mapping-style',
			$src = plugins_url( '/template/css/admin-flux-mapping.css', __FILE__ ),
			$deps = array( 'font-awesome-style' ),
			$ver = false,
			$media = 'all'
		);

		wp_enqueue_script( $handle='admin-flux-mapping-js',
			$src = plugins_url( '/template/js/admin-flux-mapping.js', __FILE__ ),
			$deps = array( 'jquery' ),
			$ver = false,
			$in_footer = false
		);

		wp_enqueue_script( $handle='flux-mapping-js',
				$src = plugins_url( '/template/js/flux-mapping.js', __FILE__ ),
				$deps = array( 'jquery' ),
				$ver = false,
				$in_footer = false
		);

		wp_localize_script(
				'flux-mapping-js',
				'flux_mapping',
				array(
					'ajaxurl'	=> admin_url( 'admin-ajax.php' )
				)
		);
	}


	public function flux_mapping_scripts(){

			wp_enqueue_script( $handle='flux-mapping-js',
					$src = plugins_url( '/template/js/flux-mapping.js', __FILE__ ),
					$deps = array( 'jquery' ),
					$ver = false,
					$in_footer = false
			);

			wp_localize_script(
					'flux-mapping-js',
					'flux_mapping',
					array(
						'ajaxurl'	=> admin_url( 'admin-ajax.php' )
					)
			);
	}


	// Instanciation
	public static function getInstance(){

		static $instance = null;

		if( ! $instance ){
			$instance = new WpImportFlux();
		}
		return $instance;
	}


}
WpImportFlux::getInstance();

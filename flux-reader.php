<?php

/*
  Plugin Name: Flux Reader
  Plugin URI: 
  Description: Permettre la lecture des flux
  Version: 1.0
  Author: ARS GROUP
  Author URI: http://www.ars-agency.com
 */



add_action('cron_flux_reader', 'flux_reader');
// add_action('init', 'flux_reader');

$args = array( false );

if ( ! wp_next_scheduled( 'cron_flux_reader', $args ) ) {
    wp_schedule_event( time(), 'everyminute', 'cron_flux_reader', $args );
}

function flux_reader(){
	
	global $woocommerce;

	$url = 'https://chichamania.belsis.cm/flux_example.xml';

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
					create_category( trim( $value ) );
					array_push( $category_ids, get_category_id_by_name( trim($value) ) );
				}

				$wc_product = new WC_Product_Variable();
				$wc_product->set_name( $name );
				$wc_product->set_price( $price );
				$wc_product->set_regular_price( $price );

				$wc_product->set_category_ids( $category_ids );

				$wc_product_id = $wc_product->save();
				generate_geatured_image( $url_photo_grande, $wc_product_id );

				update_post_meta( $wc_product_id, 'reference_interne', $reference_interne);

				// create variation
				$variation = new WC_Product_Variation( $wc_product_id );

				

			}
			
		}
		


	} catch (Exception $e) {
		
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


add_filter( 'cron_schedules', 'wpshout_add_cron_interval' );
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
add_filter( 'woocommerce_product_data_store_cpt_get_products_query', 'handle_custom_query_var', 10, 2 );


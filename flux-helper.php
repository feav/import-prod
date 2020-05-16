<?php
class FluxHelper {

  function __construct(){
  }


  /**
   * Check if product ( a parent ) exists
   *
   * @since  1.0.0
   * @param  string $meta_key of reference of the product
   * @param  string $meta_value of the reference
   * @return boolean
   */
  public function is_parent_product_exists( $meta_key = 'reference', $meta_value = null ){

      $args = array(
        'numberposts'      => 1,
        'meta_key'         => $meta_key,
        'meta_value'       => $meta_value,
        'post_type'        => 'product',
      );

      $products = get_posts( $args );
      if( empty( $products ) )
          return false;
      return true;
  }


  /**
   * Get product_id if category of existing product is updated.
   *
   * @since  1.0.0
   * @param  string $meta_key of reference of the product
   * @param  string $meta_value of the reference
   * @param  string $flux_category to updated
   * @return int|boolean(false)
   */
  public function updated_categories_of_existing_product( $meta_key = 'reference', $meta_value = null, $flux_category  ){

    $args = array(
      'numberposts'      => 1,
      'meta_key'         => $meta_key,
      'meta_value'       => $meta_value,
      'post_type'        => 'product',
    );

    $products = get_posts( $args );

    if( !empty( $products ) ){
      $product = $products[0];
      $flux_categories = get_post_meta( $product->ID,  'flux_categories', true );

      if( empty( $flux_categories ) ){
         $flux_categories = array();
      }else{
        $flux_categories = maybe_unserialize( $flux_categories );
      }

      if( !in_array( $flux_category, $flux_categories ) ){
        array_push( $flux_categories, $flux_category );
        update_post_meta( $product->ID, 'flux_categories', maybe_serialize( $flux_categories ) );
        return $product->ID;
      }
      return false;
    }
    return false;
  }


  /**
   * Create products. It mainly means variable's product and thier variations
   *
   * @since  1.0.0
   * @param  int|string $flux_id the id of the flux
   * @param  array(object) $xml_products array contains xml elements represents the product structure
   * @param  string $flux_category category of flux that we want to create
   * @param  array(int) $prod_cats array of product categories that we want to match on
   * @param  boolean $is_updated check if creation is update
   * @return int|boolean(false)
   */
  public function create_products( $flux_id, $xml_products, $flux_category, $prod_cats = array(), $is_updated ){

    $max_product_variable_to_create = 10;
    $counter = 0;
    $products_read = 0;
    $max_product_to_read = 2000;

    $flux_category = trim( $flux_category ); // remove spaces at beginning an at the end

    foreach ($xml_products as $key => $xml_product) {

        $products_read ++;
        if( $counter == $max_product_variable_to_create )
          break;

        $reference = $this->get_xml_product_reference( $xml_product );

        if( $this->get_xml_product_category( $xml_product ) == $flux_category ){

            $name = (string) $xml_product->name;
            $description = (string) $xml_product->description;
            $price = (string) $xml_product->price;
            $sale_price = (string) $xml_product->old_price;
            $url = (string) $xml_product->url;

            $sex = (string) $xml_product->sexe;
            $gender = (string) $xml_product->genre;
            $color = (string) $xml_product->color;
            $brand = (string) $xml_product->brand;

            $pointures = (string) $xml_product->pointure;
            $url_photo_grande = (string) $xml_product->image_big;

            if( empty($name) )
              $name = (string) $xml_product->nom;
            if( empty( $price ) )
              $price = (string) $xml_product->prix;
            if( empty( $sale_price ) )
              $sale_price = (string) $xml_product->prix_barre;

            if( empty( $color ) ){
              $color = (string) $xml_product->colorbase;
            }else if( empty( $color ) ){
              $color = (string) $xml_product->couleur;
            }

            if( empty( $brand ) )
               $brand = (string) $xml_product->marque;
            if( empty( $pointures ) )
              $pointures = (string) $xml_product->taille;
            if( empty( $url_photo_grande ) )
            $url_photo_grande = (string) $xml_product->url_photo_grande;


            $sizes = explode( ',', $pointures );

            if( !empty( $sizes ) && !$this->is_parent_product_exists( 'reference', $reference ) ){

                $product_variable_id = $this->create_product_variable( $name );
				
                $product_variable = wc_get_product( $product_variable_id );
                $product_variable->set_category_ids( $prod_cats );

                if( !empty( $sale_price ) ){
                  
                  $sale_price = (float)$sale_price;
                  $price = (float)$price;

                  $product_variable->set_price( $sale_price );
                  $product_variable->set_regular_price( $sale_price );

                  $product_variable->set_sale_price( $price );

                }else{
                  
                  $price = (float)$price;

                  $product_variable->set_price( $price );
                  $product_variable->set_regular_price( $price );

                }

                $flux_categories = array( $flux_category );


                update_post_meta( $product_variable_id, 'reference', $reference  );
                update_post_meta( $product_variable_id, 'flux_categories', maybe_serialize( $flux_categories ) );
                update_post_meta( $product_variable_id, 'url', $url );
                update_post_meta( $product_variable_id, 'flux_id', $flux_id );

                if( !empty( $color ) ){
                    $value = $color;
                    $taxonomy = 'pa_couleur';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );
					
      					if( empty( $_product_attributes ) )
      						$_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $gender ) ){
                    $value = $gender;
                    $taxonomy = 'pa_genre';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );

                    if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $sex ) ){
                    $value = $sex;
                    $taxonomy = 'pa_sexe';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );

                    if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $brand ) ){
                    $value = $brand;
                    $taxonomy = 'pa_marque';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );

                    if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                
                if( !empty( $sizes ) ){

                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );
					           $taxonomy = 'pa_taille';

                     if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                      'name'=> $taxonomy,
                      'value'=> '',
                      'is_visible' => '1',
                      'is_variation' => '1',
                      'is_taxonomy' => '1'
                    );
                    
                    foreach ($sizes as $key => $size) {
                      
                      $value = $size;
					  
                      if( !term_exists( $value, $taxonomy ) ){
                        wp_insert_term( $value, $taxonomy );
                      }  
                      $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );

                    }

                    update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $brand ) ){
                    $value = $brand;
                    $taxonomy = 'product-brand';

                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term = get_term_by( 'name', $value, $taxonomy );
                    if( $term )
                      $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, array( $term->term_id ), $taxonomy, true );
                }

                $product_variable->set_description( $description );
                $product_variable->save();

                $this->generate_geatured_image( $url_photo_grande,  $product_variable_id );

                foreach ( $sizes as $key => $size ) {
                  $this->create_product_variation( $product_variable_id, 'pa_taille', $size, $xml_product );
                }

                $counter ++;

            }
            else if( $product_variable_id = $this->updated_categories_of_existing_product( 'reference', $reference, $flux_category ) ){
              $product_variable = wc_get_product( $product_variable_id );
              $category_ids = $product_variable->get_category_ids();

              foreach ($prod_cats as $key => $prod_cat) {
                array_push( $category_ids, $prod_cat );
              }
              $product_variable->set_category_ids( $category_ids );
              $product_variable->save();

              $counter ++;

            }
            else if( $is_updated ){ // we update the product

              $args = array(
                'numberposts'      => 1,
                'meta_key'         => 'reference',
                'meta_value'       => $reference,
                'post_type'        => 'product',
              );

              $products = get_posts( $args );

              if( !empty( $products ) ){

                $product = $products[0];
                wp_delete_post( $product->ID ); // delete and recreate

                // $product_variable_id = $this->create_product_variable( $name, 'tailles', $sizes  );
                $product_variable_id = $this->create_product_variable( $name );
                $product_variable = wc_get_product( $product_variable_id );
                $product_variable->set_category_ids( $prod_cats );

                if( !empty( $sale_price ) ){
                  
                  $sale_price = (float)$sale_price;
                  $price = (float)$price;

                  $product_variable->set_price( $sale_price );
                  $product_variable->set_regular_price( $sale_price );

                  $product_variable->set_sale_price( $price );

                }else{
                  
                  $price = (float)$price;

                  $product_variable->set_price( $price );
                  $product_variable->set_regular_price( $price );
                  
                }

                $flux_categories = array( $flux_category );


                update_post_meta( $product_variable_id, 'reference', $reference  );
                update_post_meta( $product_variable_id, 'flux_categories', maybe_serialize( $flux_categories ) );
                update_post_meta( $product_variable_id, 'url', $url );
                update_post_meta( $product_variable_id, 'flux_id', $flux_id );

                if( !empty( $color ) ){
                    $value = $color;
                    $taxonomy = 'pa_couleur';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );
          
                if( empty( $_product_attributes ) )
                  $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $gender ) ){
                    $value = $gender;
                    $taxonomy = 'pa_genre';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );

                    if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $sex ) ){
                    $value = $sex;
                    $taxonomy = 'pa_sexe';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );

                    if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $brand ) ){
                    $value = $brand;
                    $taxonomy = 'pa_marque';
                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );
                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );

                    if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                          'name'=> $taxonomy,
                          'value'=> $value,
                          'is_visible' => '1',
                          'is_variation' => '0',
                          'is_taxonomy' => '1'
                    );

                     update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                
                if( !empty( $sizes ) ){

                    $_product_attributes = get_post_meta( $product_variable_id,'_product_attributes', true );
                     $taxonomy = 'pa_taille';

                     if( empty( $_product_attributes ) )
                      $_product_attributes = array();

                    $_product_attributes[ $taxonomy ] = array(
                      'name'=> $taxonomy,
                      'value'=> '',
                      'is_visible' => '1',
                      'is_variation' => '1',
                      'is_taxonomy' => '1'
                    );
                    
                    foreach ($sizes as $key => $size) {
                      
                      $value = $size;
            
                      if( !term_exists( $value, $taxonomy ) ){
                        wp_insert_term( $value, $taxonomy );
                      }  
                      $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, $value, $taxonomy, true );

                    }

                    update_post_meta( $product_variable_id,'_product_attributes', $_product_attributes );
                }

                if( !empty( $brand ) ){
                    $value = $brand;
                    $taxonomy = 'product-brand';

                    if( !term_exists( $value, $taxonomy ) ){
                      wp_insert_term( $value, $taxonomy );
                    }

                    $term = get_term_by( 'name', $value, $taxonomy );
                    if( $term )
                      $term_taxonomy_ids = wp_set_object_terms( $product_variable_id, array( $term->term_id ), $taxonomy, true );
                }

                $product_variable->set_description( $description );
                $product_variable->save();

                $this->generate_geatured_image( $url_photo_grande,  $product_variable_id );

                foreach ( $sizes as $key => $size ) {
                  $this->create_product_variation( $product_variable_id, 'pa_taille', $size, $xml_product );
                }

                $counter ++;
              }
            }else{
              $counter ++;
            }
        }

        if( $products_read == $max_product_to_read)
        	break;
    }

    return $products_read;
  }


  /**
   * Retrieve the category of the xml product with abstraction of the root node
   *
   * @since  1.0.0
   * @param  int $post_id the if of the flux that we want to get products
   * @return array(objects) the xml products
   */
  public function get_xml_products( $post_id ){

    $url =  WPIF_DIR . 'flux/flux_' . $post_id . '.xml';

		if( ! file_exists( $url ) ) // if xml file doesn't exist
			return array();

		try {
  			$xml = new SimpleXMLElement( $url, 0, true );

  			$xml_products = $xml->xpath( '/catalog/product' ); // smallest flux
  			if( !$xml_products )
  				$xml_products = $xml->xpath( '/produits/produit' ); // medium flux
  			if( !$xml_products )
          $xml_products = $xml->xpath( '/catalog/product' ); // biggest flux
  		} catch (\Exception $e) {
  				return array();
  		}
      return $xml_products;
  }


  /**
   * Retrieve the category of the xml product with abstraction of the root node
   *
   * @since  1.0.0
   * @param  object $xml_product the xml product
   * @return string the category of the product
   */
  public function get_xml_product_category( $xml_product ){
      $category = '';
      if( empty( $category ) ){ // first flux structure ( the smallest )
        $category = (string) $xml_product->category;
      }
      if( empty( $category ) ){ // second flux structure ( the medium one )
        $category = (string) $xml_product->categorie;
      }
      if( empty( $category ) ){ // second flux structure ( the biggest )
        $category = (string) $xml_product->category;
      }

      return trim( $category );
  }


  /**
   * Retrieve the reference of the xml product with abstraction of the root node
   *
   * @since  1.0.0
   * @param  object $xml_product the xml product
   * @return int the reference of the product
   */
  public function get_xml_product_reference( $xml_product ){
      $reference = '';

      if( empty( $reference ) ){ // smallest one
        $reference = (int) $xml_product->id;
      }
      if( empty( $reference ) ){ // medium one
        $reference = (int) $xml_product->reference_interne;
      }
      if( empty( $reference ) ){ // biggest one
        $reference = (int) $xml_product->id;
      }

      return $reference;
  }


  /**
   * Create a variable product through it's name
   *
   * @since  1.0.0
   * @param  string $name the name of the product
   * @return int the id of the created variable product
   */
  public function create_product_variable( $name ){

      $product_variable = new WC_Product_Variable();
      $product_variable->set_name( $name );
	
      $product_variable_id = $product_variable->save();
      
      return $product_variable_id;
  }


  /**
   * Create a variation product with a specific variation attribute
   *
   * @since  1.0.0
   * @param  int the parent of the variation product. It's the variable product
   * @param  string $attribute_name attribute name that we want to match as variation attrbiute
   * @param  string $attribute_value the value's attribute
   * @param  object it's the representation of a xml product
   * @return int the id of the created variation
   */
  public function create_product_variation( $product_variable_id, $attribute_name, $attribute_value = null, $xml_product ){

      $product_variable = wc_get_product( $product_variable_id );

    	// Creating the product variation
    	$variation = new WC_Product_Variation( );

      $sale_price = (string)$xml_product->price;
      $regular_price = (string)$xml_product->old_price;

      if( empty( $sale_price ) )
        $sale_price = (string) $xml_product->prix;
      if( empty( $regular_price ) )
        $regular_price = (string) $xml_product->prix_barre;

      if( !empty( $regular_price ) ){

        $regular_price = (float)$regular_price;
        $sale_price = (float)$sale_price;

        $variation->set_price( $regular_price );
        $variation->set_regular_price( $regular_price );

        $variation->set_sale_price( $sale_price );

      }else{

        $regular_price = (float)$sale_price;
        $variation->set_price( $regular_price );
        $variation->set_regular_price( $regular_price );

      }


      $url = (string)$xml_product->url;
      $variation->set_stock_quantity( 1 );

      $variation->set_parent_id( $product_variable_id );
      /*
      if( !empty( $attribute_value ) && $attribute_value != null ){
        
        $variation->set_attributes( array(
            $attribute_name    => $attribute_value,
          )
        ); 

      }
	  */
      
      $variation_id = $variation->save();
      update_post_meta( $variation_id, 'url', $url );

      // alternative way
      $term_slug = get_term_by( 'name', $attribute_value, $attribute_name )->slug; // Get the term slug
      update_post_meta( $variation_id, 'attribute_'.$attribute_name, $term_slug );

      return $variation_id;
  }


  /**
   * Download an image and attach it to a post time.
   *
   * @since  1.0.0
   * @param  string $image_url url of the image the we want to get
   * @param  int|string $post_id the post that we want to attach the image on
   * @return
   */
  public function generate_geatured_image( $image_url, $post_id  ){

    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    if( $image_data == null)
      return ;
	  if( !$image_data )
		 return ;
	
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

}
<?php
class FluxHelper {

  function __construct(){
  }

  public function get_max_async_cron(){
    return 1;
  }


  /**
   * Check if product ( a parent ) exists
   *
   * @since  1.0.0
   * @param  string $meta_key of reference of the product
   * @param  string $meta_value of the reference
   * @return boolean
   */
  public function is_parent_product_exists( $flux_id, $reference ){

      $args = array(
        'numberposts'      => 1,
        'post_type'        => 'product',
        'meta_query' => array(
          'relation' => 'AND',
          array(
              array(
                'key' => 'reference',
                'value' => $reference,
                'compare' => '='
              ),
          ),
          array(
              array(
                'key' => 'flux_id',
                'value' => $flux_id,
                'compare' => '='
              ),
          ),
        ),
      );

      $products = get_posts( $args );
      if( empty( $products ) )
          return false;
      return true;
  }

  /**
  * Avoid du plication of update in i run especially
  */
  public function dont_update_or_create_it( $flux_id, $flux_category, $xml_products, $index_to_check ){

    $all_indexes_original = get_post_meta( $flux_id,  'flux_categories_indexes', true );
    $indexes = array();
    $all_indexes = array();

    $product_categories = array(); 

    if( !empty( $all_indexes_original ) )
      $all_indexes = maybe_unserialize( $all_indexes_original );

    if( isset( $all_indexes[ $flux_category ] ) )
      $indexes = $all_indexes[ $flux_category ];

    $result = false;

    foreach ($indexes as $key => $index) {
      
      if( $index == $index_to_check )
        break;
      
      if( $this->get_xml_product_reference( $xml_products[ $index ] ) == $this->get_xml_product_reference( $xml_products[ $index_to_check ] ) ){
        $result = true;
        break;
      }

    }

    return $result;

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

            if( !empty( $sizes ) && !$this->is_parent_product_exists( $flux_id, $reference ) ){

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
                /*
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
                */

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

                /*
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
                */

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


  public function get_producs_index_of_category( $flux_id, $xml_products, $flux_category ){
    
    $result = array();

    foreach ($xml_products as $key => $xml_product) {
      
      if( $this->get_xml_product_category( $xml_product ) == $flux_category ) {
        array_push( $result, $key );
      }
    }

    return $result;
  }


  public function save_categories_indexes( $flux_id, $xml_products, $flux_categories=array() ){

    $result = array();

    foreach ($flux_categories as $key => $flux_category) {
      $indexes = $this->get_producs_index_of_category( $flux_id, $xml_products, $flux_category );
      $result[ $flux_category ] = $indexes;
    }

    update_post_meta( $flux_id,  'flux_categories_indexes', maybe_serialize( $result ) );

  }

    public function create_products_from_indexes( $flux_id, $xml_products, $flux_category, $prod_cats = array(), $is_updated, $indexes = array() ){

      $max_product_variable_to_create = 10;
      $counter = 0;
      $products_read = 0;
      $max_product_to_read = 200;

      $flux_category = trim( $flux_category ); // remove spaces at beginning an at the end

      $xml_products_number = count( $xml_products );

      $time_start_before_bc = microtime(true);

      foreach ($indexes as $key => $index ) {

        $time_start_single_product = microtime(true);

        $products_read ++;

        if( $index >= $xml_products_number )
          return false; // we need to reposition elements
        
        $xml_product = $xml_products[ $index ];
        
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

            if( !empty( $sizes ) && !$this->is_parent_product_exists( $flux_id, $reference ) ){

                var_dump( 'Nouvelle création' );

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
                /*
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
                }*/

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
              var_dump( 'Update de la catégorie' );
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
              var_dump( "Mise à jour ");

              $time_before_check = microtime(true);
              if( $this->dont_update_or_create_it( $flux_id, $flux_category, $xml_products, $index ) ){
                $counter ++ ;
              }else{
                $args = array(
                  'numberposts'      => 1,
                  'post_type'        => 'product',
                  'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        array(
                          'key' => 'reference',
                          'value' => $reference,
                          'compare' => '='
                        ),
                    ),
                    array(
                        array(
                          'key' => 'flux_id',
                          'value' => $flux_id,
                          'compare' => '='
                        ),
                    ),
                  ),
                );

                $products = get_posts( $args );

                if( !empty( $products ) ){
                    $product = $products[0];
                    $product_variable_id = $product->ID;

                    $product_variable = wc_get_product( $product_variable_id );

                    if( $product_variable ){
                      $product_variation_ids =  $product_variable->get_children();
                      foreach ($product_variation_ids as $key => $product_variation_id) {
                        wp_delete_post( $product_variation_id );
                      }

                    }


                    // $WC_Product_Variable_Data_Store_CPT = new WC_Product_Variable_Data_Store_CPT();
                    // $WC_Product_Variable_Data_Store_CPT->delete_variations( $product_variable_id, true );
                    // wp_delete_post( $product->ID ); // delete in other to recreate

                }else{
                  $time_before_variation = microtime(true);
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
                    /*
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
                    */

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
                }

                var_dump( 'Before variations ' . ( microtime(true) - $time_before_variation ) / 60  . '  taille variations ' . count($sizes) ) ;

                foreach ( $sizes as $key => $size ) {
                  $this->create_product_variation( $product_variable_id, 'pa_taille', $size, $xml_product );
                }
                var_dump( 'After variations ' . ( microtime(true) - $time_before_variation ) / 60  . '  taille variations ' . count($sizes) ) ;

                $counter ++;
              };
            }else{
                $counter ++;
            }
        }else{
          return false; // we need to reposition elements category doesn't match
        }

        if( $products_read == $max_product_to_read)
          break;

        $time_end_single_product = microtime(true);
        echo "<pre>";
        var_dump('création d\'un produit : ' . ($time_end_single_product - $time_start_single_product )/60 );
        echo "</pre>";
      }

      $time_end_before_bc = microtime(true);
      echo "<pre>";
      var_dump('Après la boucle : ' . ($time_end_before_bc - $time_start_before_bc )/60 );
      echo "</pre>";


      return $counter;
    }

    public function get_flux_category_position( $flux_id, $flux_category ){

      $flux_product_categories_original = get_post_meta( $flux_id, 'flux_product_categories',  true );
      $flux_product_categories = maybe_unserialize( $flux_product_categories_original );

      $positon = 0;

      foreach ($flux_product_categories as $key => $flux_product_category) {
      $positon ++;
      if( $key == $flux_category ){
        break;
      }       
      }

      return $positon;
    }

    public function delete_products( $flux_id, $products, $xml_products ){
      
      $xml_products_ids = array();
      
      foreach ($xml_products as $key => $xml_product) {
        $xml_products_id = get_xml_product_reference( $xml_product );
        array_push( $xml_products_ids , $xml_products_id );
      }

      if( !empty( $xml_products ) && !empty( $products ) ){

        foreach ($products as $key => $product) {
          $product_id = $product->ID;
          if( !in_array( $product_id, $xml_products_ids ) ){
            $WC_Product_Variable_Data_Store_CPT = new WC_Product_Variable_Data_Store_CPT();
            $WC_Product_Variable_Data_Store_CPT->delete_variations( $product_id, true );
            $attachment_id = get_post_thumbnail_id( $product_id );
            wp_delete_attachment( $attachment_id, true );
            wp_delete_post( $product_id, true );
          }
        }

      }

    }


}

new FluxHelper(); 

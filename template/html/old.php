<?php

  $product_cats = get_terms(
    array(
      'taxonomy' => 'product_cat',
      'hide_empty' => false
     )
   );


   $args = array(
     'post_type'  => 'wp_product_import',
     'numberposts' => -1,
     'meta_query' => array(
       array(
         'key'  => 'flux_stape',
         'value'  => 4, // 4 is completed status
         'compare' => '='
       )
     )
   );

   $posts = get_posts( $args );

?>

<div class="flux-reader main-box">
  <div class="flux-reader title-box">
    <span><?php _e( 'Flux Reader - Mapping' ); ?></span>
    <span><i class="fas fa-chevron-down fa-2"></i></span>
  </div>
  <div class="flux-reader content-box">
    <div>
      <div class="row">
        <div class="title-bloc">
          <span>Activer ?</span>
        </div>
        <div class="button-switch">
          <label for="flux-status" class="lbl-on" style="display:none;">On</label>
          <input type="checkbox" id="flux-status" class="switch" value="" />
          <label for="flux-status" class="lbl-off">Off</label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="title-bloc">
        <span>Mapping</span>
      </div>
      <div class="mapping-container">
        <?php
        foreach ( $posts as $key => $post ) {
        ?>
          <div class="mapping-item">
            <div class="input-field mapping-item--title">
              <label>Flux</label>
              <div class="flux-name">
                <span><?php echo $post->post_title; ?></span> - <a target="_blank" href="<?php echo WPIF_PLUGIN_URL . '/flux/category_' . $post->ID . '.txt' ;?>">Lien</a>
              </div>
            </div>
            <div class="input-field mapping-item--flux">
              <label>Categorie Flux</label>
              <input type="text" id="flux-<?php echo $post->ID;?>" name="flux-<?php echo $post->ID;?>" />
            </div>
            <div class="input-field mapping-item--category">
              <label>Categorie Produits</label>
              <select class="js-example-basic-multiple" id="flux-category-<?php echo $post->ID;?>" name="flux-category-<?php echo $post->ID;?>[]" multiple="multiple">
              <?php
              foreach ($product_cats as $key => $product_cat) {
                ?>
                <option value="<?php echo $product_cat->term_id; ?>"><?php _e( $product_cat->name ); ?></option>
                <?php
              }
              ?>
              </select>
            </div>
            <div style="text-align: right;"><button class="button validate-btn" data-flux-id=<?php echo $post->ID;?> id="button-<?php echo $post->ID;?>">Valider</button></div>
          </div>
        <?php
        }
        ?>
      </div>
    </div>
  </div>
</div>

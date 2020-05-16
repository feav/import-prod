<?php

  global $post;
  $itemNumber = 0;

  $flux_product_categories = get_post_meta( $post->ID, 'flux_product_categories', true );
  $flux_status = get_post_meta( $post->ID, 'flux_status', true );

  if( !empty( $flux_product_categories ) ){
    $flux_product_categories = maybe_unserialize( $flux_product_categories );
  }else{
    $flux_product_categories = array();
  }

  $flux_step = get_post_meta( $post->ID, 'flux_stape', true );

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


   if( $flux_step == '4' ){
?>
    <!-- store product categories inside option elements so that we call use it in js -->
    <div id="select-options" style="display: none;">
      <?php
      foreach ($product_cats as $key => $product_cat) {
        ?>
        <option value="<?php echo $product_cat->term_id; ?>"><?php _e( $product_cat->name ); ?></option>
        <?php
      }
      ?>
    </div>

    <div class="flux-reader content-box">
      <div>
        <div class="row">
          <h2 class="title">Flux : <?php echo $post->post_title; ?> - <a target="_blank" href="<?php echo WPIF_PLUGIN_URL . '/flux/category_' . $post->ID . '.txt' ;?>">Lien</a></h2>
        </div>
        <div class="row">
          <div class="title-bloc">
            <span>Activer ?</span>
          </div>
          <div class="button-switch">
            <label for="flux-status" class="lbl-on" <?php if( !$flux_status ) echo '"style=display: none;"'; ?> >On</label>
            <input type="checkbox" id="flux-status" class="switch" name="flux-status" value="<?php echo $flux_status; ?>" <?php if( $flux_status ) echo "checked" ?> />
            <label for="flux-status" class="lbl-off"  <?php if( $flux_status ) echo '"style=display: none;"'; ?>>Off</label>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="title-bloc">
          <span>Mapping</span>
        </div>
        <div class="mapping-container">
          <?php foreach ($flux_product_categories as $flux_categories => $product_categories): ?>
            <div class="mapping-item">
              <div class="mapping-item--number">
                <span><?php echo $itemNumber + 1; $itemNumber ++; ?></span>
              </div>
              <div class="mapping-item--fields">
                <div class="mapping-item--field">
                    <label>Catégorie(s) Flux</label>
                    <input type="text" value="<?php echo $flux_categories; ?>" name="flux-categories[]" />
                </div>
                <div class="mapping-item--field">
                  <label>Catégorie(s) Produit</label>
                  <select class="select-products" multiple="multiple" name="product-categories-<?php echo $itemNumber; ?>[]">;
                  <?php
                  foreach ($product_cats as $key => $product_cat) {
                    ?>
                    <option value="<?php echo $product_cat->term_id; ?>" <?php if( in_array( $product_cat->term_id, $product_categories ) ) echo 'selected';  ?> >
                      <?php _e( $product_cat->name ); ?>
                    </option>
                    <?php
                  }
                  ?>
                  </select>
                </div>
              </div>
              <div class="mapping-item--delete"><span>-</span></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mapping-item-add-field">
        <button class="button mapping-item-add-btn">Ajouter</button>
      </div>
    </div>
<?php
}

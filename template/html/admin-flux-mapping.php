<?php
  $product_cats = get_terms(
    array(
      'taxonomy' => 'product_cat',
      'hide_empty' => false
     )
   );

   var_dump( $product_cats );
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
          <div class="mapping-item">
            <div class="mapping-item--title">Nom du flux</div>
            <div class="input-field mapping-item--flux">
              <label>Categorie Flux</label>
              <input type="text" />
            </div>
            <div class="input-field mapping-item--category">
              <label>Categorie Produits</label>
              <select class="js-example-basic-multiple" name="states[]" multiple="multiple">
              <?php
              foreach ($product_cats as $key => $product_cat) {
                ?>
                <option value="<?php echo $product_cat->term_id; ?>"><?php _e( $product_cat->name ); ?></option>
                <?php
              }
              ?>
              </select>
            </div>
          </div>
      </div>
    </div>
  </div>
</div>

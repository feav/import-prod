jQuery(document).ready( function($){

  if( $('.button-switch input#flux-status').length ){
     $('.button-switch input#flux-status').change( function(event){
        if( $('.button-switch input#flux-status').is(':checked') ){
          $('.button-switch .lbl-off').hide();
          $('.button-switch').addClass('switch-enable');
          $('.button-switch .lbl-on').show();

        }else{
          $('.button-switch .lbl-on').hide();
          $('.button-switch').removeClass('switch-enable');
          $('.button-switch .lbl-off').show();
        }
     });

     if( $('.button-switch input#flux-status').is(':checked') ){
       $('.button-switch .lbl-off').hide();
       $('.button-switch').addClass('switch-enable');
       $('.button-switch .lbl-on').show();

     }else{
       $('.button-switch .lbl-on').hide();
       $('.button-switch').removeClass('switch-enable');
       $('.button-switch .lbl-off').show();
     }
  }

  $('.select-products').select2();

  $('.validate-btn').click( function(event){
      event.preventDefault();
      let flux_id = jQuery( this ).data('flux-id');
      let flux_category = $( 'input#flux-' + flux_id ).val();
      let product_categories = $( 'select#flux-category-' + flux_id ).val();
      console.log( product_categories );
      $.post(
        flux_mapping.ajaxurl, // url
        {
          'action': 'load_products',
          'flux_id': flux_id,
          'flux_category': flux_category,
          'product_categories': product_categories
        }, // data
        function( responseData, textStatus,  jqXHR ){ // success
            console.log( responseData );
        }

      );

  });



  // Adding mapping-item
  $('.mapping-item-add-btn').click( function(event){
    event.preventDefault();
    itemNumber = $('.mapping-item').length;

    let template = `
    <div class="mapping-item">
      <div class="mapping-item--number">
        <span>${ itemNumber + 1 }</span>
      </div>
      <div class="mapping-item--fields">
        <div class="mapping-item--field">
            <label>Catégorie(s) Flux</label>
            <input type="text" value="" name="flux-categories[]"/>
        </div>
        <div class="mapping-item--field">
          <label>Catégorie(s) Produit</label>
          <select class="select-products" multiple="multiple" name="product-categories-${ itemNumber + 1 }[]">`;
          $('#select-options').find('option').each( function( index, element){
            template += `<option value=${element.value}>${element.text}</option>`;
          });
        template += `
          </select>
        </div>
      </div>
      <div class="mapping-item--delete"><span>-</span></div>
    </div>`;

    $('.mapping-container').append( template );

    $('#select-options').find('option').each( function( index, element){

    });

    // Add delete mapping item event after his creation
    $('.mapping-item--delete').each( function(index, element){
      $( element ).click( function(event){
          $( element ).parent().remove();
      });
    });

    // Add select2 fonctionnalities
    $('.select-products').select2();
  });

  // Delete mapping item
  $('.mapping-item--delete').each( function(index, element){
    $( element ).click( function(event){
        $( element ).parent().remove();
    });
  });





  // checkbox in admin
  $('#activate-flux-mapping-update').change( function(e){

      if( $(this).is(":checked") ) {
          $('#activate-flux-mapping-update').val(1);
      }else{
          $('#activate-flux-mapping-update').val(0);
       }
  });

  // checkbox in admin
  $('#flux-status').change( function(e){
      if( $(this).is(":checked") ) {
          $('#flux-status').val(1);
      }else{
          $('#flux-status').val(0);
       }
  });

});

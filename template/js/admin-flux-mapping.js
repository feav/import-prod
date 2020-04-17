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
  }

  $('.js-example-basic-multiple').select2();


});

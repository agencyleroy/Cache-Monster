(function( $ ) {

  $.fn.cmUiWidget = function(apiUrl) {

    var template = "\
      <div id='cache-monster-widget'> \
        <button>Purge</button> \
      </div>";

    jQuery(template)
      .appendTo(this)
      .find('button')
      .on('click', function(event) {
        event.preventDefault();
        $btn = $(this);
        $widget = $btn.parent();

        $widget.addClass('loading');
        $btn.prop('disabled', true);

        //post currentUrl to endpoint
        $.getJSON(apiUrl)
         .done(function(){
           $widget.removeClass('loading').addClass('success');
           console.log("Cache Monster success: "+apiUrl);
         })
         .fail(function(jqXHR, textStatus, errorThrown){
           $widget.removeClass('loading').addClass('error');
           console.log("Cache Monster error: "+jqXHR.responseJSON.error);
         })
         .always(function(){
           $btn.prop('disabled', false);
           window.setTimeout(function(){
             $widget.removeClass('success error loading');
           }, 2000)
         })
      });

      return this;
  };

}( jQuery ));

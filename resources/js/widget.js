(function( $ ) {

  $.fn.cmUiWidget = function(apiUrl) {
    
    jQuery('<div id="cache-monster-widget"><a href="#" class="button radius">Purge page</a></div>')
      .appendTo(this)
      .find('a')
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
         })
         .fail(function(jqXHR, textStatus, errorThrown){
           $widget.removeClass('loading').addClass('error');
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

(function( $ ) {

  $.fn.cmUiWidget = function(actionUserLoggedIn, actionPurgeUrl) {

    var template = "\
      <div id='cache-monster-widget'> \
        <button class='button'>Purge cache</button> \
      </div>";

    self = this;

    // NOTE: we use $.post instead of $.getJSON because varnish caches GETs
    jQuery.post(actionUserLoggedIn, function(result) {
      if (result.logged_in == true) {
        jQuery(template)
          .appendTo(self)
          .find('button')
          .on('click', function(event) {
            event.preventDefault();
            $btn = $(this);
            $widget = $btn.parent();

            $widget.addClass('loading');
            $btn.prop('disabled', true);

            //post currentUrl to endpoint
            jQuery.ajax({
              type: 'POST',
              url: actionPurgeUrl,
              dataType: 'JSON'
            })
              .done(function(){
               $widget.removeClass('loading').addClass('success');
               console.log("Cache Monster success: "+actionPurgeUrl);
               window.location.reload(true);
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
      }
    });

    return this;
  };

}( jQuery ));

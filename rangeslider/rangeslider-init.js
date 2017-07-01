jQuery(document).ready(function($){

    var slidermarkup = $('<div class="shortscore" id="rangeslider_output"></div><input id="rangeslider" name="score" type="range" min="1" max="10" step="1" value="6">');

    if($('body').hasClass('logged-in')){
        $('#score').hide().after(slidermarkup);
    } else {
        $('.must-log-in').hide();
        $('.wp-social-login-widget').hide();
        $('#fakescore').prepend(slidermarkup);
    }

    var $inputRange = $('#rangeslider');

    $inputRange.rangeslider({
        polyfill: false,
        onSlide: function(position, value) {
          valueOutput(value);
         },
        onSlideEnd: function(position, value) {
            console.info("HEY");
            $('#fakescore').delay( 100 ).hide();
            $('.must-log-in').delay( 100 ).fadeIn("20");
        }
    });

    function valueOutput(element) {
        if (element.value === undefined) {
          var value = element;
        } else {
          var value = element.value;
        }
        var output = document.getElementById('rangeslider_output');
        $('#score').val(value);
        output.innerHTML = value;
        $('#rangeslider_output').removeClass().addClass('shortscore shortscore-' + value);
    }

    for (var i = $inputRange.length - 1; i >= 0; i--) {
        valueOutput($inputRange[i]);
    }



    $.fn.removeClassPrefix = function(prefix) {
        this.each(function(i, el) {
            var classes = el.className.split(" ").filter(function(c) {
                return c.lastIndexOf(prefix, 0) !== 0;
            });
            el.className = $.trim(classes.join(" "));
        });
        return this;
    };
});

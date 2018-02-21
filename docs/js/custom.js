(function($) {
    "use strict";

    $(function() {
        
        $("[data-hash-icon]").each(function () {
            var t = $(this);
            var linkType = t.attr("data-hash-icon");
            var linkHash = "";
            if (linkType == "this" && t.prop("id") != "") {
                linkHash = t.prop("id");
            }else if(linkType.indexOf("parent") === 0){
                var p = t.parents(linkType.replace("parent", ""));
                linkHash = p.prop("id");
            }
            if (linkHash) {
                t.addClass("hasHashIcon");
                t.prepend($("<a>").attr("href", "#"+linkHash).addClass("hashIcon glyphicon glyphicon-link"));                
            }            
        });

        $('a[href*=#]:not([href=#])').click(function() {
            var urlHash = this.hash;
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html,body').animate({
                    scrollTop: target.offset().top
                }, 1000, function () {
                    window.location.hash = urlHash;
                    console.log("Set hash: ", urlHash);
                });
                return false;
            }
        });

        $('body').scrollspy({
            target: '.docs-sidebar'
        });

        $('[data-spy="scroll"]').each(function () {
          var $spy = $(this).scrollspy('refresh')
        });
    });
})(jQuery);

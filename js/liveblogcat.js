jQuery(function ($) {
    "use strict";

    const GRMOnHanldeCategories = () => {
        setTimeout(function () {
            const $isCate = $('.editor-post-taxonomies__hierarchical-terms-list[aria-label=Categories]')
            if ($isCate.length === 0) return;
            __onLoad($isCate)

        }, 3000)

        __onChange()

        function __onLoad($isCate) {
            let $label = $isCate.find('.components-checkbox-control__label');
            __onHanlde($label)
        }

        function __onChange() {
            $("body").on(
                "change",
                ".editor-post-taxonomies__hierarchical-terms-list[aria-label=Categories]",
                function (e) {
                    let $label = $(this).find('.components-checkbox-control__label');
                    __onHanlde($label)
                }
            );
        }

        function __onHanlde($data) {
            $data.each(function () {
                if ($(this)['0'].innerText == 'Live Blog') {
                    let $value = $(this).parents('.components-base-control__field').find('input')['0'].checked
                    if ($value) {
                        $('#liveblog_meta_box').show();
                        console.log($value);
                    } else {
                        $('#liveblog_meta_box').hide();
                        console.log($value);
                    }
                }
            })
        }
    }

    $(window).on('load', function () {
        GRMOnHanldeCategories()
        setTimeout(()=>{
            var iframes = document.querySelectorAll('iframe');
            iframes.forEach(function(iframe) {
                var iframeDocument = iframe.contentWindow.document;
                var iframeBody = iframeDocument.body;
                iframeBody.style.maxWidth = '100%';
            
            });
        },3000)
    })
})
(function () {

    var widget_class = 'webasyst-shop-script-product';

    // TODO: multi products on page
    function init() {
        var containers = Array.prototype.slice.apply( document.getElementsByClassName(widget_class) ),
            length = containers.length;

        for (var i = 0; i < length; i += 1) {
            addFrame( containers[i] );
        }
    }

    function addFrame( $wrapper ) {
        if (!$wrapper) {
            return;
        }

        if ($wrapper.getAttribute('id')) {
            return;
        }

        var id = widget_class + '-' + ('' + Math.random()).slice(2);

        $wrapper.setAttribute('id', id + '-wrapper');

        var url = $wrapper.getAttribute('data-storefront');
        if ($wrapper.getAttribute('data-url')) {
            url = $wrapper.getAttribute('data-url');
            $wrapper.removeAttribute('data-url');
        }
        if (!url) {
            return;
        }

        var params = [];
        var attributes = $wrapper.attributes;
        var len = attributes.length;
        var attr = null, name, val;
        for (var i = 0; i < len; i += 1) {
            attr = attributes[i];
            name = attr.nodeName;
            val = attr.nodeValue;
            if (name.slice(0, 5) === 'data-') {
                params.push(name.slice(5).replace('-', '_') + '=' + val);
            }
        }

        var width = ( $wrapper.getAttribute("data-width") ||  512 );
        var height = $wrapper.getAttribute("data-height");
        var parentNode = $wrapper.parentNode;
        var iframe = document.createElement('iframe');

        iframe.setAttribute('id', id + '-iframe');

        // glue url with params, maybe ? or &
        var glue = url.indexOf('?') !== -1 ? '&' : '?';

        // sanitize right side of url
        url = url.replace(/[\?&]*$/, '');

        iframe.src = url + glue + params.join('&') + '&html_id=' + id + '&iframe_width=' + width;
        parentNode.replaceChild(iframe, $wrapper);

        iframe.setAttribute('style', 'width: ' + width + 'px; border: 0;');
        iframe.width = width;

        iframe.onload = function() {
            updateHeight(iframe, height);
        }
    }

    function updateHeight(iframe, iframe_height) {
        try {
            var document = iframe.contentWindow.document;
            iframe_height = document.getElementsByTagName("form")[0].offsetHeight;
        } catch(e) {
            console.log(e);
        }

        iframe.style.height = iframe_height + "px";
    }

    function onReady(fn) {
        var called = 0;
        var ofn = fn;
        fn = function () {
            called = 1;
            ofn();
        };
        if (document.readyState === 'complete') {
            fn();
        } else if (window.addEventListener) {
            window.addEventListener("load", function () {
                fn();
            });
        } else if (window.attachEvent) {
            window.attachEvent("onload", function () {
                fn();
            });
        } else {
            setTimeout(function () {
                fn();
            }, 1000);
        }
        setTimeout(function () {
            if (!called) {
                fn();
            }
        }, 5000);
    }

    onReady(init);

})();
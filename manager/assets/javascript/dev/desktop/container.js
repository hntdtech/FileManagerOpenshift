define([
    "jquery",
    "define",
    "selector",
    "scroll",
    "contextmenu",
    "content"
], function(
    jquery,
    define,
    selector,
    scroll,
    contextmenu,
    content
) {
    return {
        isInitFixSizeChild: false,

        fixSizeChild: function() {
            var self         = this;

            // Get window size
            var windowWidth  = jquery(window).width();
            var windowHeight = jquery(window).height();

            // Get header, sidebar size
            var headerHeight = selector.header.height();
            var sidebarWidth = selector.sidebar.width();

            // Css fix
            var cssFix = {
                display: self.isInitFixSizeChild == false ? "none" : "block",
                height: (windowHeight - headerHeight) + "px",
            };

            // Fix height
            selector.container.css(cssFix);
            selector.sidebar.css(cssFix);

            if (self.isInitFixSizeChild == false)
                selector.loading.css(cssFix).css({ display: "block" });

            selector.headerAction.find("li").each(function() {
                var element = $(this);
                var login   = element.attr("login");

                if (typeof login === "string" && login === "true") {
                    element.css({
                        display: "none"
                    });
                }
            });

            selector.content.css(cssFix);
            selector.content.css({ width: (windowWidth - sidebarWidth) + "px", left: sidebarWidth + "px" });
            selector.header.css({ display: "block" });
            selector.container.css({ display: "block", top: headerHeight + "px" });

            scroll.emulator(define.sidebarFileSelector);
            content.file.fixSize();
            scroll.emulator(define.contentFileManagerListSelector);

            if (self.isInitFixSizeChild)
                self.stopLoading();

            self.isInitFixSizeChild = true;
        },

        registerWindowOnResize: function() {
            var self = this;

            jquery(window).unbind("resize").bind("resize", function(handler) {
                self.fixSizeChild();
            });
        },

        registerDocumentOnMouseMove: function() {
            var self = this;

            jquery(document).unbind("mousemove").bind("mousemove", function(e) {
                contextmenu.onMouseMove(self, e);
            });
        },

        startLoading: function(notice) {
            if (selector.loading.css("display") === "block") {
                if (typeof notice === "string")
                    selector.loadingNotice.html(notice);

                return;
            }

            if (typeof notice === "string")
                selector.loadingNotice.html(notice);

            selector.loading.css({ display: "block", opacity: 0 }).animate({ opacity: 1 }, define.time.animate_show);
        },

        stopLoading: function() {
            if (selector.loading.css("display") === "none") {
                selector.loadingNotice.html("");
                return;
            }

            selector.loading.animate({ opacity: 0 }, define.time.animate_hidden, function() {
                selector.loadingNotice.html("");

                selector.loading.css({
                    display: "none"
                });
            });
        },

        show: function() {
            var css  = { display: "block", opacity: 0 };
            var anim = { opacity: 1 };

            selector.sidebar        .stop().css(css).animate(anim, define.time.time_show);
            selector.sidebarFile    .stop().css(css).animate(anim, define.time.time_show);
            selector.sidebarDatabase.stop().css(css).animate(anim, define.time.time_show);
            selector.content.stop() .css(css).animate(anim, define.time.time_show);

            selector.headerAction.find("li").each(function() {
                var element = $(this);
                var login   = element.attr("login");

                if (typeof login === "string" && login === "true")
                    element.stop().css({
                        display: "inline-block"
                    }).animate(anim, define.time_show);
            });

            scroll.emulator(define.sidebarFileSelector);
            scroll.emulator(define.contentFileManagerListSelector);
        },

        hidden: function() {
            var css  = { display: "none" };
            var anim = { opacity: 0 };

            selector.sidebar.stop().animate(anim, define.time.time_show, function() {
                $(this).css(css);
            });

            selector.sidebarFile.stop().animate(anim, define.time.time_show, function() {
                $(this).css(css);
            });

            selector.sidebarDatabase.stop().animate(anim, define.time.time_show, function() {
                $(this).css(css);
            });

            selector.content.stop().animate(anim, define.time.time_show, function() {
                $(this).css(css);
            });

            selector.headerAction.find("li").each(function() {
                var element = $(this);
                var login   = element.attr("login");

                if (typeof login === "string" && login === "true") {
                    element.css({
                        display: "none"
                    });
                }
            });
        }
    };
});
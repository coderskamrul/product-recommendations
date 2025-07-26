(($) => {
  var WCProductRecommendationsAdmin = {
    init: function () {
      this.bindEvents();
      this.initTabs();
    },

    bindEvents: function () {
      // Tab navigation
      $(".upspr-nav-tab").on("click", this.handleTabClick);

      // Tools buttons
      $("#upspr-rebuild-recommendations").on(
        "click",
        this.rebuildRecommendations
      );
      $("#upspr-clear-recommendations").on("click", this.clearRecommendations);

      // Engine selection changes
      $('select[name="wc_product_recommendations_settings[active_engine]"]').on(
        "change",
        this.handleEngineChange
      );
    },

    initTabs: () => {
      // Show first tab by default
      $(".upspr-tab-content:first").addClass("active");
      $(".nav-tab:first").addClass("nav-tab-active");
    },

    handleTabClick: function (e) {
      e.preventDefault();

      var $tab = $(this);
      var target = $tab.attr("href");

      // Remove active classes
      $(".upspr-nav-tab").removeClass("nav-tab-active");
      $(".upspr-tab-content").removeClass("active");

      // Add active classes
      $tab.addClass("nav-tab-active");
      $(target).addClass("active");
    },

    rebuildRecommendations: function (e) {
      e.preventDefault();

      var $button = $(this);
      var originalText = $button.text();

      $button.prop("disabled", true).text("Rebuilding...");

      var ajaxurl = window.ajaxurl; // Declare ajaxurl
      var wp = window.wp; // Declare wp

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "upspr_rebuild_recommendations",
          nonce: wp.ajax.settings.nonce,
        },
        success: (response) => {
          if (response.success) {
            WCProductRecommendationsAdmin.showNotice("success", response.data);
          } else {
            WCProductRecommendationsAdmin.showNotice("error", response.data);
          }
        },
        error: () => {
          WCProductRecommendationsAdmin.showNotice(
            "error",
            "An error occurred while rebuilding recommendations."
          );
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    clearRecommendations: function (e) {
      e.preventDefault();

      if (
        !confirm(
          "Are you sure you want to clear all recommendation data? This action cannot be undone."
        )
      ) {
        return;
      }

      var $button = $(this);
      var originalText = $button.text();

      $button.prop("disabled", true).text("Clearing...");

      var ajaxurl = window.ajaxurl; // Declare ajaxurl
      var wp = window.wp; // Declare wp

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "upspr_clear_recommendations",
          nonce: wp.ajax.settings.nonce,
        },
        success: (response) => {
          if (response.success) {
            WCProductRecommendationsAdmin.showNotice("success", response.data);
          } else {
            WCProductRecommendationsAdmin.showNotice("error", response.data);
          }
        },
        error: () => {
          WCProductRecommendationsAdmin.showNotice(
            "error",
            "An error occurred while clearing recommendations."
          );
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText);
        },
      });
    },

    handleEngineChange: function () {
      var engine = $(this).val();

      // Show/hide relevant engine settings
      $(".engine-settings").hide();
      $(".engine-settings-" + engine).show();
    },

    showNotice: (type, message) => {
      var noticeClass = "notice-" + type;
      var $notice = $(
        '<div class="notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          message +
          "</p></div>"
      );

      $(".upspr-product-recommendations-admin").prepend($notice);

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    },
  };

  // Initialize when document is ready
  $(document).ready(() => {
    WCProductRecommendationsAdmin.init();
  });
})(window.jQuery); // Use window.jQuery to ensure jQuery is declared

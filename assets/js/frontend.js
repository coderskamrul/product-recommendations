;(($) => {
  var wc_product_recommendations = window.wc_product_recommendations // Declare the variable before using it

  var WCProductRecommendations = {
    init: function () {
      this.bindEvents()
    },

    bindEvents: function () {
      // Listen for cart updates
      $(document.body).on("updated_cart_totals", this.refreshCartRecommendations)
      $(document.body).on("updated_checkout", this.refreshCheckoutRecommendations)

      // Listen for add to cart events
      $(document.body).on("added_to_cart", this.handleAddToCart)

      // Handle quantity changes
      $(document).on("change", ".cart .qty", this.handleQuantityChange)
    },

    refreshCartRecommendations: () => {
      var $container = $("#wc-product-recommendations-cart")
      if ($container.length === 0) {
        return
      }

      $container.addClass("loading")

      $.ajax({
        url: wc_product_recommendations.ajax_url,
        type: "POST",
        data: {
          action: "wc_refresh_cart_recommendations",
          nonce: wc_product_recommendations.nonce,
        },
        success: (response) => {
          if (response.success && response.data.html) {
            $container.html(response.data.html)
          } else {
            $container.empty()
          }
        },
        error: () => {
          console.log("Error refreshing cart recommendations")
        },
        complete: () => {
          $container.removeClass("loading")
        },
      })
    },

    refreshCheckoutRecommendations: () => {
      var $container = $("#wc-product-recommendations-checkout")
      if ($container.length === 0) {
        return
      }

      $container.addClass("loading")

      $.ajax({
        url: wc_product_recommendations.ajax_url,
        type: "POST",
        data: {
          action: "wc_refresh_cart_recommendations",
          nonce: wc_product_recommendations.nonce,
        },
        success: (response) => {
          if (response.success && response.data.html) {
            $container.html(response.data.html)
          } else {
            $container.empty()
          }
        },
        error: () => {
          console.log("Error refreshing checkout recommendations")
        },
        complete: () => {
          $container.removeClass("loading")
        },
      })
    },

    handleAddToCart: (event, fragments, cart_hash, $button) => {
      // Refresh recommendations after adding to cart
      setTimeout(() => {
        WCProductRecommendations.refreshCartRecommendations()
        WCProductRecommendations.refreshCheckoutRecommendations()
      }, 500)
    },

    handleQuantityChange: () => {
      // Debounce quantity changes
      clearTimeout(WCProductRecommendations.quantityTimeout)
      WCProductRecommendations.quantityTimeout = setTimeout(() => {
        WCProductRecommendations.refreshCartRecommendations()
      }, 1000)
    },
  }

  // Initialize when document is ready
  $(document).ready(() => {
    WCProductRecommendations.init()
  })
})(window.jQuery) // Declare the variable before using it

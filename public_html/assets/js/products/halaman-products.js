$(document).ready(function () {
  const isLocal = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";

  /**
   * Logs messages to the console only in a local environment.
   * @param {...any} args - Messages or objects to log.
   */
  function log(...args) {
    if (isLocal) console.log(...args);
  }

  /**
   * Logs warnings to the console only in a local environment.
   * @param {...any} args - Warnings to log.
   */
  function warn(...args) {
    if (isLocal) console.warn(...args);
  }

  /**
   * Logs errors to the console only in a local environment.
   * @param {...any} args - Errors to log.
   */
  function error(...args) {
    if (isLocal) console.error(...args);
  }

  log("Document ready, initializing...");

  const productContainer = $("#halamanProductsContainer");

  /**
   * Loads products based on the selected filters and updates the UI.
   */
  function loadProducts() {
    log("loadProducts() called");

    const category = $("#halamanProductsCategoryFilter").val();
    const minPrice = $("#halamanProductsMinPrice").val() || null;
    const maxPrice = $("#halamanProductsMaxPrice").val() || null;
    const sortBy = $("#halamanProductsSortBy").val();

    log("Filter values:", { category, minPrice, maxPrice, sortBy });

    if (typeof BASE_URL === "undefined") {
      error("BASE_URL is not defined. Make sure it is set in the HTML.");
      return;
    }

    let url = new URL(BASE_URL + "api-proxy.php");
    url.searchParams.append("action", "filter_products");

    if (category) url.searchParams.append("categories[]", category);
    if (minPrice) url.searchParams.append("min_price", minPrice);
    if (maxPrice) url.searchParams.append("max_price", maxPrice);
    if (sortBy) url.searchParams.append("sort_by", sortBy);

    log("Fetching data from:", url.toString());

    productContainer.html(`
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading products...</p>
            </div>
        `);

    fetch(url)
      .then((response) => {
        log("Response received:", response);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
      })
      .then((data) => {
        log("Data received:", data);

        productContainer.empty();

        if (!Array.isArray(data) || data.length === 0) {
          warn("No products found.");
          productContainer.html(`
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No products match your filter criteria.</p>
                        </div>
                    `);
          return;
        }

        const productsGrid = $('<div class="row row-cols-1 row-cols-md-3 g-4"></div>');

        data.forEach((product) => {
          log("Processing product:", product);

          const productCard = `
                        <div class="col mb-4">
                            <div class="card h-100 shadow-sm">
                                <img src="${product.image}" class="card-img-top p-3" alt="${product.name}" style="height: 250px; object-fit: contain">
                                <div class="card-body">
                                    <h5 class="card-title">${product.name}</h5>
                                    <p class="card-text text-muted">${product.description}</p>
                                    <p class="text-primary fw-bold mb-0">${product.price}</p>                
                                    <a href="#" class="btn btn-primary btn-sm mt-2">
                                        <i class="fa-solid fa-circle-info me-1"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
          productsGrid.append(productCard);
        });

        productContainer.append(productsGrid);
        log("Products displayed successfully.");
      })
      .catch((err) => {
        error("Fetch Error:", err);
        productContainer.html(`
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <p class="text-danger">Error loading products. Please try again.</p>
                    </div>
                `);
      });
  }

  /**
   * Handles the click event for the apply filter button.
   * Validates price range inputs and triggers product loading.
   *
   * @function
   * @listens jQuery#click
   * @param {Event} e - The click event object
   * @returns {void}
   */
  $("#halamanProductsApplyFilter").click(function (e) {
    e.preventDefault();
    log("Apply filter button clicked.");

    // Validate price range inputs
    const minVal = parseInt($("#minPrice").val());
    const maxVal = parseInt($("#maxPrice").val());

    if (minVal && maxVal && minVal > maxVal) {
      warn("Min price is greater than max price. Adjusting max price.");
      $("#maxPrice").val(minVal);
    }

    loadProducts();
  });

  // Tambahkan event handler untuk clear filter:
  $("#halamanProductsClearFilter").click(function (e) {
    e.preventDefault();
    log("Clear filter button clicked.");

    // Reset semua nilai filter ke default
    $("#halamanProductsCategoryFilter").val("");
    $("#halamanProductsMinPrice").val("");
    $("#halamanProductsMaxPrice").val("");
    $("#halamanProductsSortBy").val("latest");

    // Memuat ulang produk dengan filter default
    loadProducts();
  });

  log("Initializing product load...");
  loadProducts();
});

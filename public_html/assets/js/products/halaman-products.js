$(document).ready(function () {
  // 1. Initial Configuration and Constants
  const isLocal = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";

  /** Container element that holds the product cards */
  const productContainer = $("#halamanProductsContainer");

  /** Number of items to display per page */
  const itemsPerPage = 10;

  /** Current pagination page */
  let currentPage = 1;

  /** Total number of pagination pages */
  let totalPages = 1;

  // Ellipsed Configuration
  const ellipsedInstances = [];

  /** Configuration object for Ellipsed.js, controlling line truncation behavior */
  const ELLIPSED_CONFIG = {
    lines: 3,
    ellipsis: "…",
    responsive: true,
    break: "word",
    onChange: (isTruncated) => {
      if (isLocal && isTruncated) log("Text truncated");
    },
  };

  /** Stores the current filter state for fetching product data */
  let currentFilters = {
    category: "",
    minPrice: null,
    maxPrice: null,
    sortBy: "latest",
  };

  // 2. Utility Functions

  /** Logs messages to the console only in local development */
  const log = (...args) => isLocal && console.log(...args);

  /** Logs warnings to the console only in local development */
  const warn = (...args) => isLocal && console.warn(...args);

  /** Logs errors to the console only in local development */
  const error = (...args) => isLocal && console.error(...args);

  // 3. Core Product Loading Functions

  /**
   * Fetches and loads products based on current filters and page.
   * @param {number} page - The page number to load.
   */
  function loadProducts(page = 1) {
    currentPage = page;
    const offset = (page - 1) * itemsPerPage;

    const url = new URL(BASE_URL + "api-proxy.php");
    url.searchParams.append("action", "filter_products");
    if (currentFilters.category) url.searchParams.append("categories[]", currentFilters.category);
    if (currentFilters.minPrice) url.searchParams.append("min_price", currentFilters.minPrice);
    if (currentFilters.maxPrice) url.searchParams.append("max_price", currentFilters.maxPrice);
    if (currentFilters.sortBy) url.searchParams.append("sort_by", currentFilters.sortBy);
    url.searchParams.append("limit", itemsPerPage);
    url.searchParams.append("offset", offset);

    // Execute the request and chain the response handlers
    fetch(url).then(handleResponse).then(handleData).catch(handleError);
  }

  // 4. UI State Management

  /**
   * Displays a user-friendly message when no products match the filter criteria.
   */
  function showNoProductsMessage() {
    productContainer.html(`
      <div class="col-12 text-center py-5">
        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
        <p class="text-muted">No products match your filter criteria.</p>
      </div>
    `);
    $("#paginationContainer").empty();
  }

  // 5. Data Handling Functions

  /**
   * Validates the response status and parses JSON data.
   * @param {Response} response
   * @returns {Promise<Object>}
   */
  function handleResponse(response) {
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    return response.json();
  }

  /**
   * Handles the parsed product data, renders product cards and pagination.
   * @param {Object} data - The product data object from the API.
   */
  function handleData(data) {
    productContainer.empty();
    destroyEllipsedInstances();

    if (!data.data || data.data.length === 0) {
      showNoProductsMessage();
      return;
    }

    totalPages = data.totalPages || 1;
    renderProducts(data.data);
    renderPagination();
    initEllipsed();
  }

  /**
   * Handles errors encountered during product fetch operations.
   * @param {Error} err
   */
  function handleError(err) {
    error("Fetch Error:", err);
    productContainer.html(`
      <div class="col-12 text-center py-5">
        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
        <p class="text-danger">Error loading products. Please try again.</p>
      </div>
    `);
    $("#paginationContainer").empty();
  }

  // 6. Rendering Functions

  /**
   * Renders product cards using the data received from the API.
   * @param {Array<Object>} products - Array of product objects.
   */
  function renderProducts(products) {
    const productsGrid = $('<div class="row row-cols-2 row-cols-lg-5 g-3"></div>');

    products.forEach((product) => {
      productsGrid.append(`
            <div class="col mb-3">
                <a href="${BASE_URL}products/${product.slug}/" 
                   class="card h-100 border-0 bg-transparent text-decoration-none">
                    <div class="card-img-top-container position-relative" 
                         style="height: 180px; background-color: #f8f9fa">
                        <img src="${product.image}" 
                             class="h-100 w-100 object-fit-cover p-2" 
                             alt="${product.name}">
                    </div>
                    <div class="card-body p-3 d-flex flex-column">
                        <h5 class="card-title mb-1 fs-5 fw-bold text-dark">${product.name}</h5>
                        <div class="flex-grow-1">
                            <p class="card-text text-secondary mb-2 fs-6 product-description">${product.description}</p>
                        </div>
                        <div class="mt-auto">
                            <p class="text-primary fw-semibold fs-6 mb-0">${product.price}</p>
                        </div>
                    </div>
                </a>
            </div>
        `);
    });

    productContainer.append(productsGrid);
  }

  /**
   * Renders pagination controls based on the current page and total pages.
   */
  function renderPagination() {
    if (totalPages <= 1) {
      $("#paginationContainer").empty();
      return;
    }

    let html = `<nav><ul class="pagination justify-content-center">`;

    html += `<li class="page-item ${currentPage === 1 ? "disabled" : ""}">
      <a class="page-link" data-page="${currentPage - 1}">Previous</a>
    </li>`;

    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (startPage > 1) {
      html += `<li class="page-item"><a class="page-link" data-page="1">1</a></li>`;
      if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `<li class="page-item ${currentPage === i ? "active" : ""}">
        <a class="page-link" data-page="${i}">${i}</a>
      </li>`;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      html += `<li class="page-item"><a class="page-link" data-page="${totalPages}">${totalPages}</a></li>`;
    }

    html += `<li class="page-item ${currentPage === totalPages ? "disabled" : ""}">
      <a class="page-link" data-page="${currentPage + 1}">Next</a>
    </li>`;

    html += `</ul></nav>`;
    $("#paginationContainer").html(html);
  }

  // 7. Ellipsed Functions

  /**
   * Initializes line clamping for product descriptions using Ellipsed.
   */
  function initEllipsed() {
    $(".product-description").each(function () {
      const EllipsedClass = typeof Ellipsed !== "undefined" ? Ellipsed : window.Ellipsed;
      const instance = new EllipsedClass(this, ELLIPSED_CONFIG);
      ellipsedInstances.push(instance);
    });
  }

  /**
   * Destroys all active Ellipsed instances to avoid memory leaks.
   */
  function destroyEllipsedInstances() {
    ellipsedInstances.forEach((instance) => instance.destroy());
    ellipsedInstances.length = 0;
  }

  // 8. Window Resize Handler

  // Update ellipsed content after resizing with delay (debounce)
  let resizeTimer;
  $(window).on("resize", () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      ellipsedInstances.forEach((instance) => instance.update());
    }, 250);
  });

  // 9. Filter Management

  /**
   * Reads the filter form inputs and updates `currentFilters`.
   */
  function updateFilterValues() {
    currentFilters = {
      category: $("#halamanProductsCategoryFilter").val(),
      minPrice: $("#halamanProductsMinPrice").val() || null,
      maxPrice: $("#halamanProductsMaxPrice").val() || null,
      sortBy: $("#halamanProductsSortBy").val(),
    };
    log("Filters updated (not applied yet):", currentFilters);
  }

  // 10. Event Handlers

  // Apply filters and fetch new products
  $("#halamanProductsApplyFilter").click(function (e) {
    e.preventDefault();
    updateFilterValues();
    loadProducts(1);
  });

  // Update filters on input change (without reloading data)
  $("#halamanProductsCategoryFilter, #halamanProductsMinPrice, #halamanProductsMaxPrice, #halamanProductsSortBy").on(
    "change input",
    updateFilterValues,
  );

  // Constants for default filters
  const DEFAULT_FILTERS = {
    category: "",
    minPrice: null,
    maxPrice: null,
    sortBy: "latest",
  };

  // Reset all filters to their default state
  $("#halamanProductsClearFilter").click(function (e) {
    e.preventDefault();

    $("#halamanProductsCategoryFilter").val("");
    $("#halamanProductsMinPrice").val("");
    $("#halamanProductsMaxPrice").val("");
    $("#halamanProductsSortBy").val("latest");

    currentFilters = { ...DEFAULT_FILTERS };

    const $icon = $(this).find("i");
    const originalIcon = $icon.attr("class");
    $icon.attr("class", "fas fa-check me-1");

    setTimeout(() => {
      $icon.attr("class", originalIcon);
    }, 800);

    loadProducts(1);
  });

  // Handle pagination click
  $(document).on("click", ".page-link", function (e) {
    e.preventDefault();
    const page = $(this).data("page");
    if (page >= 1 && page <= totalPages) {
      loadProducts(page);
      // Scroll to product container after loading
      $("html, body").animate({ scrollTop: productContainer.offset().top - 20 }, 300);
    }
  });

  // 11. Initialization
  updateFilterValues(); // Sync filters with UI
  loadProducts(1); // Initial product load
});

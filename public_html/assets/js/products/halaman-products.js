$(document).ready(function () {
  // 1. Initial Configuration and Constants
  const isLocal = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
  const productContainer = $("#halamanProductsContainer");
  const itemsPerPage = 10;
  let currentPage = 1;
  let totalPages = 1;

  let currentFilters = {
    category: "",
    minPrice: null,
    maxPrice: null,
    sortBy: "latest",
  };

  // 2. Utility Functions
  /**
   * Logs messages to the console only in a local environment.
   * @param {...any} args - Messages to log.
   */
  const log = (...args) => isLocal && console.log(...args);

  /**
   * Logs warnings to the console only in a local environment.
   * @param {...any} args - Warnings to log.
   */
  const warn = (...args) => isLocal && console.warn(...args);

  /**
   * Logs errors to the console only in a local environment.
   * @param {...any} args - Errors to log.
   */
  const error = (...args) => isLocal && console.error(...args);

  // 3. Core Product Loading Functions
  /**
   * Loads products based on the current filters and pagination.
   * @param {number} [page=1] - The page number to load.
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

    fetch(url).then(handleResponse).then(handleData).catch(handleError);
  }

  // 4. UI State Management
  /**
   * Displays a message when no products match the filters.
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
   * Handles the response from the fetch API.
   * @param {Response} response - The fetch response object.
   * @returns {Promise<Object>} - Parsed JSON data.
   */
  function handleResponse(response) {
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    return response.json();
  }

  /**
   * Processes and displays the fetched product data.
   * @param {Object} data - The response containing product data.
   */
  function handleData(data) {
    productContainer.empty();

    if (!data.data || data.data.length === 0) {
      showNoProductsMessage();
      return;
    }

    totalPages = data.totalPages || 1;
    renderProducts(data.data);
    renderPagination();
  }

  /**
   * Handles errors when fetching products.
   * @param {Error} err - The error object.
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
   * Renders product cards on the page.
   * @param {Array} products - Array of product objects.
   */
  function renderProducts(products) {
    const productsGrid = $('<div class="row row-cols-1 row-cols-md-3 g-4"></div>');

    products.forEach((product) => {
      productsGrid.append(`
        <div class="col mb-4">
          <div class="card h-100 shadow-sm">
            <img src="${product.image}" class="card-img-top p-3" alt="${product.name}" style="height: 250px; object-fit: contain">
            <div class="card-body">
              <h5 class="card-title">${product.name}</h5>
              <p class="card-text text-muted">${product.description}</p>
              <p class="text-primary fw-bold mb-0">${product.price}</p>                
              <a href="${BASE_URL}products/${product.slug}/" class="btn btn-primary btn-sm mt-2">
                <i class="fa-solid fa-circle-info me-1"></i> View Details
              </a>
            </div>
          </div>
        </div>
      `);
    });

    productContainer.append(productsGrid);
  }

  /**
   * Renders pagination controls.
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

  // 7. Filter Management
  /**
   * Updates filter values based on user input.
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

  // 8. Event Handlers
  $("#halamanProductsApplyFilter").click(function (e) {
    e.preventDefault();
    updateFilterValues();
    loadProducts(1);
  });

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
  $("#halamanProductsClearFilter").click(function (e) {
    e.preventDefault();

    $("#halamanProductsCategoryFilter").val("");
    $("#halamanProductsMinPrice").val("");
    $("#halamanProductsMaxPrice").val("");
    $("#halamanProductsSortBy").val("latest");

    currentFilters = { ...DEFAULT_FILTERS };

    const $btn = $(this);
    $btn.blur().addClass("btn-success").html('<i class="fas fa-check-circle me-1"></i> Filters Cleared!');

    setTimeout(() => {
      $btn.removeClass("btn-success").html('<i class="fas fa-broom me-1"></i> Clear Filters');
    }, 1500);

    loadProducts(1);
  });

  $(document).on("click", ".page-link", function (e) {
    e.preventDefault();
    const page = $(this).data("page");
    if (page >= 1 && page <= totalPages) {
      loadProducts(page);
      $("html, body").animate({ scrollTop: productContainer.offset().top - 20 }, 300);
    }
  });

  // 9. Initialization
  updateFilterValues();
  loadProducts(1);
});

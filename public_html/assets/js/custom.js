// ==================== JS UNTUK SCROLL TO TOP ==================== //
document.addEventListener("DOMContentLoaded", function () {
  const scrollToTopBtn = document.getElementById("scrollToTopBtn");

  // Cek apakah elemen scrollToTopBtn ada
  if (scrollToTopBtn) {
    // Menampilkan atau menyembunyikan tombol scroll to top
    window.addEventListener("scroll", function () {
      if (window.scrollY > 300) {
        // Menampilkan tombol jika menggulir lebih dari 300px
        scrollToTopBtn.style.display = "block";
      } else {
        scrollToTopBtn.style.display = "none";
      }
    });

    // Menambahkan efek scroll ke atas saat tombol diklik
    scrollToTopBtn.addEventListener("click", function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  } else {
    console.log("Elemen scrollToTopBtn tidak ditemukan, kode JavaScript dinonaktifkan.");
  }
});
// ==================== AKHIR JS UNTUK SCROLL TO TOP ==================== //

// ==================== JS UNTUK NAVBAR BERUBAH WARNA ==================== //
$(function () {
  const navbar = $(".navbar");

  if (navbar.length) {
    $(window).on("scroll", function () {
      if ($(window).scrollTop() > 10) {
        navbar.addClass("active");
      } else {
        navbar.removeClass("active");
      }
    });
  } else {
    console.log("Elemen navbar tidak ditemukan, kode JavaScript dinonaktifkan.");
  }
});
// ==================== AKHIR JS UNTUK NAVBAR BERUBAH WARNA ==================== //

// ==================== JS UNTUK successModal-forgotpassword ==================== //
document.addEventListener("DOMContentLoaded", function () {
  var successModalElement = document.getElementById("successModal-forgotpassword");

  if (successModalElement) {
    var myModal = new bootstrap.Modal(successModalElement);
    myModal.show();
  } else {
    console.log("Script untuk successModal-forgotpassword dinonaktifkan karena elemen tidak ditemukan.");
  }
});
// ==================== AKHIR JS UNTUK successModal-forgotpassword ==================== //

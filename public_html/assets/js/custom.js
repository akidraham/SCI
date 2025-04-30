// ====================Scroll to top==================== //
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
// ====================Akhir Scroll to top==================== //

// ====================JS untuk Navigasi Bar berubah warna==================== //
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
// ====================Akhir JS untuk Navigasi Bar berubah warna==================== //

// ====================JS untuk ourteknologi==================== //
$(function () {
  const techLogos = $(".tech-logos");

  if (techLogos.length) {
    techLogos.slick({
      slidesToShow: 6,
      slidesToScroll: 1,
      autoplay: true,
      autoplaySpeed: 1500,
      arrows: false,
      dots: false,
      pauseOnHover: false,
      responsive: [
        {
          breakpoint: 768,
          settings: {
            slidesToShow: 4,
          },
        },
        {
          breakpoint: 578,
          settings: {
            slidesToShow: 2,
          },
        },
      ],
    });
  } else {
    console.log("Elemen .tech-logos tidak ditemukan, Slick carousel dinonaktifkan.");
  }
});
// ====================Akhir JS untuk ourteknologi==================== //

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

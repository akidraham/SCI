<img src="./public_html/assets/images/logoscblue.png" align="left" width="192px" height="192px"/>

> Repo untuk Website Sarjana Canggih Indonesia. check the website (here)[https://sarjanacanggihindonesia.com].

[![Under Development](https://img.shields.io/badge/under-development-orange.svg)](https://github.com/SarjanaCanggih/SCI)

<br>
<br>

## Installing

1. Clone this project and name it accordingly:
   `git clone https://github.com/SarjanaCanggih/SCI.git MY-PROJECT-NAME && cd MY-PROJECT-NAME`
2. Do composer install to install dependencies
   `composer install`

## Acknowledgments :thumbsup:

> Libraries used:

- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv).
- [PHPMailer/PHPMailer](https://github.com/PHPMailer/PHPMailer).
- [google-api-php-client](https://github.com/googleapis/google-api-php-client).
- [voku/anti-xss](https://github.com/voku/anti-xss).
- [jenssegers/optimus](https://github.com/jenssegers/optimus).
- [symfony/console](https://github.com/symfony/console).
- [brick/money](https://github.com/brick/money).
- [symfony/http-client](https://github.com/symfony/http-client).
- [filp/whoops](https://github.com/filp/whoops).
- [symfony/validator](https://github.com/symfony/validator).
- [symfony/property-access](https://github.com/symfony/property-access).
- [Carbonphp/carbon](https://github.com/CarbonPHP/carbon).
- [Intervention/image](https://github.com/Intervention/image).
- [nikic/FastRoute](https://github.com/nikic/FastRoute)
- [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php).
- [twbs/bootstrap](https://github.com/twbs/bootstrap).
- [FortAwesome/Font-Awesome](https://github.com/FortAwesome/Font-Awesome).
- [slick-carousel](https://kenwheeler.github.io/slick/).
- [jackocnr/intl-tel-input](https://github.com/jackocnr/intl-tel-input).
- [nzambello/ellipsed](https://github.com/nzambello/ellipsed).
- [flatpickr/flatpickr](https://github.com/flatpickr/flatpickr).

## Notes:

- Optimus hanya digunakan pada

  1. Halaman profil pengguna.
  2. Halaman yang mengakses data menggunakan ID tertentu dalam URL (seperti user_id, post_id, product_id dll).
  3. API yang mengirimkan ID sebagai parameter.
  4. Jika ID tidak pernah dibagikan di URL atau parameter, maka mungkin tidak perlu menggunakan Optimus.

- Rekomendasi Praktik Keamanan
  1. Saat menerima input dari user: Gunakan sanitize_input() untuk membersihkan input sebelum menyimpannya.
  2. Saat menampilkan output di HTML: Gunakan escapeHTML() sebelum mencetak data ke halaman web.

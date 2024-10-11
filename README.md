# Route Planner

## Requirement
- PHP 8.2 ke atas
- Composer

## Cara instalasi
Project ini membutuhkan composer untuk dapat berjalan dengan baik,
jika belum memiliki composer dapat mendownload di https://getcomposer.org sesuai
dengan sistem operasi yang dimiliki.

Apabila sudah memiliki composer, dapat dimulai dengan cara
1. Buka project ini menggunakan code editor
2. Cari terminal
3. Ketik composer install, maka secara otomatis composer akan menginstall semua library yang dibutuhkan untuk berjalan dengan lancar
4. Copy paste file .env.example menjadi .env
5. Jalankan perintah "php artisan key:generate" untuk generate encryption key yang dibutuhkan oleh laravel
6. Jalankan perintah "php artisan migrate", pilih opsi "Yes"

## Menjalankan program
1. Ketik "php artisan serve" pada terminal code editor, tunggu hingga muncul tulisan http://127.0.0.1:8000
2. Klik url tersebut atau buka browser favorit anda dan ketik http://127.0.0.1:8000

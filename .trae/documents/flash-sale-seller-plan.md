# Rencana Implementasi Flash Sale Seller

## Summary

Tambahkan flash sale per produk yang dapat dibuat seller dengan harga promo tetap atau persentase diskon. Satu produk hanya memiliki satu konfigurasi flash sale aktif/terjadwal pada satu waktu, tetapi riwayat promo tetap tersimpan. Promo berlaku end-to-end: seller mengatur, katalog menampilkan harga dan status promo, checkout memakai harga promo serta mengurangi kuota promo secara aman.

## Current State Analysis

- Seller product API berada di `app/Http/Controllers/Api/V1/SellerProductController.php`, memakai Form Request, `ProductPolicy`, ownership scope, dan transaction.
- Product hanya memiliki `price` dan `stock`; belum ada relasi promo atau scope promo aktif.
- Seller edit UI berada di `resources/js/pages/Seller/Products/Edit.vue` dan mengirim `FormData` langsung ke endpoint update product.
- Public product API memakai `ProductResource`; harga publik saat ini selalu `Product::$price`.
- Checkout di `app/Http/Controllers/Api/V1/CheckoutController.php` mengunci row product, mengecek stock, snapshot harga ke `OrderItem`, lalu decrement stock di dalam transaction.
- `OrderItem` sudah menyimpan `price_snapshot`, sehingga harga promo dapat dikunci pada order tanpa menghitung ulang setelah promo berubah.
- Routes API seller dikelompokkan di bawah `web`, `auth`, dan `can:seller`.
- Test seller memakai Pest feature test di `tests/Feature/Seller/SellerProductManagementTest.php`.
- Tidak ada `.ai/rules`; aturan aktif berasal dari `AGENTS.md`.

## Proposed Changes

### 1. Data model dan database

Tambahkan migration `flash_sales` dengan:

- `product_id` foreign key cascade.
- `price` decimal sebagai harga promo hasil akhir.
- `discount_type` enum/string untuk `fixed` atau `percentage`.
- `discount_value` decimal untuk nilai yang seller masukkan.
- `quantity` unsigned integer sebagai kuota flash sale.
- `quantity_sold` unsigned integer default 0.
- `starts_at` dan `ends_at` timezone-aware datetime.
- timestamps.
- index pada `product_id`, `starts_at`, `ends_at`.

Buat `app/Models/FlashSale.php` dan factory mengikuti pola model/factory yang ada. Tambahkan relasi `Product::flashSales()` dan helper/scope untuk promo aktif berdasarkan waktu saat ini. Jangan menambah status kolom; status diturunkan dari periode dan kuota. Validasi satu promo aktif/terjadwal per produk dilakukan pada layer request/controller dan database index tidak dipaksakan sebagai unique agar riwayat tetap bisa disimpan.

Tambahkan field snapshot promo opsional pada `OrderItem` hanya bila diperlukan untuk audit; minimum wajib tetap menyimpan `price_snapshot` yang sudah mencerminkan harga flash sale.

### 2. Seller API

Tambahkan endpoint khusus agar lifecycle promo terpisah dari update produk:

- `POST /api/v1/seller/products/{product:id}/flash-sale` untuk membuat/menjadwalkan promo.
- `PUT /api/v1/seller/products/{product:id}/flash-sale` untuk mengubah promo yang belum selesai.
- `DELETE /api/v1/seller/products/{product:id}/flash-sale` untuk membatalkan promo yang belum mulai atau menonaktifkan promo aktif.

Tambahkan route ke `routes/api.php` dalam group seller. Buat `FlashSaleController` di `app/Http/Controllers/Api/V1/` dengan authorization melalui `ProductPolicy`/ownership parent product. Query promo selalu melalui product seller yang sedang login atau authorize product sebelum operasi.

Buat `StoreFlashSaleRequest` dan `UpdateFlashSaleRequest`:

- `discount_type`: `fixed` atau `percentage`.
- `discount_value`: numeric non-negatif; percentage maksimal 100.
- `quantity`: integer positif dan tidak melebihi stock produk yang tersedia untuk dialokasikan.
- `starts_at` dan `ends_at`: tanggal valid, `ends_at` setelah `starts_at`.
- Harga hasil akhir harus lebih rendah dari harga normal dan tidak boleh nol bila aturan bisnis produk menuntut harga positif.
- Tolak overlap dengan promo lain pada produk.
- Tolak perubahan data inti ketika promo sudah selesai; delete/cancel tidak menghapus riwayat.

Response memakai `FlashSaleResource`, dan `ProductResource` memuat data flash sale saat relasi sudah di-load serta field harga efektif/status untuk consumer.

### 3. Seller UI

Ubah `resources/js/pages/Seller/Products/Edit.vue` untuk menampilkan panel flash sale pada halaman edit produk:

- Toggle/aksi aktifkan flash sale.
- Pilihan tipe diskon fixed atau percentage.
- Input nilai diskon, kuota, waktu mulai, waktu selesai.
- Ringkasan harga normal dan harga promo terhitung.
- Status promo aktif, terjadwal, habis, atau selesai.
- Tombol simpan, ubah, dan batalkan sesuai lifecycle.
- Tampilkan error validasi per field.

Pertahankan pola fetch/FormData yang sudah dipakai halaman tersebut bila endpoint khusus tidak membutuhkan upload. Gunakan route Wayfinder bila route function tersedia; jangan menambah dependency. Pastikan komponen tetap single-root dan UI memakai komponen/input Tailwind yang sudah ada.

### 4. Katalog publik

Ubah query/controller/resource product publik agar eager-load promo yang aktif tanpa N+1. Tambahkan field response, minimal:

- harga normal.
- harga efektif.
- apakah flash sale aktif.
- persentase/nilai diskon.
- waktu mulai/selesai.
- sisa kuota promo.

Tampilkan harga coret, harga promo, badge flash sale, kuota tersisa, dan countdown pada halaman katalog/detail yang sudah menampilkan data product. Jangan menghitung status promo di Vue dari data mentah bila API dapat mengirim status efektif.

### 5. Checkout dan concurrency

Ubah `CheckoutController` di dalam transaction dan setelah `lockForUpdate()`:

- Pilih flash sale aktif hanya bila waktu sekarang berada dalam periode dan `quantity - quantity_sold` masih cukup.
- Gunakan harga promo sebagai `line_total` dan `price_snapshot`.
- Tambah `quantity_sold` atomically pada row flash sale.
- Tetap decrement `products.stock` seperti flow existing; kuota flash sale menjadi batas promo, bukan stock tambahan.
- Abort `409` bila kuota promo tidak cukup, tanpa membuat order.

Pastikan produk aktif tanpa promo tetap memakai harga normal. Harga snapshot order tidak berubah bila promo berakhir setelah order dibuat.

### 6. Tests

Buat/ubah Pest feature tests untuk:

- seller dapat membuat promo fixed milik produknya.
- seller dapat membuat promo percentage dan harga hasilnya benar.
- seller tidak dapat mengubah/membuat promo untuk produk seller lain.
- validasi menolak harga promo tidak lebih rendah, percentage di atas 100, waktu invalid, kuota invalid, dan periode overlap.
- katalog hanya menandai promo saat periode aktif dan tidak menandai promo selesai/kuota habis.
- checkout memakai harga promo dan snapshot `OrderItem`.
- checkout menolak kuota promo tidak cukup.
- concurrent-safe flow tetap menggunakan row lock dan tidak menjual melebihi kuota.

Jalankan test file terkait, `vendor/bin/pint --dirty --format agent` untuk PHP, lalu `npm run types:check` dan build/check frontend sesuai perubahan Vue.

## Assumptions & Decisions

- Satu produk dapat memiliki banyak record riwayat, tetapi tidak boleh punya lebih dari satu promo yang aktif/terjadwal overlap.
- Seller memilih `fixed` atau `percentage`; backend menyimpan tipe dan nilai input serta harga hasil akhir yang dipakai transaksi.
- Kuota flash sale adalah bagian dari stock produk, bukan tambahan stock.
- Promo berlaku hanya pada product berstatus `active`.
- Waktu request disimpan sebagai UTC melalui Laravel date handling; UI mengirim datetime lokal yang dikonversi konsisten di backend.
- Tidak ada approval admin pada scope ini; seller dapat langsung menjadwalkan promo.
- Promo yang sudah masuk order memakai harga snapshot dan tidak direprice.
- Cart persistence tidak ada dalam flow yang dibaca; checkout menentukan promo saat transaction dimulai.
- Fitur campaign global, homepage khusus, notifikasi, scheduler, dan refund kuota berada di luar scope kecuali ditemukan requirement tambahan.

## Verification Steps

1. Jalankan migration dan test feature flash sale.
2. Verifikasi seller ownership, validasi periode/harga/kuota, dan response resource.
3. Verifikasi katalog pada tiga kondisi: scheduled, active, expired/sold out.
4. Verifikasi checkout normal dan flash sale, termasuk `price_snapshot`, `subtotal`, decrement product stock, dan increment promo sold.
5. Jalankan `vendor/bin/pint --dirty --format agent`.
6. Jalankan `npm run types:check` dan build frontend.
7. Jalankan `php artisan test --compact` untuk suite penuh setelah test terarah lulus.

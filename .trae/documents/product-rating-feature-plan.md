# Rencana Fitur Rating Produk

## Ringkasan
Tambahkan fitur rating produk yang hanya dapat digunakan buyer setelah produk pada order seller mencapai status `delivered`. Saat tracking menunjukkan tahap delivered, tampilkan container baru berisi link ke halaman rating produk. Rating tersimpan berdasarkan `order_item`, sehingga validasi pembelian dan status pengiriman tetap aman pada order multi-seller.

## Analisis Kondisi Saat Ini
- Tracking disimpan per seller pada `order_seller_trackings`, dengan `delivered_at` sebagai timestamp tahap selesai.
- `TrackingController` mengirim data tracking ke `resources/js/pages/Tracking/Index.vue`; halaman saat ini hanya memakai `seller_trackings[0]`.
- `OrderItem` belum memiliki relasi `product()` dan belum ada model/tabel review.
- `Product` belum memiliki relasi review atau aggregate rating.
- Product detail memakai `ProductResource` dan `resources/js/pages/Product/Show.vue`.
- API authenticated memakai group `web`, `auth`, dan resource JSON.
- Test memakai Pest dan factory.

## Perubahan yang Diusulkan

### 1. Database dan model
Buat tabel `product_reviews` melalui migration dengan kolom:
- `product_id`
- `user_id`
- `order_item_id`
- `rating` unsigned tiny integer
- `review` nullable text
- timestamps

Tambahkan unique constraint `user_id + order_item_id` agar satu order item hanya dapat diberi rating satu kali.

Buat model `ProductReview` dengan fillable, casts, factory, dan relasi:
- `product()`
- `user()`
- `orderItem()`

Tambahkan relasi pada:
- `Product`: `reviews()`
- `OrderItem`: `product()` dan `review()`
- `User`: `productReviews()` bila mengikuti pola relasi user yang ada.

### 2. Authorization dan service/controller
Buat endpoint authenticated berbasis order item:
- `POST /api/v1/order-items/{orderItem}/review`

Tambahkan controller/API action yang:
- Memastikan user login.
- Memastikan `orderItem` milik order user aktif.
- Memastikan product masih tersedia.
- Memastikan tracking seller untuk `orderItem.seller_id` memiliki `delivered_at`.
- Menolak duplicate review untuk order item yang sama.
- Memvalidasi rating 1 sampai 5 dan review opsional.
- Mengembalikan `ProductReviewResource`.

Tambahkan endpoint/page untuk form rating bila diperlukan:
- Halaman `GET /rating/order-items/{orderItem}` memakai marketplace layout.
- Submit form memakai endpoint API yang sama.

### 3. Data product dan review
Tambahkan aggregate rating ke `ProductResource`:
- `average_rating`
- `ratings_count`

Gunakan `withAvg`/`withCount` atau eager loading yang setara pada query product yang dipakai product detail, supaya tidak menimbulkan N+1.

Tambahkan endpoint paginated review product bila product detail perlu menampilkan daftar review:
- `GET /api/v1/products/{slug}/reviews`

Product detail menampilkan rating rata-rata dan jumlah rating. Daftar review memakai pagination, bukan memuat seluruh review sekaligus.

### 4. Tracking frontend
Ubah `TrackingController` agar mengirim order items yang relevan bersama seller tracking, termasuk:
- item ID
- product ID
- product name
- product slug
- seller ID
- seller tracking status/timestamp
- existing review status untuk user login bila tersedia.

Ubah `resources/js/pages/Tracking/Index.vue` agar memproses setiap seller tracking/order item, bukan hanya index pertama. Setelah `delivered_at` terisi, tampilkan container baru:
- Judul: `Rate your product`
- Nama produk
- Tombol/link `Rate product`
- Link menuju halaman rating dengan `order_item` sebagai parameter.

Jika belum delivered, container rating tidak tampil.

### 5. Halaman rating
Buat `resources/js/pages/Rating/Show.vue` dengan marketplace layout. Form berisi:
- pilihan rating 1–5
- textarea review opsional
- tombol submit
- pesan validasi/error/sukses

Halaman hanya dapat dibuka oleh buyer pemilik order item dan hanya jika seller tracking sudah delivered. Setelah berhasil, tampilkan rating yang tersimpan dan cegah submit ulang.

### 6. Routes dan Wayfinder
Tambahkan route web untuk halaman rating dan route API untuk submit review pada file route yang sesuai. Jalankan `wayfinder:generate` atau build yang menghasilkan type route agar frontend memakai route helper, bukan URL hardcode untuk endpoint baru.

### 7. Test
Buat `tests/Feature/Product/ProductRatingTest.php` dengan coverage minimum:
- guest tidak dapat submit rating.
- user tidak dapat rating order item milik user lain.
- user tidak dapat rating sebelum seller tracking delivered.
- user dapat rating setelah `delivered_at` tersedia.
- rating 0 dan 6 ditolak.
- duplicate rating untuk order item ditolak.
- aggregate rating muncul pada product response.
- rating untuk seller/item lain pada multi-seller order tetap mengikuti tracking seller masing-masing.

Gunakan factory existing dan factory `ProductReviewFactory`. Jalankan test terkait, lalu Pint untuk PHP dan build frontend.

## Asumsi dan Keputusan
- Syarat delivered memakai `delivered_at IS NOT NULL`, bukan status order global.
- Satu order item menghasilkan maksimal satu rating per user.
- Rating dilakukan melalui halaman khusus dari tracking page.
- Rating membutuhkan authentication; guest hanya dapat melihat tracking.
- Product review tidak dihapus saat product dihapus; `product_id` sebaiknya `nullOnDelete` atau kebijakan migration mengikuti kebutuhan data historis. Endpoint menolak rating jika product sudah tidak tersedia.
- Implementasi harus tetap mendukung order multi-seller.

## Verifikasi
1. Jalankan migration dan cek constraint tabel review.
2. Jalankan `php artisan test --compact tests/Feature/Product/ProductRatingTest.php`.
3. Jalankan `vendor/bin/pint --dirty --format agent`.
4. Jalankan `npm run build`.
5. Uji manual: order belum delivered tidak menampilkan link rating; setelah seller set delivered, link muncul; user dapat mengirim rating; duplicate submit ditolak; rating tampil pada product detail.

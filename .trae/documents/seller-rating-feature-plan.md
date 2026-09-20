# Rencana Fitur Rating Toko / Seller

## Ringkasan
Tambahkan rating rata-rata toko dan daftar review produk pada halaman publik seller. Rating toko dihitung dari seluruh `ProductReview` milik produk aktif seller, tanpa membuat tabel rating seller baru.

## Analisis Kondisi Saat Ini
- `ProductReview` terhubung ke `Product`, `User`, dan `OrderItem`; seller dapat ditentukan melalui `product.seller_id`.
- `ProductReviewResource` sudah mengirim rating, komentar, media, tanggal, dan user, tetapi belum mengirim informasi produk.
- `SellerShowController` hanya memuat seller dan produk aktif beserta gambar/kategori.
- `SellerResource` belum mengirim `average_rating` dan `ratings_count`.
- `Store/Show.vue` sudah memiliki tab Produk dan Rating, tetapi tab Rating masih placeholder `Belum ada rating untuk toko ini.`.
- Header halaman toko masih menampilkan `—` untuk statistik Rating.

## Perubahan yang Diusulkan

### 1. SellerShowController
File: `app/Http/Controllers/Web/SellerShowController.php`

- Query produk aktif tetap memuat `images` dan `category`.
- Tambahkan `withAvg('reviews', 'rating')` dan `withCount('reviews')` agar kartu produk di toko memiliki statistik rating.
- Query review toko dari `ProductReview` dengan `whereHas('product', ...)` berdasarkan seller ID.
- Muat relasi `user` dan `product` untuk review.
- Urutkan review terbaru.
- Kirim prop `reviews` ke halaman `Store/Show` menggunakan resource.
- Hitung aggregate rating toko dari review produk seller dan expose melalui atribut resource seller.
- Jangan memuat review terpisah untuk setiap produk agar payload dan query tidak berlipat.

### 2. SellerResource
File: `app/Http/Resources/Api/V1/SellerResource.php`

Tambahkan:
- `average_rating`, menggunakan aggregate rating toko.
- `ratings_count`, menggunakan jumlah review toko.

Jika seller belum memiliki review, kirim `average_rating` sebagai `null` dan `ratings_count` sebagai `0`.

### 3. ProductReviewResource
File: `app/Http/Resources/Api/V1/ProductReviewResource.php`

Tambahkan data produk saat relasi `product` dimuat:
- `id`
- `name`
- `slug`

Informasi ini dipakai halaman toko untuk menunjukkan review berasal dari produk mana. Pertahankan field user, media, rating, komentar, dan tanggal yang sudah ada.

### 4. Store/Show.vue
File: `resources/js/pages/Store/Show.vue`

- Tambahkan tipe TypeScript untuk seller rating dan review toko.
- Tambahkan prop `reviews`.
- Ganti statistik header Rating `—` dengan format `4.7/5` dan jumlah review.
- Pertahankan tab Produk.
- Ubah tab Rating dari placeholder menjadi daftar review.
- Setiap review menampilkan foto/inisial user, nama, bintang, tanggal, nama produk, komentar, serta media bila tersedia.
- Tampilkan empty state hanya ketika seller belum memiliki review.
- Gunakan layout dan token warna yang sudah dipakai halaman toko.
- Jangan menambahkan route baru; data dimuat bersama halaman seller melalui route `sellers/{slug}`.

### 5. Test
Tambahkan atau perbarui feature test untuk memastikan:
- Rating toko dihitung dari review produk seller yang benar.
- Review seller lain tidak ikut masuk.
- Halaman seller mengirim review dan statistik rating.
- Seller tanpa review mengirim jumlah `0` dan kondisi empty state.

## Keputusan dan Asumsi
- Sumber rating seller adalah semua review produk seller, bukan rating terpisah.
- Review diurutkan terbaru.
- Semua review toko dimuat pada satu request; pagination async tidak ditambahkan dalam scope ini.
- Rating produk pada kartu toko tetap dihitung dengan `withAvg` dan `withCount`.
- Review seller ditampilkan hanya pada halaman publik toko.

## Verifikasi
1. Jalankan test feature terkait.
2. Jalankan `vendor/bin/pint --dirty --format agent`.
3. Jalankan `npm run build`.
4. Buka halaman seller dengan review dan tanpa review.
5. Pastikan header menampilkan rating rata-rata dan jumlah review.
6. Pastikan review produk yang tampil hanya milik seller tersebut.

# Plan: Fitur Diskon dan Kupon Marketplace

## Summary

Tambahkan fitur kupon bergaya Shopee untuk admin dan seller. Admin dapat membuat kupon global marketplace, seller dapat membuat kupon yang hanya berlaku pada produk tokonya. Checkout menerima satu kode kupon, memvalidasi ulang di backend, menghitung diskon dari harga efektif produk (termasuk flash sale bila kupon mengizinkan), serta menyimpan snapshot dan pemakaian kupon pada order.

## Current State Analysis

- Checkout utama berada di `app/Http/Controllers/Api/V1/CheckoutController.php`; backend menghitung ulang harga produk dan flash sale di dalam transaksi dengan row lock.
- `orders` hanya menyimpan subtotal, shipping fee, total, status, dan informasi customer; belum ada kolom kupon atau diskon.
- `order_items` menyimpan harga snapshot dan subtotal; belum ada alokasi diskon per item.
- Cart memakai Pinia di `resources/js/stores/cart.ts`, sedangkan state checkout berada di `resources/js/stores/checkout.ts`.
- Checkout UI berada di `resources/js/pages/Checkout/Show.vue` dan belum memiliki input/preview kupon.
- Admin dan seller memakai halaman Inertia tipis dengan API CRUD terpisah; pola ini sudah dipakai flash sale.
- Flash sale aktif dipilih per produk saat checkout. Coupon harus menjadi lapisan setelah harga efektif flash sale, dengan eligibility per coupon untuk menentukan apakah item flash sale boleh didiskon.

## Proposed Changes

### 1. Data model dan database

Buat migration dan model `Coupon` dengan field minimal:

- `code` unik dan uppercase.
- `name`, `description` nullable.
- `owner_type`/scope yang membedakan global admin dan seller, serta nullable `seller_id` untuk kupon seller.
- `discount_type`: `percentage`, `fixed`, atau `free_shipping`.
- `discount_value`, `maximum_discount` nullable.
- `minimum_spend` default 0.
- `usage_limit`, `usage_count`, `per_user_limit` nullable.
- `starts_at`, `ends_at`.
- `allow_flash_sale` boolean.
- `status` aktif/nonaktif.
- timestamps.

Buat tabel `coupon_product` dan `coupon_category` untuk membatasi kupon ke produk/kategori tertentu. Kupon tanpa assignment berlaku ke scope owner-nya: global untuk semua produk atau seller untuk produk seller tersebut.

Buat tabel `coupon_usages` dengan `coupon_id`, `order_id`, `user_id` nullable, `discount_amount`, timestamps, serta unique constraint yang mencegah pemakaian user melebihi batas melalui pemeriksaan terkunci di transaksi.

Tambahkan kolom order:

- `discount_amount` default 0.
- `coupon_code` nullable.
- `coupon_snapshot` nullable JSON untuk menyimpan code, name, type, value, max discount, dan scope saat order dibuat.

Tambahkan `discount_amount` pada `order_items` agar diskon dapat dialokasikan proporsional ke item/seller dan histori seller tetap akurat.

Tambahkan relasi, casts, fillable, dan helper scope pada `Coupon`, `Order`, dan `OrderItem` mengikuti pola model existing.

### 2. Validasi dan service kalkulasi

Buat Form Request atau validator khusus untuk payload checkout yang menerima `coupon_code` opsional. Buat service kecil untuk:

- normalize code uppercase.
- validasi periode, status, kuota global, kuota per user, minimum spend, scope seller, produk/kategori, dan eligibility flash sale.
- menghitung diskon fixed, percentage dengan maximum discount, atau free shipping.
- membatasi diskon agar tidak melebihi subtotal eligible.
- mengalokasikan discount amount ke item eligible secara proporsional.

Gunakan harga efektif hasil flash sale sebagai basis subtotal. Kupon seller hanya menghitung item milik seller tersebut; kupon global dapat menghitung semua item eligible. Satu order hanya memakai satu kode kupon.

### 3. Checkout backend

Ubah `BuyerInformationRequest` agar menerima `coupon_code` opsional.

Di `CheckoutController`:

- lock coupon yang dipakai di dalam transaksi bersama product/flash sale lock.
- hitung subtotal efektif terlebih dahulu.
- validasi dan hitung kupon terhadap item yang sudah disiapkan.
- hitung shipping setelah diskon item; free shipping mengurangi shipping fee menjadi nol sesuai aturan kupon.
- simpan subtotal, discount amount, shipping fee, total, coupon code, dan snapshot ke order.
- simpan alokasi discount pada order items.
- increment usage counter dan buat `coupon_usages` dalam transaksi.
- pertahankan batas total tidak negatif dan semua validasi trust-boundary di backend.

Tambahkan endpoint preview/validate kupon untuk UX checkout, misalnya `POST /api/v1/coupons/validate`, tetapi validasi wajib diulang saat `POST /api/v1/orders`. Endpoint preview hanya membaca data dan mengembalikan breakdown tanpa mengonsumsi kuota.

Perbarui `OrderResource` dan response checkout agar frontend menerima subtotal, discount, shipping fee, total, dan coupon snapshot.

### 4. Admin dan seller CRUD

Tambahkan page routes:

- Admin: `/admin/coupons`, `/admin/coupons/create`, `/admin/coupons/{coupon}/edit`.
- Seller: `/seller/coupons`, `/seller/coupons/create`, `/seller/coupons/{coupon}/edit`.

Tambahkan controller page sesuai pola flash sale, menu sidebar, dan API CRUD:

- Admin dapat CRUD semua kupon global dan seller.
- Seller hanya dapat CRUD kupon milik seller sendiri.
- Seller hanya dapat memilih produk/kategori dari tokonya.
- Admin dapat menentukan `allow_flash_sale`, scope produk/kategori, kuota, periode, diskon, dan gratis ongkir.
- Delete/update menolak perubahan yang merusak order usage; kupon yang sudah dipakai dinonaktifkan atau dihapus secara aman sesuai konvensi existing.

Buat halaman list, create, dan edit untuk admin/seller. Tabel menampilkan code, scope, tipe diskon, periode, usage, status, dan aksi edit/delete. Tambahkan pagination server-side.

### 5. Checkout frontend

Perbarui `resources/js/stores/checkout.ts` dengan:

- `couponCode`.
- `couponPreview` berisi discount, shipping discount, eligible subtotal, dan total.
- `couponError` serta status loading.
- reset coupon state saat checkout reset.

Perbarui `resources/js/pages/Checkout/Show.vue`:

- tambah input kode kupon dan tombol Apply/Remove di area review atau ringkasan order.
- panggil endpoint preview.
- tampilkan error validasi, detail diskon, dan total akhir.
- kirim `coupon_code` saat submit order.
- jangan mempercayai total frontend; gunakan response backend setelah order dibuat.
- tampilkan harga flash sale dan status eligibility dengan jelas.

### 6. Order display dan seller/admin reporting

Perbarui resource dan halaman order admin/seller/customer agar menampilkan coupon code dan discount amount dari snapshot order. Seller hanya melihat alokasi diskon untuk item tokonya; admin melihat total order dan seluruh kupon.

### 7. Tests

Tambahkan/update Pest feature tests untuk:

- admin dan seller coupon CRUD dengan authorization/isolation.
- valid percentage, fixed, free shipping, minimum spend, max discount.
- periode belum mulai dan expired.
- usage global dan per-user.
- code invalid/inactive.
- product/category scope.
- seller coupon pada multi-seller cart.
- flash sale eligible dan tidak eligible.
- diskon dialokasi ke order item.
- coupon hanya dikonsumsi sekali saat order dibuat dan race-safe.
- total, shipping, snapshot, dan coupon usage tersimpan benar.
- checkout tanpa coupon tetap regresi pass.

## Assumptions & Decisions

- Scope dipilih: admin global dan seller-specific.
- Dukungan diskon: percentage, fixed amount, free shipping, minimum spend, maximum discount, periode, global usage limit, dan per-user limit.
- Satu checkout memakai satu kupon.
- Coupon seller hanya berlaku pada item milik seller pembuat kupon; coupon global berlaku lintas seller.
- Admin dapat membuat kupon untuk semua scope; seller tidak boleh mengakses data seller lain.
- Flash sale eligibility dikontrol per coupon melalui `allow_flash_sale`; jika diizinkan, basis diskon adalah harga flash sale.
- Kupon dianggap terpakai saat order `pending_payment` dibuat karena stok juga langsung di-reserve. Pengembalian usage saat order expired/payment gagal perlu mengikuti mekanisme order expiry existing dan dicatat sebagai langkah wajib sebelum production.
- Order menyimpan snapshot kupon dan discount amount agar histori tidak berubah saat kupon diedit.
- Tidak menambah dependency baru.

## Verification

1. Jalankan migration pada database development.
2. Jalankan test terarah coupon dan checkout.
3. Jalankan `vendor/bin/pint --dirty --format agent`.
4. Jalankan `php artisan route:list --path=coupons` dan cek middleware admin/seller.
5. Jalankan `npm run build` dan type check frontend.
6. Uji manual:
   - admin membuat kupon global;
   - seller membuat kupon tokonya;
   - checkout single-seller dan multi-seller;
   - coupon invalid/expired/quota habis;
   - kombinasi flash sale eligible/non-eligible;
   - free shipping dan max discount;
   - pagination list admin/seller.

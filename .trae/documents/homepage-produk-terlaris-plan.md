# Rencana: Section "Produk Terlaris" Data Asli + Slider Swiper

## Ringkasan

Section "Produk Terlaris" di homepage saat ini adalah tampilan statis: `HomeController` mengirim 8 produk terbaru (`$latest->take(8)`) sebagai `bestSellerProducts`, bukan hasil peringkat penjualan. Section juga hanya memakai `overflow-x-auto` tanpa kontrol geser.

Perubahan yang dilakukan:

1. **Backend**: ranking produk terlaris dihitung dari data transaksi asli (`order_items.quantity`) pada order berstatus `paid`, `processing`, `shipped`, `completed` — konsisten dengan perhitungan revenue di `DashboardController`.
2. **Payload**: `ProductResource` menambah field `sold_count` (hanya muncul bila dimuat).
3. **Frontend**: `BestSellerSection.vue` memakai **Swiper JS** (bisa di-drag dengan mouse/touch) dengan **arrow prev/next**, **tanpa dot/pagination**, dan menampilkan jumlah terjual.

Keputusan user (sudah dikonfirmasi):
- Status order yang dihitung: **paid, processing, shipped, completed**.
- Bila belum ada penjualan: **empty state** (tidak mengarang ranking).

## Analisis Kondisi Saat Ini

- [HomeController.php](file:///d:/dev/barokah/app/Http/Controllers/Web/HomeController.php#L39-L59) — `bestSellerProducts` = `ProductResource::collection($latest->take(8)->values())`, murni produk aktif terbaru. Tidak menyentuh `order_items` sama sekali.
- [BestSellerSection.vue](file:///d:/dev/barokah/resources/js/components/marketplace/BestSellerSection.vue#L50-L73) — wrapper `flex overflow-x-auto`, kartu `<article>` rank + gambar + nama. Ada teks placeholder "Ranking preview only. Best-seller ranking backend is TBC (spec §24 item 22)". Tidak ada arrow, tidak ada library carousel.
- [ProductResource.php](file:///d:/dev/barokah/app/Http/Resources/Api/V1/ProductResource.php#L19-L45) — belum mengekspos data penjualan. Pola `sold_count` tidak ada; yang ada `average_rating`/`ratings_count` dari `withAvg`/`withCount`.
- [Product.php](file:///d:/dev/barokah/app/Models/Product.php#L62-L101) — scope hanya `active()`; relasi `orderItems()` → `OrderItem` sudah tersedia (via `hasMany`).
- [OrderItem.php](file:///d:/dev/barokah/app/Models/OrderItem.php#L59-L78) — punya relasi `order()`, `product()`, `seller()`; kolom `quantity` adalah sumber data penjualan riil.
- [OrderStatus.php](file:///d:/dev/barokah/app/Enums/OrderStatus.php#L16-L25) — `pending_payment, paid, processing, shipped, completed, cancelled, expired`.
- [DashboardController.php](file:///d:/dev/barokah/app/Http/Controllers/Admin/DashboardController.php#L25-L32) — sudah memakai daftar status "terbayar" (`Paid, Processing, Shipped, Completed`) untuk revenue; jadi pola ini bukan hal baru di repo.
- [marketplace.ts](file:///d:/dev/barokah/resources/js/types/marketplace.ts#L9-L98) — `ProductCardData.soldCount` sudah ada, tetapi `toProductCardData()` belum pernah mengisinya, dan `HomeProductItem` belum punya `sold_count`.
- [package.json](file:///d:/dev/barokah/package.json#L13-L30) — **`swiper` belum terpasang**. `three` catatan: Node v22.17.1 / npm 10.9.2 tersedia; Vue 3.5, Inertia 3, Tailwind v4.
- Pola carousel existing di [FlashSaleSection.vue](file:///d:/dev/barokah/resources/js/components/marketplace/FlashSaleSection.vue#L67-L112) dipakai sebagai acuan gaya arrow (bulat, border soft, `‹`/`›`, hover brand).
- Tidak ada `ssr.ts`/SSR entry (hanya `resources/js/app.ts`), jadi Swiper client-only aman.
- [OrderItemFactory.php](file:///d:/dev/barokah/database/factories/OrderItemFactory.php#L21-L37) & [OrderFactory.php](file:///d:/dev/barokah/database/factories/OrderFactory.php#L21-L46) siap dipakai untuk test; `OrderItemFactory` default `product_id => null` sehingga harus di-set eksplisit.

## Perubahan yang Diusulkan

### 1. Instalasi Swiper

- Jalankan `npm install swiper` (menambah `swiper` ke `dependencies`; versi terbaru, saat ini 14.x — API `swiper/vue`, `swiper/modules`, `swiper/css` tidak berubah dari v12).
- Ini satu-satunya dependensi baru dan sudah diminta eksplisit oleh user.

### 2. `app/Http/Controllers/Web/HomeController.php` — ranking dari penjualan asli

Ganti baris 59 (`bestSellerProducts`) dengan query berbasis penjualan. Tambahkan import `App\Enums\OrderStatus` dan `Illuminate\Database\Eloquent\Builder`.

```php
$soldStatuses = [
    OrderStatus::Paid,
    OrderStatus::Processing,
    OrderStatus::Shipped,
    OrderStatus::Completed,
];

$bestSellers = Product::query()
    ->active()
    ->with(['seller', 'category', 'images'])
    ->whereHas('orderItems.order', fn (Builder $order) => $order->whereIn('status', $soldStatuses))
    ->withSum(
        ['orderItems as sold_count' => fn (Builder $items) => $items
            ->whereHas('order', fn (Builder $order) => $order->whereIn('status', $soldStatuses))],
        'quantity'
    )
    ->orderByDesc('sold_count')
    ->orderByDesc('id')
    ->limit(8)
    ->get();
```

Lalu pada `Inertia::render`:

```php
'bestSellerProducts' => ProductResource::collection($bestSellers),
```

Alasan/klaim teknis:
- `withSum` menghasilkan satu subquery agregat (`sold_count`) → tidak ada N+1; `whereHas` memastikan hanya produk yang benar-benar punya penjualan yang masuk (produk tanpa penjualan tidak muncul → empty state di UI).
- `orderByDesc('id')` hanya sebagai tie-breaker deterministik bila jumlah terjual sama.
- Sejalan dengan pola `DashboardController` (daftar status terbayar) tanpa membuat abstraksi baru.

### 3. `app/Http/Resources/Api/V1/ProductResource.php` — ekspos `sold_count`

Tambahkan satu baris setelah `stock` (baris 29):

```php
'sold_count' => $this->when($this->sold_count !== null, fn (): int => (int) $this->sold_count),
```

- Memakai `when()` agar key **tidak muncul** saat query tidak memuat agregat (listing API, detail produk, flash sale tetap tidak berubah).
- Pola `$this->...` untuk atribut agregat sudah dipakai di file yang sama (`$this->reviews_avg_rating`, `$this->reviews_count`).

### 4. `resources/js/types/marketplace.ts`

- Pada `HomeProductItem`, tambah `sold_count?: number | null;` (setelah `stock`).
- Pada `toProductCardData()`, tambah `soldCount: product.sold_count ?? undefined,`.
- Tidak mengubah `ProductCardData`.

Dampak samping: `RecommendationSection` (memakai `toProductCardData` + `ProductCard`) akan otomatis ikut menampilkan "N sold" bila `sold_count` ada. Query `latestProducts` tidak memuat agregat tersebut, jadi `soldCount` tetap `undefined` dan perilaku kartu rekomendasi tidak berubah.

### 5. `resources/js/components/marketplace/BestSellerSection.vue` — Swiper, drag, arrow, tanpa dot

Ganti seluruh isi file dengan pendekatan berikut (mempertahankan struktur section, aria-label, link "Lihat Semua", skeleton, empty state):

- Import:
  - `import { Swiper, SwiperSlide } from 'swiper/vue';`
  - `import { Navigation } from 'swiper/modules';`
  - `import 'swiper/css';` dan `import 'swiper/css/navigation';`
  - `import { ref } from 'vue';` (untuk `prevEl`/`nextEl` template ref)
- Config Swiper (script setup):
  - `modules: [Navigation]`
  - `slidesPerView: 'auto'`, `spaceBetween: 8`
  - `watchSlidesProgress: true`, `grabCursor: true`, `slidesPerGroup: 1`
  - `navigation: { prevEl, nextEl }` memakai `ref<HTMLButtonElement | null>`
  - **Tanpa** `Pagination` → tidak ada dot.
  - `breakpoints` mengatur `slidesPerView` agar konsisten dengan lebar kartu `w-[220px]` yang sekarang (mobile ~1.3 kartu, md ke atas ~2.5–3 kartu), memakai `spaceBetween: 8`.
- Template:
  - Header section dipertahankan (judul "Produk Terlaris" + `Lihat Semua >`).
  - Hapus paragraf placeholder "Ranking preview only… TBC (spec §24 item 22)" — backend ranking kini nyata.
  - `loading` → skeleton 6 kotak (dipertahankan, tanpa Swiper).
  - `cards.length === 0` → teks empty state: "Belum ada data penjualan. Produk terlaris akan muncul setelah ada transaksi."
  - Selain itu → struktur Swiper:
    - Wrapper `relative` berisi 2 tombol arrow (kiri/kanan) bergaya sama dengan `FlashSaleSection` (bulat `h-9 w-9`, border soft, `bg-white`, `shadow-md`, hover brand, `-translate-x-1/2` / `translate-x-1/2`, `z-10`). Tombol ke-2 (`nextEl`) diberi atribut `data-best-seller-next`; tombol pertama `data-best-seller-prev` (agar ref tetap valid & unik), dengan `aria-label="Produk sebelumnya"` / `"Produk berikutnya"`.
    - `<Swiper :modules="modules" :navigation="navigation" ... class="best-seller-swiper">`
    - `<SwiperSlide v-for="(card, index) in cards" :key="card.id" class="!w-[220px]">` — lebar slide dipatok eksplisit (`!w-[220px]` / style `width: 220px`) karena `slidesPerView: 'auto'` butuh lebar slide dari CSS; dengan `shrink-0` pada konten kartu.
    - Konten kartu dipertahankan: nomor ranking besar (`index + 1`, warna brand), thumbnail `h-16 w-16`, nama `line-clamp-2`. Tambahkan baris `{{ card.soldCount }} terjual` (opsional, hanya bila `soldCount !== undefined`) dan bungkus kartu dengan `<Link :href="`/products/${card.slug}`">` agar konsisten dengan kartu lain (perilaku klik sebelumnya tidak ada — ini penyelarasan kecil, bukan fitur baru yang diminta; jika ingin minimal, cukup jadikan `<article>` biasa tanpa Link).
  - Tambah blok `<style scoped>` bila perlu untuk memastikan tombol Swiper bawaan tidak muncul (`--swiper-navigation-size`/`.swiper-button-prev` default di-override) — kita memakai tombol kustom, sehingga cukup tidak mengimpor markup bawaan Swiper (tidak ada `<div class="swiper-button-prev">`), dan CSS `swiper/css/navigation` hanya diperlukan bila memakai kustomisasi `--swiper-navigation-*`.
  - Aksesibilitas: `aria-label` section dipertahankan; Swiper menerima navigasi keyboard secara default.

Catatan implementasi: karena `navigation` menerima elemen DOM, gunakan `const navigation = { prevEl: ref<HTMLButtonElement | null>(null), nextEl: ref<HTMLButtonElement | null>(null) }` dan pasang `ref="navigation.prevEl"` / `ref="navigation.nextEl"` pada tombol. Swiper mengamati perubahan ref dengan benar pada mount di Vue 3.

### 6. `resources/js/types` — tidak ada file baru

Tidak membuat komponen/utility baru; semua perubahan ada di file yang sudah ada.

## Asumsi & Keputusan

| Item | Keputusan | Alasan |
| --- | --- | --- |
| Status order dihitung | `paid, processing, shipped, completed` | Jawaban user; konsisten dengan revenue `DashboardController`. |
| Tanpa penjualan | Empty state, tanpa fallback | Jawaban user; sesuai semangat spec "tidak mengarang data". |
| Sumber angka "terjual" | `SUM(order_items.quantity)` | Satu-satunya data penjualan riil; tidak menambah kolom/tabel baru. |
| `quantity_sold` flash sale | Tidak dipakai | Hanya menghitung kuota promo, bukan total penjualan produk. |
| Library slider | `swiper` (npm) | Permintaan eksplisit user; satu-satunya dependensi baru. |
| Dot/pagination | Tidak dipakai | Permintaan eksplisit user ("tanpa dot"). |
| Drag | Default Swiper (mouse + touch) + `grabCursor` | Permintaan eksplisit "bisa di-drag". |
| Jumlah item | Batas 8 (sama seperti sekarang) | Menghindari perubahan cakupan tak diminta. |
| Field resource | `sold_count` conditional via `when()` | Tidak mengubah payload listing/detail/flash sale. |
| Test baru | Ya, feature test homepage | Aturan repo: setiap perubahan kode diuji. |

## Verifikasi

1. **Backend test** — buat `tests/Feature/HomeBestSellerSectionTest.php` (Pest) berisi minimal:
   - Produk dengan penjualan lebih tinggi muncul lebih dulu di `bestSellerProducts` (urutan berdasar `sold_count`).
   - Order `pending_payment` / `expired` / `cancelled` **tidak** menambah `sold_count`.
   - Produk tanpa order item tidak muncul sama sekali.
   - Produk `draft`/`inactive` tidak muncul meski punya penjualan.
   - Field `bestSellerProducts.0.sold_count` ada dan bernilai jumlah quantity.
   - Jalankan: `php artisan test --compact tests/Feature/HomeBestSellerSectionTest.php`.
2. **Regresi** — `php artisan test --compact tests/Feature/HomeSellerSectionTest.php tests/Feature/Product/ProductCatalogTest.php` memastikan `ProductResource` dan homepage lain tidak berubah.
3. **Format & tipe** — `vendor/bin/pint --dirty --format agent`, `npm run types:check`.
4. **Frontend** — `npm run build` berhasil (Swiper ter-bundle). Verifikasi manual di homepage:
   - Section bisa di-drag dengan mouse dan swipe di layar sentuh.
   - Arrow kiri/kanan menggeser slider dan tampil sesuai gaya FlashSaleSection.
   - Tidak ada dot/pagination.
   - Skeleton tampil saat loading; empty state tampil bila tidak ada penjualan.
5. **Data demo** — `OrderSeeder` sudah menyediakan order `paid` dan `completed`, sehingga setelah `php artisan migrate:fresh --seed` section tidak kosong dan bisa diperiksa visual.

## File yang Disentuh

- `package.json` (+ `package-lock.json`) — tambah `swiper`.
- [HomeController.php](file:///d:/dev/barokah/app/Http/Controllers/Web/HomeController.php) — query ranking penjualan.
- [ProductResource.php](file:///d:/dev/barokah/app/Http/Resources/Api/V1/ProductResource.php) — `sold_count`.
- [marketplace.ts](file:///d:/dev/barokah/resources/js/types/marketplace.ts) — tipe + mapper.
- [BestSellerSection.vue](file:///d:/dev/barokah/resources/js/components/marketplace/BestSellerSection.vue) — Swiper + arrow.
- `tests/Feature/HomeBestSellerSectionTest.php` — test baru.

Tidak ada migration, model, atau kolom database baru.
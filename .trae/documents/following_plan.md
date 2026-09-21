# Fitur Following (Follow Seller + Favorit Produk) Implementation Plan

## Repository Research

### Kondisi saat ini (Before)

**Database / Models:**
- Tidak ada tabel/model `SellerFollow` / `FavoriteProduct` / `Wishlist`. Grep keyword `follow|follower` di seluruh repo hanya menemukan 2 match UI placeholder di `Store/Show.vue` button disabled dan `Welcome.vue`, tidak ada backend logic.
- Relasi yang sudah ada: `User hasOne Seller`, `Seller belongsTo User`, `Seller hasMany Product`, `Product belongsTo Seller`.
- Konvensi model: menggunakan `#[Fillable([...])]`, `#[Hidden([...])]` attributes, factory untuk User/Seller/Product sudah tersedia di `database/factories/`.

**Resources (JSON API):**
- `SellerResource` (line 17–35): mengembalikan `id, store_name, slug, description, profile_photo_url, phone, whatsapp, store_location, state, city, status, average_rating, ratings_count` — **TIDAK ADA** `followers_count` dan `is_followed` (flag untuk user login).
- `ProductResource` (line 17–46): mengembalikan product fields biasa — **TIDAK ADA** `is_favorited` / wishlist flag.

**Routes (`routes/web.php`):**
- Tidak ada route POST/DELETE untuk follow/unfollow seller ataupun toggle wishlist produk.
- Route `profile.show` sudah di grup `middleware(['auth','verified'])`.

**Frontend pages:**
1. `Store/Show.vue` — baris 212–219: button **Follow disabled** dengan title "Segera hadir"; kolom statistik "Pengikut" (baris 247–256) tampilkan hard-coded `—` karena backend belum mengirim count.
2. `Product/Show.vue` — halaman detail produk saat ini hanya punya Add to Cart / Buy buttons. **Belum ada button Favorit / Wishlist**.
3. `Profile/Show.vue` — sidebar tabs saat ini hanya `profile` ('Account Info') dan `password` ('Password'). **Belum ada tab Following / Wishlist**. Dua kontainer existing tabs cukup besar (grid `lg:grid-cols-[280px_1fr]`) sehingga mudah ditambah navigasi tanpa re-layout besar.

**Konvensi project (dari AGENTS.md / project memory):**
- Wayfinder diaktifkan: generate typed functions di `@/routes/` dan `@/actions/`. Route baru akan otomatis bisa di-import via `php artisan wayfinder:generate`.
- UI: Tailwind v4, CSS variables (`var(--brand-primary)` dll.) — **tidak pakai `text-muted-foreground` / shadcn class mentah**.
- Tests: Pest (Pint for style). Semua perubahan PHP harus test coverage minimal untuk happy path + auth guard.
- Inertia v3: Flash / Toast message lewat `Inertia::flash('toast', [...])`.

---

## Scope yang disetujui user
1. **Follow Seller + Follow Product** (dua objek bisa di-follow / favorit).
2. **Halaman seller (Store/Show.vue):** tombol Follow aktif + jumlah pengikut real (bukan placeholder `—`).
3. **Halaman profile user (Profile/Show.vue):** tab "Toko Diikuti" (daftar seller yang difollow) + tab "Produk Favorit" (wishlist).

---

## Files and Modules

### 1. Database Migrations
- `database/migrations/2026_09_21_XXXXXX_create_seller_follows_table.php` — tabel `seller_follows`: `user_id` FK, `seller_id` FK, composite unique `(user_id, seller_id)`, FK on cascade delete.
- `database/migrations/2026_09_21_XXXXXX_create_favorite_products_table.php` — tabel `favorite_products`: `user_id` FK, `product_id` FK, composite unique `(user_id, product_id)`, FK cascade delete.

### 2. Models (baru + update)
- **BARU** `app/Models/SellerFollow.php` — `Fillable([user_id, seller_id])`, `BelongsTo User`, `BelongsTo Seller`.
- **BARU** `app/Models/FavoriteProduct.php` — `Fillable([user_id, product_id])`, `BelongsTo User`, `BelongsTo Product`.
- **UPDATE** `app/Models/User.php` — tambah:
  - `followedSellers(): BelongsToMany<Seller>` (pivot via seller_follows)
  - `favoriteProducts(): BelongsToMany<Product>` (pivot via favorite_products)
  - helper: `followsSeller(Seller $seller): bool`, `hasFavorited(Product $product): bool`
- **UPDATE** `app/Models/Seller.php` — tambah:
  - `followers(): BelongsToMany<User>` (inverse pivot)
  - `followers_count` via withCount + append/attribute casting bila perlu via Resource.
- **UPDATE** `app/Models/Product.php` — tambah:
  - `favoritedByUsers(): BelongsToMany<User>` (inverse pivot).

### 3. Controller / Actions
- **BARU** `app/Http/Controllers/Web/SellerFollowController.php` — `toggle(Seller $seller)`: buat / hapus follow record. Response: back() / redirect ke sellers.show dengan toast flash `Berhasil mengikuti / Berhenti mengikuti`.
- **BARU** `app/Http/Controllers/Web/FavoriteProductController.php` — `toggle(Product $product)`.
- **UPDATE** `app/Http/Controllers/Web/SellerShowController.php` — inject auth user lalu attach:
  - `followers_count` via `withCount('followers')` atau inline setAttribute.
  - `is_followed` flag true/false untuk current user (guest = false).
  - **PENTING**: seller followers count harus benar untuk semua pengunjung bukan cuma user login.
- **UPDATE** `app/Http/Controllers/Web/ProductShowController.php` — attach `is_favorited` flag ke product single resource untuk user login (jika route ini belum ada, pakai ProductIndexController sesuai struktur existing).
- **UPDATE** `app/Http/Controllers/Web/ProfileController.php` (method `__invoke`) — attach data:
  - `followedSellers` = SellerResource::collection(user()->followedSellers)
  - `favoriteProducts` = ProductResource::collection(user()->favoriteProducts->load(['category', 'images']))

### 4. API Resources
- **UPDATE** `SellerResource.php` — tambah field:
  - `followers_count: $this->followers_count ?? 0`
  - `is_followed: $this->when(auth()->check(), fn() => auth()->user()->followsSeller($this->resource))`
  - (jika resource dipanggil tanpa eager load followers_count, fallback aman 0 — no N+1)
- **UPDATE** `ProductResource.php` — tambah field:
  - `is_favorited: $this->when(auth()->check(), fn() => auth()->user()->hasFavorited($this->resource))`
    Gunakan pola `$this->when(...)` agar listing standar tidak dihantui query per item.
    **Catatan**: Mirip `sold_count` — hanya inject jika relation / data tersedia, hindari N+1.

### 5. Routes
- **UPDATE** `routes/web.php` — di grup `Route::middleware(['auth','verified'])`:
  ```php
  Route::post('sellers/{seller:slug}/follow', [SellerFollowController::class, 'toggle'])->name('sellers.follow');
  Route::post('products/{product:slug}/favorite', [FavoriteProductController::class, 'toggle'])->name('products.favorite');
  ```
  (slugs karena existing route model binding menggunakan slug di `sellers/{slug}` dan `products/{slug}`)

### 6. Frontend (Vue)

- **UPDATE** `resources/js/pages/Store/Show.vue`:
  - Hapus `disabled` + `title="Segera hadir"` dari tombol Follow (baris 212–219).
  - Tambah `useForm` / `<Form>` via Wayfinder route `sellers.follow()` POST dengan seller slug (bukan id).
  - Toggle state optimis local: setelah success, flip `isFollowed` dan `followersCount` ±1 — supaya tidak perlu reload page.
  - Aktifkan kolom stat "Pengikut": tampilkan `seller.followers_count ?? 0` (bukan `—` lagi).
  - Guest user: tombol Follow tetap ditampilkan tapi ketika diklik redirect ke login, atau disable dengan tooltip "Login untuk follow".
  - Styling: Jika `isFollowed` → button jadi "Mengikuti" dengan styling secondary (border + warna text normal); jika belum follow → brand-primary fill.

- **UPDATE** `resources/js/pages/Product/Show.vue`:
  - Tambah button ❤️ "Favorit" di area action (samping Add to Cart / Buy).
  - Pakai Wayfinder `products.favorite()` route POST.
  - Toggle state: filled heart jika `is_favorited` true, outline jika false.
  - User guest: button disabled dengan tooltip.

- **UPDATE** `resources/js/pages/Profile/Show.vue`:
  - Tambah dua item navigasi di `<nav>` sidebar (baris 169–216):
    - 🏪 **Toko Diikuti** → set `activeTab = 'following_sellers'`
    - ❤️ **Produk Favorit** → set `activeTab = 'favorite_products'`
  - Ubah type `activeTab` ref: `<'profile' | 'password' | 'following_sellers' | 'favorite_products'>`
  - Tambah defineProps baru: `followedSellers?: HomeSellerItem[] | { data: HomeSellerItem[] }`, `favoriteProducts?: HomeProductItem[] | { data: HomeProductItem[] }`
  - Tambah `<section v-show="activeTab === 'following_sellers'">` — grid kartu seller (foto, nama toko, lokasi, followers count + tombol "Lihat Toko" ke `/sellers/{slug}`). Empty state "Belum mengikuti toko manapun."
  - Tambah `<section v-show="activeTab === 'favorite_products'">` — grid `<ProductCard>` via `toProductCardData()` seperti homepage / products index. Empty state "Produk favorit masih kosong."

- **(Optional)** Jika tipe di `@/types/marketplace.ts` belum cukup: tambahkan field `is_followed?: boolean; followers_count?: number;` ke `HomeSellerItem` dan `is_favorited?: boolean;` ke `HomeProductItem` — supaya konsisten dengan backend inject.

### 7. Pest Tests
- **BARU** `tests/Feature/SellerFollowTest.php`:
  1. Guest user tidak bisa POST follow (redirect 302 ke login).
  2. Auth user follow seller aktif → record dibuat, followers_count bertambah 1.
  3. Double click follow = toggle unfollow → record hilang (idempotency via composite unique).
  4. Follow seller non-Active / tidak ada → 404 / tidak tercipta record.
- **BARU** `tests/Feature/FavoriteProductTest.php`:
  1. Guest toggle favorite → redirect ke login.
  2. Auth user favorit-kan produk aktif → record dibuat.
  3. Favorit produk draft / inactive → reject (tambah scope validasi di controller: hanya favorit yang `active`).
  4. Unfollow favorit berhasil dihapus.
- **Opsional tapi disarankan**: update `tests/Feature/HomeBestSellerSectionTest.php` tidak perlu diubah karena tidak terkait.

---

## Implementation Steps (dependency order)

1. Jalankan `php artisan make:migration` untuk **seller_follows** + **favorite_products** tables.
2. Buat dua model baru `SellerFollow` dan `FavoriteProduct`.
3. Edit `User`, `Seller`, `Product` — tambah relasi BelongsToMany + helper boolean methods.
4. Buat `SellerFollowController` + `FavoriteProductController` dengan method `toggle()`.
5. Daftarkan route `sellers.follow` + `products.favorite` di `web.php` grup auth/verified.
6. Jalankan `php artisan wayfinder:generate` agar typed route actions tersedia di `@/routes/` dan `@/actions/`.
7. Update `SellerResource` + `ProductResource` — tambah field count + flag.
8. Update `SellerShowController`, `ProductShowController`, `ProfileController` — eager load count + inject data.
9. (Vue) Edit `Store/Show.vue`: enable follow button + wire POST + toggle optimistic + tampilkan Pengikut count.
10. (Vue) Edit `Product/Show.vue`: tambah Favorit button + wire POST.
11. (Vue) Edit `Profile/Show.vue`: sidebar items Following/Favorite, dua tab section baru, mapping ke existing ProductCard.
12. Jalankan `php artisan migrate` di local dev.
13. Jalankan `vendor/bin/pint --dirty --format agent` untuk PHP styling.
14. Jalankan Pest test suite yang spesifik: `php artisan test --filter=SellerFollowTest` dan `FavoriteProductTest`.
15. Jalankan `vendor/bin/pest` untuk affected file test saja.
16. Jalankan `npm run build` atau `npm run dev` + cek diagnostics Vue (gunakan GetDiagnostics).

---

## Dependencies and Considerations

1. **Wayfinder re-run wajib** setiap menambah route baru agar import `use { store as sellersFollowStore } from '@/routes/sellers.follow'` dll. tersedia di frontend.
2. **N+1 Prevention**: Gunakan `withCount('followers')` bukan `count()` per item di loop. Gunakan `$this->when(auth()->check(), ...)` wrapper di Resource sebelum memanggil `followsSeller()` / `hasFavorited()` — perhatikan bahwa ini akan N query per item (N = jumlah produk/seller per halaman). Untuk listing > 50 item pertimbangkan eager load via `whereIn` di controller lalu setAttribute terlebih dahulu, baru masuk ke Resource collection. Plan ini mengasumsikan seller page hanya menampilkan 1 seller (OK), profile page menampilkan daftar yang user follow (user sendiri, tidak perlu cek ulang), dan ProductShow 1 produk per halaman (OK). Jika nanti perlu inject `is_followed` pada listing 50 produk, harus refactor eager-load flag.
3. **Authorization (Policy) opsional**: Tidak perlu policy untuk aksi follow self (user hanya bisa toggle follow dirinya sendiri). Di controller cukup pakai `auth()->id()` langsung.
4. **Composite Unique Index**: Wajib di migration — mencegah duplikat row jika user double-click. Toggle logic harus menggunakan `firstOrCreate` + `delete` atau `upsert` approach yang aman.
5. **Cascade Delete**: `seller_follows.seller_id` → on delete seller row, hapus semua follow-nya (mirip penghapusan order). `seller_follows.user_id` → jika user dihapus, follow hilang. Sama untuk favorite_products.
6. **Only Active Product / Seller**: Favorit kan hanya produk yang status `active` dan seller `SellerStatus::Active`. Jika user men-favorit seller yang suspend nanti, tampilan "Toko Diikuti" bisa sembunyikan seller non-active dengan filter di controller Profile (tapi record di DB tidak perlu dihapus — seller diaktifkan lagi otomatis muncul).
7. **Toast UX**: Gunakan `Inertia::flash('toast', ...)` existing convention supaya feedback visual konsisten dengan update profile.
8. **Guest behavior**: Di frontend, tombol follow/favorit untuk **guest user** arahkan ke `/login` (via `<Link :href="loginUrl()" method="get">` atau `router.visit(loginStore route)`), atau disable + tooltip. Jangan biarkan guest submit (auth middleware akan 302 tapi tidak user-friendly). Karena tombol ini berada di halaman seller yang publicly accessible — pakai check `$page.props.auth.user` untuk condition.

---

## Validation

### PHP / Backend
1. `php artisan route:list --name=follow` — kedua route ada di grup `auth,verified`.
2. `php artisan tinker --execute 'User::find(1)->followedSellers()->count();'` (setelah seed manual) mengembalikan angka benar.
3. Pest tests baru (SellerFollowTest + FavoriteProductTest) **hijau semua**.
4. `vendor/bin/pint --dirty --format agent` lulus.
5. Buka halaman `/sellers/{slug}` user login → Pengikut count angka nyata bukan `—`.
6. Buka halaman `/products/{slug}` → ada button Favorit; diklik → state berubah tanpa reload page (optimistic update).
7. Buka `/profile` → Tab "Toko Diikuti" dan "Produk Favorit" muncul di sidebar; empty benar kalau belum ada.

### Frontend
1. GetDiagnostics bersih di 3 file: `Store/Show.vue`, `Product/Show.vue`, `Profile/Show.vue`.
2. `npm run build` tidak ada error TS / unused imports.
3. Manual test interaksi:
   - Follow seller → button teks "Mengikuti" + Pengikut +1 → klik lagi → kembali "Follow" + count -1.
   - Favorite produk → hati filled → klik lagi → outline.
   - Logout → tombol follow disabled / redirect login → tidak bisa aksi.

---

## Risks

| Risiko | Mitigasi |
|--------|----------|
| **N+1 queries** pada product collection saat inject `is_favorited` di ProductResource (loop > 20 produk). | Di scope plan ini, hanya halaman Product/Show.vue (1 produk) + Profile favorite list (pribadi user — tidak perlu cek is_favorited ulang karena sudah pasti true). Jika nanti akan inject ke listing grid, refactor controller untuk eager-load favorit user via `whereIn product_id` lalu setAttribute, JANGAN biarkan Resource per-item query DB. |
| **Race condition double click toggle** → insert 2 row follow sebelum delete sempat dijalankan. | Gunakan DB transaction + composite UNIQUE index di migration. Exception pada unique constraint ditangkap → otomatis fallback delete. Atau cara paling aman: lakukan `lockForUpdate()` / pada pivot gunakan `syncWithoutDetaching` / `toggle` Laravel: `$user->followedSellers()->toggle([$sellerId])`. **Laravel `BelongsToMany::toggle()` sudah atomic-friendly dan otomatis menangani add/remove tanpa duplikat.** Gunakan ini, jangan manual firstOrCreate + delete. |
| Wayfinder generate error atau typed functions tak muncul di `@/routes/`. | Run `php artisan route:clear && php artisan wayfinder:generate`. Pastikan route name unik (tidak bentrok dengan existing seller routes). |
| Follow count berbeda dengan hasil Resource (cache issue / tanpa eager load). | Di `SellerShowController` pastikan panggil `withCount('followers')` pada builder SEBELUM `firstOrFail()`. Jangan `$seller->followers()->count()` setelah fetch. `SellerResource` fallback value 0 jika `followers_count` attribute tidak ada, agar tidak crash pada halaman yang tidak menggunakannya. |
| Existing tests BestSeller / dll rusak karena migration baru dijalankan di CI. | Migration baru menambah tabel saja (tidak mengubah existing tabel kolom). `php artisan test` menjalankan refresh migration dengan SQLite in-memory — zero impact. |

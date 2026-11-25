<?php
// =====================================================================
//  AMBOKO ERP - Helper bersama (dipakai semua halaman)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* Fallback jika ekstensi mbstring tidak aktif (XAMPP biasanya sudah aktif) */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return preg_match_all('/./us', (string)$s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null, $enc = null) {
        preg_match_all('/./us', (string)$s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}

/* ---------------------------------------------------------------------
   FORMAT & OUTPUT
--------------------------------------------------------------------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Rp120.000 (gaya keranjang / checkout) */
function rp($n): string { return 'Rp' . number_format((float)$n, 0, ',', '.'); }

/** Rp.120.000 (gaya katalog / halaman produk) */
function rp_dot($n): string { return 'Rp.' . number_format((float)$n, 0, ',', '.'); }

/** URL gambar: file upload admin ('uploads/..') atau aset desain ('asset/..') */
function img(?string $path): string
{
    if (!$path) return 'asset/placeholder.jpg';
    if (strpos($path, 'uploads/') === 0) return $path;
    return 'asset/' . rawurlencode($path);
}

const ID_MONTHS = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const ID_MONTHS_SHORT = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** "12 Apr 2026" */
function date_short($d): string
{
    $t = strtotime((string)$d);
    return date('j', $t) . ' ' . ID_MONTHS_SHORT[(int)date('n', $t)] . ' ' . date('Y', $t);
}

/** "12 Apr 2026" dengan nol di depan seperti desain Jacket Run ("08 Apr 2026") */
function date_short_pad($d): string
{
    $t = strtotime((string)$d);
    return date('d', $t) . ' ' . ID_MONTHS_SHORT[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function date_long($d): string
{
    $t = strtotime((string)$d);
    return date('j', $t) . ' ' . ID_MONTHS[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function add_working_days(DateTime $dt, int $days): DateTime
{
    $dt = clone $dt;
    while ($days > 0) {
        $dt->modify('+1 day');
        if ((int)$dt->format('N') < 6) $days--;
    }
    return $dt;
}

/** Estimasi tiba: 3-4 hari kerja dari sekarang -> "12-13 Oktober 2026" */
function est_arrival(int $min = 3, int $max = 4): string
{
    $a = add_working_days(new DateTime(), $min);
    $b = add_working_days(new DateTime(), $max);
    $am = (int)$a->format('n'); $bm = (int)$b->format('n');
    if ($am === $bm) {
        return $a->format('j') . '-' . $b->format('j') . ' ' . ID_MONTHS[$bm] . ' ' . $b->format('Y');
    }
    return $a->format('j') . ' ' . ID_MONTHS[$am] . ' - ' . $b->format('j') . ' ' . ID_MONTHS[$bm] . ' ' . $b->format('Y');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($parts[0], 0, 2));
}

/** Bintang emas (FontAwesome). $rating dibulatkan. */
function stars($rating, string $size = 'text-sm'): string
{
    $full = (int)round((float)$rating);
    $out = '<span class="inline-flex gap-0.5 ' . $size . '" style="color:#f5b301" aria-label="Rating ' . e($rating) . ' dari 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="' . ($i <= $full ? 'fa-solid' : 'fa-regular') . ' fa-star"></i>';
    }
    return $out . '</span>';
}

/* ---------------------------------------------------------------------
   FLASH, REDIRECT, CSRF
--------------------------------------------------------------------- */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit();
}

function flash(string $type, string $msg): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) if ($f['msg'] === $msg) return; // hindari pesan ganda
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_pull(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }

/** Hentikan request POST jika token tidak cocok. */
function csrf_check(): void
{
    $tok = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($tok) || !hash_equals($_SESSION['csrf'] ?? '', $tok)) {
        http_response_code(419);
        die('Sesi formulir kedaluwarsa. Silakan kembali dan muat ulang halaman.');
    }
}

/** Alamat kembali yang aman (hanya path relatif di situs ini) */
function safe_next(?string $url, string $fallback = 'katalog.php'): string
{
    if (!$url || preg_match('#^(https?:)?//#i', $url) || strpos($url, "\n") !== false) return $fallback;
    return ltrim($url, '/');
}

/* ---------------------------------------------------------------------
   AUTH
--------------------------------------------------------------------- */
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function user_id(): ?int { return is_logged_in() ? (int)$_SESSION['user_id'] : null; }

function require_login(string $next = ''): void
{
    if (!is_logged_in()) {
        redirect('login.php' . ($next ? '?next=' . urlencode($next) : ''));
    }
}

function require_admin(): void
{
    if (!is_logged_in() || !is_admin()) redirect('login.php');
}

function current_user(mysqli $conn): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    if (!is_logged_in()) return $u = null;
    $st = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $id = user_id();
    $st->bind_param('i', $id);
    $st->execute();
    return $u = ($st->get_result()->fetch_assoc() ?: null);
}

/* ---------------------------------------------------------------------
   PRODUK
--------------------------------------------------------------------- */
/** Konfigurasi tampilan per produk (JSON di kolom ui_config) dengan nilai default. */
function product_ui(array $p): array
{
    $def = [
        'label_style'    => 'upper',        // upper | title
        'color_style'    => 'outline',      // filled (pill hitam) | outline (pill putih bergaris)
        'review_style'   => 'breakdown_pct',// breakdown_pct | breakdown_count | metrics | none
        'review_layout'  => 'list',         // list | cards | cards_white
        'summary_title'  => '',
        'summary_sub'    => 'Berdasarkan ulasan pembeli terverifikasi',
        'complete_look'  => false,
        'marquee'        => '',
        'rounded_marquee'=> false,
        'highlights'     => [],
        'load_more'      => false,
        'full_name_reviews' => false,
        'review_meta'    => '',
        'icons'          => false,
    ];
    $j = json_decode($p['ui_config'] ?? '', true);
    return is_array($j) ? array_merge($def, $j) : $def;
}

function product_card_name(array $p): string { return $p['short_name'] ?: $p['name']; }

/** Grup opsi + nilainya untuk satu produk. */
function product_options(mysqli $conn, int $productId): array
{
    static $cache = [];
    if (isset($cache[$productId])) return $cache[$productId];
    $groups = [];
    $st = $conn->prepare("SELECT * FROM product_option_groups WHERE product_id = ? ORDER BY sort_order, id");
    $st->bind_param('i', $productId);
    $st->execute();
    $res = $st->get_result();
    while ($g = $res->fetch_assoc()) { $g['values'] = []; $groups[$g['id']] = $g; }
    if ($groups) {
        $ids = implode(',', array_map('intval', array_keys($groups)));
        $r = $conn->query("SELECT * FROM product_option_values WHERE group_id IN ($ids) ORDER BY sort_order, id");
        while ($v = $r->fetch_assoc()) $groups[$v['group_id']]['values'][] = $v;
    }
    return $cache[$productId] = array_values($groups);
}

function wishlist_ids(mysqli $conn): array
{
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    if (is_logged_in()) {
        $uid = user_id();
        $r = $conn->query("SELECT product_id FROM wishlists WHERE user_id = $uid");
        while ($row = $r->fetch_assoc()) $ids[(int)$row['product_id']] = true;
    }
    return $ids;
}

/* ---------------------------------------------------------------------
   KERANJANG  (disimpan di session; satu baris = produk + kombinasi opsi)
--------------------------------------------------------------------- */
function cart_get(): array { return $_SESSION['cart'] ?? []; }

function cart_count(): int
{
    $n = 0;
    foreach (cart_get() as $line) $n += (int)($line['qty'] ?? 0);
    return $n;
}

/**
 * Tambahkan produk ke keranjang.
 * @param array $selection  [group_id => value_id]; opsi yang kosong memakai nilai default.
 * @return array [bool ok, string pesan]
 */
function cart_add(mysqli $conn, int $productId, array $selection = [], int $qty = 1): array
{
    $st = $conn->prepare("SELECT id, name, stock, image_url, is_active FROM products WHERE id = ?");
    $st->bind_param('i', $productId);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    if (!$p || !$p['is_active']) return [false, 'Produk tidak ditemukan.'];
    if ((int)$p['stock'] < 1) return [false, 'Maaf, stok ' . $p['name'] . ' sedang habis.'];

    $opts = [];
    $image = $p['image_url'];
    foreach (product_options($conn, $productId) as $g) {
        $chosen = null;
        $want = (int)($selection[$g['id']] ?? 0);
        foreach ($g['values'] as $v) {
            if ((int)$v['id'] === $want) { $chosen = $v; break; }
        }
        if (!$chosen) {
            foreach ($g['values'] as $v) if ($v['is_default']) { $chosen = $v; break; }
            if (!$chosen && $g['values']) $chosen = $g['values'][0];
        }
        if (!$chosen) continue;
        $opts[] = ['group_id' => (int)$g['id'], 'label' => $g['cart_label'], 'value_id' => (int)$chosen['id'], 'value' => $chosen['label']];
        if ($g['type'] === 'color' && $chosen['image_url']) $image = $chosen['image_url'];
    }

    $key = md5($productId . '|' . json_encode(array_column($opts, 'value_id')));
    $cart = cart_get();
    $have = $cart[$key]['qty'] ?? 0;
    $newQty = $have + $qty;
    $msg = 'Ditambahkan ke keranjang.';
    if ($newQty > (int)$p['stock']) {
        $newQty = (int)$p['stock'];
        $msg = 'Jumlah disesuaikan dengan stok tersedia (' . $p['stock'] . ' pcs).';
    }
    $cart[$key] = ['product_id' => $productId, 'qty' => $newQty, 'options' => $opts, 'image' => $image];
    $_SESSION['cart'] = $cart;
    return [true, $msg];
}

/** Baris keranjang lengkap dengan data produk terbaru dari database. */
function cart_lines(mysqli $conn): array
{
    $cart = cart_get();
    if (!$cart) return [];
    $ids = array_unique(array_map(fn($l) => (int)$l['product_id'], $cart));
    $in = implode(',', $ids);
    $products = [];
    $r = $conn->query("SELECT p.*, c.group_label, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN ($in)");
    while ($row = $r->fetch_assoc()) $products[(int)$row['id']] = $row;

    $lines = [];
    foreach ($cart as $key => $l) {
        $p = $products[(int)$l['product_id']] ?? null;
        if (!$p || !$p['is_active'] || (int)$p['stock'] < 1) { unset($_SESSION['cart'][$key]); continue; }
        $qty = min((int)$l['qty'], (int)$p['stock']);
        if ($qty !== (int)$l['qty']) $_SESSION['cart'][$key]['qty'] = $qty;
        $lines[] = [
            'key'      => $key,
            'product'  => $p,
            'qty'      => $qty,
            'options'  => $l['options'],
            'image'    => $l['image'] ?: $p['image_url'],
            'unit'     => (int)$p['price'],
            'subtotal' => (int)$p['price'] * $qty,
        ];
    }
    return $lines;
}

/* ---------------------------------------------------------------------
   VOUCHER & TOTAL
--------------------------------------------------------------------- */
/** @return array ['ok'=>bool, 'msg'=>string, 'discount'=>int, 'row'=>?array] */
function voucher_check(mysqli $conn, string $code, int $subtotal): array
{
    $code = strtoupper(trim($code));
    $fail = fn($m) => ['ok' => false, 'msg' => $m, 'discount' => 0, 'row' => null];
    if ($code === '') return $fail('Masukkan kode voucher.');

    $st = $conn->prepare("SELECT * FROM vouchers WHERE code = ?");
    $st->bind_param('s', $code);
    $st->execute();
    $v = $st->get_result()->fetch_assoc();
    if (!$v || !$v['is_active']) return $fail('Kode voucher tidak valid.');
    if ($v['expires_at'] && $v['expires_at'] < date('Y-m-d')) return $fail('Voucher sudah kedaluwarsa.');
    if ($v['usage_limit'] !== null && (int)$v['used_count'] >= (int)$v['usage_limit']) return $fail('Kuota voucher sudah habis.');
    if ($subtotal < (int)$v['min_purchase']) return $fail('Minimal belanja ' . rp($v['min_purchase']) . ' untuk voucher ini.');

    if ($v['type'] === 'percent') {
        $disc = (int)floor($subtotal * (int)$v['value'] / 100);
        if ($v['max_discount'] !== null) $disc = min($disc, (int)$v['max_discount']);
    } else {
        $disc = min((int)$v['value'], $subtotal);
    }
    return ['ok' => true, 'msg' => 'Voucher ' . $v['code'] . ' berhasil dipakai.', 'discount' => $disc, 'row' => $v];
}

/**
 * Hitung seluruh komponen pembayaran.
 * Harga produk sudah termasuk PPN 11% -> pajak hanya ditampilkan (total * 11/111).
 */
function cart_totals(mysqli $conn, array $lines, string $courier = 'regular'): array
{
    $subtotal = 0; $pcs = 0;
    foreach ($lines as $l) { $subtotal += $l['subtotal']; $pcs += $l['qty']; }

    $discount = 0; $voucherCode = null;
    if (!empty($_SESSION['voucher'])) {
        $chk = voucher_check($conn, $_SESSION['voucher'], $subtotal);
        if ($chk['ok']) { $discount = $chk['discount']; $voucherCode = $chk['row']['code']; }
        else unset($_SESSION['voucher']);
    }

    $free = $subtotal >= FREE_SHIPPING_MIN;
    $regular = ($subtotal > 0 && !$free) ? REGULAR_SHIPPING : 0;
    $shipping = $courier === 'express' ? ($regular + EXPRESS_SURCHARGE) : $regular;
    if ($subtotal === 0) $shipping = 0;
    $service = $subtotal > 0 ? SERVICE_FEE : 0;
    $total = max(0, $subtotal - $discount + $shipping + $service);

    return [
        'pcs' => $pcs, 'subtotal' => $subtotal, 'discount' => $discount, 'voucher' => $voucherCode,
        'free_shipping' => $free, 'regular_fee' => REGULAR_SHIPPING, 'shipping' => $shipping,
        'service' => $service, 'total' => $total,
        'tax' => (int)round($total * PPN_RATE / (100 + PPN_RATE)),
    ];
}

/* ---------------------------------------------------------------------
   PESANAN & STOK
--------------------------------------------------------------------- */
const ORDER_STATUS = [
    'pending_payment' => ['Menunggu Pembayaran', 'bg-amber-100 text-amber-800'],
    'placed'          => ['Pesanan Masuk',       'bg-blue-100 text-blue-800'],
    'packing'         => ['Diproses',            'bg-indigo-100 text-indigo-800'],
    'shipped'         => ['Dikirim',             'bg-sky-100 text-sky-800'],
    'delivered'       => ['Selesai',             'bg-green-100 text-green-800'],
    'cancelled'       => ['Dibatalkan',          'bg-red-100 text-red-700'],
];

function order_status_badge(string $status): string
{
    [$label, $cls] = ORDER_STATUS[$status] ?? [$status, 'bg-gray-100 text-gray-700'];
    return '<span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold ' . $cls . '">' . e($label) . '</span>';
}

function add_order_history(mysqli $conn, int $orderId, string $status, ?string $note = null): void
{
    $st = $conn->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)");
    $st->bind_param('iss', $orderId, $status, $note);
    $st->execute();
}

/** Catat pergerakan stok (ledger). Panggil SETELAH kolom products.stock diperbarui. */
function stock_log(mysqli $conn, int $productId, string $type, int $change, ?string $note = null, ?int $orderId = null, ?int $userId = null): void
{
    $r = $conn->query("SELECT stock FROM products WHERE id = " . (int)$productId);
    $after = (int)($r->fetch_assoc()['stock'] ?? 0);
    $st = $conn->prepare("INSERT INTO stock_movements (product_id, order_id, user_id, type, qty_change, stock_after, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->bind_param('iiisiis', $productId, $orderId, $userId, $type, $change, $after, $note);
    $st->execute();
}

/** Kembalikan stok pesanan yang dibatalkan. */
function restore_order_stock(mysqli $conn, int $orderId): void
{
    $items = $conn->query("SELECT product_id, qty FROM order_items WHERE order_id = " . (int)$orderId);
    $uid = user_id();
    while ($it = $items->fetch_assoc()) {
        $conn->query("UPDATE products SET stock = stock + " . (int)$it['qty'] . " WHERE id = " . (int)$it['product_id']);
        stock_log($conn, (int)$it['product_id'], 'cancel_return', (int)$it['qty'], 'Pesanan dibatalkan', $orderId, $uid);
    }
}

function payment_methods(): array
{
    return [
        'qris' => ['name' => 'QRIS (GoPay, OVO, ShopeePay, Dana, BCA)', 'tag' => 'POPULER', 'desc' => 'Pindai kode QR instan dari semua e-wallet & mobile banking Indonesia.',
                   'channel' => 'QRIS Instant', 'detail' => 'BCA / GoPay / Mandiri', 'paid_label' => 'QRIS Terverifikasi'],
        'va'   => ['name' => 'Transfer Virtual Account', 'tag' => 'VERIFIKASI CEPAT', 'desc' => 'BCA, Mandiri, BNI, BRI, Permata VA via generator otomatis.',
                   'channel' => 'Virtual Account', 'detail' => 'BCA / Mandiri / BNI / BRI', 'paid_label' => 'Virtual Account Terverifikasi'],
        'card' => ['name' => 'Kartu Kredit / Debit Online', 'tag' => 'VISA / MASTERCARD / JCB', 'desc' => 'Pembayaran langsung aman dengan 3D Secure OTP.',
                   'channel' => 'Kartu Kredit / Debit', 'detail' => 'Visa / Mastercard / JCB', 'paid_label' => '3D Secure Terverifikasi'],
    ];
}

/* ---------------------------------------------------------------------
   PENGATURAN SITUS (tabel site_settings, diatur dari admin)
   Aman dipakai walau tabelnya belum ada: kembali ke nilai default.
--------------------------------------------------------------------- */
function site_setting(string $key, string $default = ''): string
{
    global $conn;
    static $all = null;
    if ($all === null) {
        $all = [];
        $r = $conn->query("SELECT k, v FROM site_settings");
        while ($r && ($row = $r->fetch_assoc())) $all[$row['k']] = (string)$row['v'];
    }
    return ($all[$key] ?? '') !== '' ? $all[$key] : $default;
}

const DEFAULT_STORE_ADDRESS = 'Jl. Kemang No.46, RT.5/RW.24, Sukatani, Kec. Tapos, Kota Depok, Jawa Barat 16454';

/** Tautan Google Maps untuk alamat toko (bisa diganti admin lewat Pengaturan). */
function store_maps_link(): string
{
    $custom = site_setting('maps_url');
    if ($custom !== '' && preg_match('#^https?://#i', $custom)) return $custom;
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(site_setting('address', DEFAULT_STORE_ADDRESS));
}

/** URL peta tertanam (iframe) untuk alamat toko. */
function store_maps_embed(): string
{
    return 'https://www.google.com/maps?q=' . rawurlencode(site_setting('address', DEFAULT_STORE_ADDRESS)) . '&output=embed';
}

/** Pecah alamat jadi beberapa baris pendek: "Jl. Kemang No.46, RT.5/RW.24" / "Sukatani, ..." */
function address_lines(string $addr, int $max = 34): array
{
    $lines = []; $cur = '';
    foreach (array_map('trim', explode(',', $addr)) as $part) {
        if ($part === '') continue;
        if ($cur === '') $cur = $part;
        elseif (mb_strlen($cur . ', ' . $part) <= $max) $cur .= ', ' . $part;
        else { $lines[] = $cur; $cur = $part; }
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines;
}

/* ---------------------------------------------------------------------
   RATING & ULASAN NYATA
   Hanya ulasan dari pembeli yang benar-benar punya pesanan LUNAS
   berisi produk tersebut (tidak dibatalkan / refund) yang dihitung.
--------------------------------------------------------------------- */
const REVIEW_VALID_SQL = "r.user_id IS NOT NULL AND EXISTS (
    SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE oi.product_id = r.product_id AND o.user_id = r.user_id
      AND o.payment_status = 'paid' AND o.status <> 'cancelled')";

function product_review_summary(mysqli $conn, int $pid): array
{
    $sum = ['total' => 0, 'avg' => 0.0, 'c' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], 'recommend' => 0];
    $q = $conn->query("SELECT r.rating, COUNT(*) n FROM product_reviews r WHERE r.product_id = " . (int)$pid . " AND " . REVIEW_VALID_SQL . " GROUP BY r.rating");
    $total = 0; $pts = 0;
    while ($q && ($row = $q->fetch_assoc())) {
        $k = max(1, min(5, (int)$row['rating']));
        $sum['c'][$k] += (int)$row['n'];
        $total += (int)$row['n']; $pts += $k * (int)$row['n'];
    }
    if ($total > 0) {
        $sum['total'] = $total;
        $sum['avg'] = round($pts / $total, 1);
        $sum['recommend'] = (int)round(($sum['c'][4] + $sum['c'][5]) / $total * 100);
    }
    return $sum;
}

/** Samakan kolom ringkasan di tabel products (dipakai halaman admin) dengan data nyata. */
function product_review_sync(mysqli $conn, int $pid, array $s): void
{
    $st = $conn->prepare("UPDATE products SET rating_avg=?, review_total=?, rating_5=?, rating_4=?, rating_3=?, rating_2=?, rating_1=?, recommend_pct=?, recommend_note=NULL WHERE id=?
        AND (rating_avg<>? OR review_total<>? OR rating_5<>? OR rating_4<>? OR rating_3<>? OR rating_2<>? OR rating_1<>? OR recommend_pct<>?)");
    if (!$st) return;
    $a = (float)$s['avg']; $t = (int)$s['total']; $c5 = $s['c'][5]; $c4 = $s['c'][4]; $c3 = $s['c'][3]; $c2 = $s['c'][2]; $c1 = $s['c'][1]; $rc = (int)$s['recommend'];
    $st->bind_param('diiiiiiiidiiiiiii', $a, $t, $c5, $c4, $c3, $c2, $c1, $rc, $pid, $a, $t, $c5, $c4, $c3, $c2, $c1, $rc);
    $st->execute();
}

/** Pesanan terkirim (delivered, lunas) milik user berisi produk ini -> item untuk info varian; null jika tidak ada. */
function buyer_delivered_item(mysqli $conn, int $uid, int $pid): ?array
{
    $st = $conn->prepare("SELECT oi.options FROM order_items oi JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id = ? AND o.user_id = ? AND o.payment_status = 'paid' AND o.status = 'delivered' ORDER BY o.id DESC LIMIT 1");
    $st->bind_param('ii', $pid, $uid);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

/* ---------------------------------------------------------------------
   PEMBAYARAN: QRIS & VIRTUAL ACCOUNT (diatur admin di menu Pembayaran)
--------------------------------------------------------------------- */
const VA_BANKS = [
    'bca'     => ['BCA',     '39358'],
    'mandiri' => ['Mandiri', '88908'],
    'bni'     => ['BNI',     '8277'],
    'bri'     => ['BRI',     '26215'],
    'permata' => ['Permata', '8528'],
];

/** Gambar QRIS aktif (hasil unggahan admin, atau contoh bawaan). */
function qris_image(): string
{
    $p = site_setting('qris_image');
    return $p !== '' ? img($p) : 'asset/qris-default.png';
}

/** Nomor VA 16 digit: kode bank/perusahaan + nomor pesanan (nol di depan). */
function va_number(string $bank, int $orderId): string
{
    $prefix = preg_replace('/\D/', '', site_setting('va_' . $bank, VA_BANKS[$bank][1] ?? ''));
    $len = 16 - strlen($prefix);
    return $prefix . str_pad((string)$orderId, max($len, strlen((string)$orderId)), '0', STR_PAD_LEFT);
}

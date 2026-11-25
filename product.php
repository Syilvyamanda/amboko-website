<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$st = $conn->prepare("SELECT p.*, c.name AS category_name, c.tag_label FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.is_active = 1");
$st->bind_param('i', $id);
$st->execute();
$p = $st->get_result()->fetch_assoc();

if (!$p) {
    http_response_code(404);
    page_head('Produk tidak ditemukan - AMBOKO');
    store_header('app', 'catalog', $conn);
    echo '<main class="max-w-xl mx-auto text-center py-28 px-6"><h1 class="font-head font-extrabold text-3xl mb-3">Produk tidak ditemukan</h1><p class="text-gray-500 mb-6">Produk yang Anda cari tidak tersedia atau sudah tidak dijual.</p><a href="katalog.php" class="inline-block bg-black text-white font-bold text-sm px-8 py-3.5 rounded-xl">LIHAT KATALOG</a></main>';
    store_footer('app');
    page_end();
    exit;
}

// Kirim ulasan: hanya pembeli yang pesanannya sudah diterima dan belum pernah mengulas produk ini
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    csrf_check();
    $uid = user_id();
    if (!$uid) redirect('login.php?next=' . urlencode('product.php?id=' . $id . '#reviews'));
    $buy = buyer_delivered_item($conn, $uid, $id);
    $dup = $conn->query("SELECT 1 FROM product_reviews WHERE product_id = $id AND user_id = $uid")->num_rows > 0;
    $rate = (int)($_POST['rating'] ?? 0);
    $text = trim((string)($_POST['comment'] ?? ''));
    if (!$buy) flash('error', 'Ulasan hanya bisa diberikan setelah pesanan produk ini diterima.');
    elseif ($dup) flash('error', 'Anda sudah memberi ulasan untuk produk ini.');
    elseif ($rate < 1 || $rate > 5) flash('error', 'Pilih rating 1 sampai 5 bintang.');
    elseif (mb_strlen($text) < 10) flash('error', 'Ulasan minimal 10 karakter.');
    else {
        $u = current_user($conn);
        $opts = json_decode($buy['options'] ?? '[]', true) ?: [];
        $map = [];
        foreach ($opts as $o) $map[$o['label']] = $o['value'];
        $variant = trim(($map['WARNA'] ?? '') . (isset($map['WARNA'], $map['SIZE']) ? ' / ' : '') . ($map['SIZE'] ?? '')) ?: null;
        $text = mb_substr($text, 0, 1000);
        $ins = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, reviewer_name, rating, comment, variant_info, is_verified, created_at) VALUES (?,?,?,?,?,?,1,NOW())");
        $nm = $u['name'];
        $ins->bind_param('iisiss', $id, $uid, $nm, $rate, $text, $variant);
        $ins->execute();
        flash('success', 'Terima kasih, ulasan Anda sudah tampil.');
    }
    redirect('product.php?id=' . $id . '#reviews');
}

$ui = product_ui($p);
$upper = $ui['label_style'] === 'upper';
$groups = product_options($conn, $id);

// Galeri
$gallery = [];
$r = $conn->query("SELECT image_url FROM product_images WHERE product_id = $id ORDER BY sort_order, id");
while ($row = $r->fetch_assoc()) $gallery[] = $row['image_url'];
if (!$gallery && $p['image_url']) $gallery[] = $p['image_url'];
$gallery = array_values(array_filter($gallery, function ($g) { return strpos($g, 'uploads/') === 0 || is_file(__DIR__ . '/asset/' . $g); }));

// Gambar awal = gambar warna default (jika ada) atau foto pertama galeri
$mainImage = $gallery[0] ?? $p['image_url'];
foreach ($groups as $g) {
    if ($g['type'] !== 'color') continue;
    foreach ($g['values'] as $v) if ($v['is_default'] && $v['image_url']) $mainImage = $v['image_url'];
}

// Ulasan: hanya dari pembeli nyata (pesanan lunas berisi produk ini)
$sum = product_review_summary($conn, $id);
product_review_sync($conn, $id, $sum);
$p['rating_avg'] = $sum['avg'];
$showMore = !empty($_GET['more']);
$limit = $showMore ? 30 : 3;
$reviews = $conn->query("SELECT r.* FROM product_reviews r WHERE r.product_id = $id AND " . REVIEW_VALID_SQL . " ORDER BY r.created_at DESC, r.id DESC LIMIT $limit")->fetch_all(MYSQLI_ASSOC);
$remaining = max(0, $sum['total'] - count($reviews));
$canReview = false;
if (is_logged_in()) {
    $uidR = user_id();
    $canReview = buyer_delivered_item($conn, $uidR, $id) !== null
        && $conn->query("SELECT 1 FROM product_reviews WHERE product_id = $id AND user_id = $uidR")->num_rows === 0;
}

// Complete The Look
$related = [];
if ($ui['complete_look']) {
    $r = $conn->query("SELECT p.* FROM product_relations pr JOIN products p ON p.id = pr.related_product_id WHERE pr.product_id = $id AND p.is_active = 1 ORDER BY pr.sort_order");
    $related = $r->fetch_all(MYSQLI_ASSOC);
}

$discountPct = ($p['old_price'] && $p['old_price'] > $p['price']) ? (int)round((1 - $p['price'] / $p['old_price']) * 100) : 0;
$outOfStock = (int)$p['stock'] < 1;
$label = fn($s) => $upper ? '<span class="uppercase tracking-[0.08em]">' . e($s) . '</span>' : e($s);
$total = max(1, $sum['total']);
$pct = fn($n) => (int)round($n / $total * 100);
$ctaText = $outOfStock ? 'Stok Habis' : ($p['availability'] === 'pre_order' ? 'Pre-Order' : 'Add to Cart');

page_head($p['name'] . ' - AMBOKO', ['bodyClass' => 'bg-white']);
store_header('app', 'catalog', $conn);
?>

<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-5">
    <a href="katalog.php" onclick="if(document.referrer.indexOf(location.host)>-1){history.back();return false;}" class="inline-flex items-center gap-2 text-[13px] font-semibold hover:opacity-60 transition mb-5">
        <i class="fa-solid fa-arrow-left text-xs"></i> Back
    </a>

    <div class="grid md:grid-cols-2 gap-8 lg:gap-12 items-start">
        <!-- ================= GALERI ================= -->
        <div class="md:sticky md:top-24">
            <div class="bg-paper rounded-2xl overflow-hidden flex items-center justify-center aspect-[4/5] md:aspect-auto md:h-[min(74vh,720px)]">
                <img id="mainImg" src="<?php echo e(img($mainImage)); ?>" alt="<?php echo e($p['name']); ?>" class="w-full h-full object-contain mix-blend-multiply">
            </div>
            <?php if (count($gallery) > 1): ?>
            <div class="flex gap-2.5 mt-3 overflow-x-auto no-scrollbar pb-1" id="thumbs">
                <?php foreach ($gallery as $i => $g): ?>
                    <button type="button" data-src="<?php echo e(img($g)); ?>" class="thumb shrink-0 w-[62px] h-[78px] rounded-xl bg-paper overflow-hidden border-2 <?php echo $g === $mainImage ? 'border-black' : 'border-transparent'; ?> hover:border-gray-400 transition" aria-label="Foto <?php echo $i + 1; ?>">
                        <img src="<?php echo e(img($g)); ?>" alt="" loading="lazy" class="w-full h-full object-cover">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ================= INFO & PEMBELIAN ================= -->
        <div class="min-w-0">
            <span class="inline-block bg-[#e9e9ea] rounded-full text-[10px] font-bold tracking-[0.1em] px-3 py-1 uppercase"><?php echo e($p['tag_label'] ?: $p['category_name']); ?></span>
            <h1 class="font-head font-extrabold text-[clamp(30px,3.4vw,46px)] leading-[1.04] tracking-tight mt-3"><?php echo e($p['name']); ?></h1>

            <div class="flex items-center flex-wrap gap-2.5 mt-2.5">
                <span class="font-head font-bold text-[22px] md:text-[24px]"><?php echo e(rp_dot($p['price'])); ?></span>
                <?php if ($p['old_price']): ?><span class="text-[11px] text-gray-400 line-through"><?php echo e(rp_dot($p['old_price'])); ?></span><?php endif; ?>
                <?php if ($p['badge_text']): ?>
                    <span class="bg-[#e4e4e5] text-gray-700 text-[9px] font-bold tracking-[0.1em] px-2 py-0.5 rounded uppercase"><?php echo e($p['badge_text']); ?></span>
                <?php elseif ($discountPct > 0): ?>
                    <span class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-md">-<?php echo $discountPct; ?>%</span>
                <?php endif; ?>
            </div>

            <form action="keranjang.php" method="post" class="mt-6" id="buyForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">

                <?php foreach ($groups as $g):
                    $default = null;
                    foreach ($g['values'] as $v) if ($v['is_default']) $default = $v;
                    $default = $default ?: ($g['values'][0] ?? null);
                    $shown = $default ? ($default['display_label'] ?: $default['label']) : '';
                ?>
                <fieldset class="mb-5 option-group" data-group="<?php echo (int)$g['id']; ?>">
                    <div class="flex items-center justify-between mb-2">
                        <legend class="text-[12px] font-bold <?php echo $upper ? '' : 'text-[13px]'; ?>"><?php echo $label($g['title']); ?></legend>
                        <?php if ($g['show_value']): ?>
                            <span class="text-[10px] font-bold tracking-wide uppercase text-gray-600 selected-value"><?php echo e($shown); ?></span>
                        <?php elseif ($g['guide_label']): ?>
                            <a href="info.php?p=size" class="text-[10px] font-bold tracking-[0.1em] underline underline-offset-4 text-gray-500 hover:text-black <?php echo $upper ? 'uppercase' : ''; ?>"><?php echo e($g['guide_label']); ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($g['values'] as $v):
                            $checked = $default && $v['id'] === $default['id'];
                            $disp = $v['display_label'] ?: $v['label'];
                        ?>
                        <label class="cursor-pointer">
                            <input type="radio" class="peer sr-only opt-input" name="options[<?php echo (int)$g['id']; ?>]" value="<?php echo (int)$v['id']; ?>"
                                   data-display="<?php echo e($disp); ?>" data-image="<?php echo $v['image_url'] ? e(img($v['image_url'])) : ''; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                            <?php if ($g['type'] === 'color'):
                                $dot = $v['hex2']
                                    ? 'background:linear-gradient(90deg,' . e($v['hex']) . ' 50%,' . e($v['hex2']) . ' 50%);border:1px solid #111'
                                    : 'background:' . e($v['hex']);
                                $sel = $ui['color_style'] === 'filled'
                                    ? 'bg-[#e6e6e7] peer-checked:bg-black peer-checked:text-white border-transparent'
                                    : 'bg-[#eeeeef] border-transparent peer-checked:bg-white peer-checked:border-black';
                            ?>
                                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[12px] font-semibold border-2 transition <?php echo $sel; ?>">
                                    <i class="inline-block w-3.5 h-3.5 rounded-full shrink-0 <?php echo $ui['color_style'] === 'filled' ? 'ring-1 ring-gray-400' : ''; ?>" style="<?php echo $dot; ?>"></i><?php echo e($v['label']); ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-5 py-1.5 rounded-full text-[12px] font-semibold bg-[#e6e6e7] hover:bg-[#d9d9db] peer-checked:bg-black peer-checked:text-white transition"><?php echo e($v['label']); ?></span>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endforeach; ?>

                <button type="submit" name="add_to_cart" value="1" <?php echo $outOfStock ? 'disabled' : ''; ?>
                        class="w-full flex items-center justify-center gap-2.5 bg-black text-white rounded-xl py-4 text-[13px] font-bold tracking-[0.1em] uppercase hover:bg-gray-800 active:scale-[.99] transition disabled:bg-gray-300 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-bag-shopping"></i> <?php echo e($ctaText); ?>
                </button>
                <?php if (!$outOfStock && (int)$p['stock'] <= LOW_STOCK_LIMIT): ?>
                    <p class="text-[12px] text-red-600 font-semibold mt-2.5"><i class="fa-solid fa-fire mr-1"></i>Sisa <?php echo (int)$p['stock']; ?> pcs &mdash; segera habis!</p>
                <?php endif; ?>
            </form>

            <!-- Description & Fit -->
            <section class="bg-paper rounded-2xl p-5 mt-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="<?php echo $upper ? 'text-[11px] font-bold tracking-[0.1em] uppercase' : 'font-head font-bold text-[16px]'; ?>">Description &amp; Fit</h2>
                    <?php if ($ui['icons']): ?><i class="fa-solid fa-shirt text-gray-400 text-sm"></i><?php endif; ?>
                </div>
                <p class="text-[13px] leading-relaxed text-gray-700"><?php echo nl2br(e($p['description'])); ?></p>
                <?php if ($ui['highlights']): ?>
                    <p class="mt-4 pt-3 border-t border-gray-200 text-[9px] tracking-[0.1em] uppercase text-gray-500">
                        <?php echo '&bull; ' . implode(' &nbsp;&bull; ', array_map('e', $ui['highlights'])); ?>
                    </p>
                <?php endif; ?>
            </section>

            <!-- Shipping -->
            <section class="bg-paper rounded-2xl p-5 mt-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="<?php echo $upper ? 'text-[11px] font-bold tracking-[0.1em] uppercase' : 'font-head font-bold text-[16px]'; ?>">Shipping</h2>
                    <?php if ($ui['icons']): ?><i class="fa-solid fa-truck text-gray-400 text-sm"></i><?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-x-5 gap-y-4">
                    <?php foreach ([
                        ['fa-percent', 'Discount', 'Disc -'],
                        ['fa-box-archive', 'Package', 'Regular Package'],
                        ['fa-calendar', 'Delivery Time', '3-4 Working Days'],
                        ['fa-truck-fast', 'Estimation Arrive', est_arrival()],
                    ] as [$ic, $k, $v]): ?>
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-9 h-9 shrink-0 rounded-lg bg-[#e4e4e5] flex items-center justify-center text-[13px]"><i class="fa-solid <?php echo $ic; ?>"></i></span>
                            <div class="min-w-0">
                                <p class="text-[10px] text-gray-500 uppercase tracking-wide"><?php echo $k; ?></p>
                                <p class="text-[12px] font-bold leading-tight"><?php echo e($v); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<?php if ($ui['marquee']):
    $items = explode('|', $ui['marquee']);
    if ($ui['rounded_marquee']) echo '<div class="max-w-[1440px] mx-auto px-5 md:px-8 mt-16"><div class="rounded-lg overflow-hidden">';
    else echo '<div class="mt-16">';
    ticker_scroll($items, 'py-2.5 text-[10px] md:text-[11px] font-bold tracking-[0.14em]', '•');
    echo $ui['rounded_marquee'] ? '</div></div>' : '</div>';
else: ?>
    <div class="h-14 md:h-20"></div>
<?php endif; ?>

<!-- ================= RATING & REVIEW ================= -->
<?php if ($sum['total'] > 0 || $canReview): ?>
<section id="reviews" class="max-w-[1440px] mx-auto px-5 md:px-8 mt-10">
    <div class="flex flex-wrap items-end justify-between gap-3 pb-4 border-b border-gray-200 mb-6">
        <h2 class="font-head font-extrabold text-[clamp(26px,3vw,36px)] tracking-tight leading-none">Rating &amp; Review
            <?php if ($sum['total'] > 0): ?><span class="font-sans font-normal text-[12px] text-gray-500 tracking-normal ml-1">(<?php echo (int)$sum['total']; ?> Ulasan)</span><?php endif; ?>
        </h2>
        <?php if ($sum['total'] > 0): ?><span class="inline-flex items-center gap-1.5 bg-paper rounded-full px-3.5 py-2 text-[10px] font-bold tracking-wide"><i class="fa-regular fa-circle-check"></i> 100% Pembeli Terverifikasi</span><?php endif; ?>
    </div>

    <?php if ($canReview): ?>
    <form method="post" class="bg-paper rounded-2xl p-5 md:p-6 mb-5">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="review">
        <p class="text-[10px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-3">Tulis Ulasan Anda</p>
        <style>.review-stars label{color:#d1d5db}.review-stars input:checked~label,.review-stars label:hover,.review-stars label:hover~label{color:#f5b301}</style>
        <div class="flex flex-row-reverse justify-end gap-1 mb-3 text-[24px] review-stars">
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="rt<?php echo $i; ?>" value="<?php echo $i; ?>" class="sr-only" <?php echo $i === 5 ? 'checked' : ''; ?>>
                <label for="rt<?php echo $i; ?>" class="cursor-pointer" aria-label="<?php echo $i; ?> bintang"><i class="fa-solid fa-star"></i></label>
            <?php endfor; ?>
        </div>
        <textarea name="comment" rows="3" minlength="10" maxlength="1000" required placeholder="Ceritakan bahan, ukuran, dan pengalaman Anda..." class="w-full bg-white rounded-xl px-4 py-3 text-[14px] outline-none focus:ring-2 focus:ring-black resize-none"></textarea>
        <button class="mt-3 bg-black text-white rounded-full px-8 py-2.5 text-[11px] font-bold tracking-[0.12em] uppercase hover:bg-gray-800 transition">Kirim Ulasan</button>
    </form>
    <?php endif; ?>

    <?php if ($sum['total'] > 0): ?>
    <div class="grid lg:grid-cols-[minmax(280px,360px)_1fr] gap-5 items-start">
        <!-- Ringkasan (dihitung dari pembelian nyata) -->
        <aside class="bg-paper rounded-2xl p-6 md:p-7 lg:sticky lg:top-24">
            <p class="text-[10px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-3">Ringkasan Rating</p>
            <div class="flex items-end gap-2">
                <span class="font-head font-extrabold text-[56px] leading-none tracking-tight"><?php echo e(number_format((float)$sum['avg'], 1)); ?></span>
                <span class="text-[16px] text-gray-500 pb-1.5">/ 5.0</span>
            </div>
            <div class="mt-3"><?php echo stars($sum['avg'], 'text-[15px]'); ?></div>
            <p class="text-[11px] text-gray-600 mt-2.5">Berdasarkan <?php echo (int)$sum['total']; ?> ulasan pembeli terverifikasi</p>

            <hr class="border-gray-200 my-5">
            <div class="space-y-2.5">
                <?php foreach ([5, 4, 3, 2, 1] as $st): $v = $pct($sum['c'][$st]); ?>
                    <div class="flex items-center gap-3 text-[11px]">
                        <span class="w-9 font-semibold text-gray-700 whitespace-nowrap"><?php echo $st; ?> <i class="fa-solid fa-star text-[9px]" style="color:#f5b301"></i></span>
                        <span class="flex-1 h-[6px] bg-[#e0e0e1] rounded-full overflow-hidden"><span class="block h-full bg-black rounded-full" style="width:<?php echo $v; ?>%"></span></span>
                        <span class="w-9 text-right font-bold"><?php echo $v; ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-white rounded-xl p-4 flex items-center gap-3 mt-5">
                <i class="fa-regular fa-thumbs-up text-xl"></i>
                <div class="min-w-0">
                    <p class="text-[13px] font-bold leading-tight"><?php echo (int)$sum['recommend']; ?>% Pembeli Merekomendasikan</p>
                    <p class="text-[11px] text-gray-500 mt-0.5"><?php echo (int)($sum['c'][4] + $sum['c'][5]); ?> dari <?php echo (int)$sum['total']; ?> pembeli merasa puas</p>
                </div>
            </div>
        </aside>

        <!-- Daftar ulasan (kartu seragam) -->
        <div class="min-w-0">
            <div class="space-y-3">
                <?php foreach ($reviews as $rv):
                    $meta = $rv['variant_info'] ? (($ui['review_meta'] ? $ui['review_meta'] . ': ' : '') . $rv['variant_info']) : '';
                ?>
                <article class="bg-paper rounded-2xl p-5 md:p-6">
                    <div class="flex items-start gap-3.5">
                        <span class="w-10 h-10 shrink-0 rounded-full bg-black text-white text-[11px] font-bold flex items-center justify-center"><?php echo e(initials($rv['reviewer_name'])); ?></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-[15px] leading-tight flex items-center gap-1.5 flex-wrap">
                                        <?php echo e($rv['reviewer_name']); ?>
                                        <i class="fa-solid fa-circle-check text-[12px]" title="Pembeli Terverifikasi"></i>
                                    </p>
                                    <div class="mt-1.5"><?php echo stars($rv['rating'], 'text-[11px]'); ?></div>
                                </div>
                                <span class="text-[11px] text-gray-500 shrink-0 pt-0.5"><?php echo e(date_short_pad($rv['created_at'])); ?></span>
                            </div>
                            <p class="text-[14px] leading-relaxed mt-3 text-gray-900"><?php echo e($rv['comment']); ?></p>
                            <?php if ($meta): ?><p class="inline-block mt-3 bg-white rounded-full px-3 py-1 text-[10px] font-semibold tracking-wide text-gray-600"><?php echo e($meta); ?></p><?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <?php if ($remaining > 0 && !$showMore): ?>
                <div class="text-center mt-6">
                    <a href="product.php?id=<?php echo $id; ?>&more=1#reviews" class="inline-block border border-gray-300 rounded-full px-8 py-2.5 text-[11px] font-bold tracking-[0.12em] uppercase hover:bg-black hover:text-white hover:border-black transition">Load more reviews (<?php echo $remaining; ?>)</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ================= COMPLETE THE LOOK ================= -->
<?php if ($related): ?>
<section class="max-w-[1440px] mx-auto px-5 md:px-8 mt-16">
    <div class="flex items-center justify-between mb-5">
        <h2 class="font-head font-extrabold text-[clamp(22px,2.4vw,30px)] tracking-tight">Complete The Look</h2>
        <a href="katalog.php?availability=any" class="text-[12px] font-bold tracking-[0.1em] uppercase underline underline-offset-4 hover:opacity-60">View All</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-5">
        <?php foreach ($related as $rp): ?>
        <article class="bg-paper rounded-2xl p-3 md:p-4 group relative">
            <a href="product.php?id=<?php echo (int)$rp['id']; ?>" class="block aspect-[3/4] rounded-xl overflow-hidden bg-[#e9e9ea]">
                <img src="<?php echo e(img($rp['image_url'])); ?>" alt="<?php echo e(product_card_name($rp)); ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-500">
            </a>
            <div class="flex items-end justify-between gap-2 pt-3 px-1">
                <a href="product.php?id=<?php echo (int)$rp['id']; ?>" class="min-w-0">
                    <h3 class="font-head font-bold text-[16px] md:text-[18px] leading-tight truncate"><?php echo e(($rp['checkout_name'] && $rp['id'] == 3) ? 'T-Shirt Heavy Cotton' : product_card_name($rp)); ?></h3>
                    <p class="text-[12px] text-gray-600 mt-0.5"><?php echo e(rp_dot($rp['price'])); ?></p>
                </a>
                <form action="keranjang.php" method="post" class="shrink-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="product_id" value="<?php echo (int)$rp['id']; ?>">
                    <input type="hidden" name="back" value="product.php?id=<?php echo $id; ?>">
                    <button name="add_to_cart" value="1" class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center hover:scale-110 transition" aria-label="Tambah ke keranjang"><i class="fa-solid fa-plus text-[13px]"></i></button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
store_footer('app');
page_end("<script>
(function(){
  var main=document.getElementById('mainImg');
  function setMain(src){ if(!src) return; main.src=src; document.querySelectorAll('.thumb').forEach(function(t){ t.classList.toggle('border-black', t.dataset.src===src); t.classList.toggle('border-transparent', t.dataset.src!==src); }); }
  document.querySelectorAll('.thumb').forEach(function(t){ t.addEventListener('click',function(){ setMain(t.dataset.src); }); });
  document.querySelectorAll('.opt-input').forEach(function(inp){
    inp.addEventListener('change',function(){
      var grp=inp.closest('.option-group'), out=grp.querySelector('.selected-value');
      if(out) out.textContent=inp.dataset.display;
      if(inp.dataset.image) setMain(inp.dataset.image);
    });
  });
})();
</script>");

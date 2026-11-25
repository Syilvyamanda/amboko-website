<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

// ---------- Parameter filter ----------
$q        = trim($_GET['q'] ?? '');
$catSlug  = $_GET['category'] ?? 'all';
$material = $_GET['material'] ?? '';
$avail    = $_GET['availability'] ?? 'in_stock';           // desain: "In Stock" aktif secara default
$sort     = $_GET['sort'] ?? 'popular';
$onlyWish = !empty($_GET['wishlist']);
$onlySale = !empty($_GET['sale']);

$bounds = $conn->query("SELECT MIN(price) mn, MAX(price) mx FROM products WHERE is_active = 1")->fetch_assoc();
$priceMin = (int)($bounds['mn'] ?? 0);
$priceMax = (int)($bounds['mx'] ?? 0);
$min = isset($_GET['min']) ? max($priceMin, (int)$_GET['min']) : $priceMin;
$max = isset($_GET['max']) ? min($priceMax, (int)$_GET['max']) : $priceMax;
if ($min > $max) [$min, $max] = [$max, $min];

$sorts = [
    'popular'    => ['Most Popular', 'p.popularity DESC, p.id DESC'],
    'new'        => ['Terbaru', 'p.id DESC'],
    'price_asc'  => ['Harga Terendah', 'p.price ASC'],
    'price_desc' => ['Harga Tertinggi', 'p.price DESC'],
];
if (!isset($sorts[$sort])) $sort = 'popular';

// ---------- Query ----------
$where = ['p.is_active = 1', 'p.price BETWEEN ? AND ?'];
$types = 'ii';
$args  = [$min, $max];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.short_name LIKE ? OR p.material LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssss'; array_push($args, $like, $like, $like, $like);
}
if ($catSlug === 'new') {
    $where[] = 'p.is_new = 1';
} elseif ($catSlug !== 'all' && $catSlug !== '') {
    $where[] = '(c.slug = ? OR pc.slug = ?)';
    $types .= 'ss'; array_push($args, $catSlug, $catSlug);
}
if ($material !== '') { $where[] = 'p.material = ?'; $types .= 's'; $args[] = $material; }
if (in_array($avail, ['in_stock', 'pre_order', 'limited'], true)) { $where[] = 'p.availability = ?'; $types .= 's'; $args[] = $avail; }
if ($onlySale) $where[] = 'p.sale_percent > 0';
if ($onlyWish) {
    if (!is_logged_in()) redirect('login.php?next=' . urlencode('katalog.php?wishlist=1'));
    $where[] = 'p.id IN (SELECT product_id FROM wishlists WHERE user_id = ' . (int)user_id() . ')';
}

$sql = "SELECT p.*, c.name AS category_name FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN categories pc ON pc.id = c.parent_id
        WHERE " . implode(' AND ', $where) . " ORDER BY " . $sorts[$sort][1];
$st = $conn->prepare($sql);
$st->bind_param($types, ...$args);
$st->execute();
$products = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$total = count($products);

$cats = $conn->query("SELECT name, slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$materials = $conn->query("SELECT DISTINCT material FROM products WHERE is_active = 1 AND material IS NOT NULL AND material <> '' ORDER BY FIELD(material,'Heavy Terry','Ripstop','Micro-Twill','Combed Cotton'), material")->fetch_all(MYSQLI_NUM);
$materials = array_column($materials, 0);
$wish = wishlist_ids($conn);

/** URL dengan parameter saat ini + perubahan */
function qs(array $change = []): string
{
    $cur = $_GET;
    foreach ($change as $k => $v) { if ($v === null) unset($cur[$k]); else $cur[$k] = $v; }
    return 'katalog.php' . ($cur ? '?' . http_build_query($cur) : '');
}

$chip = fn(bool $on) => $on ? 'bg-black text-white' : 'bg-[#d9d9d9] text-gray-800 hover:bg-[#c9c9c9]';

page_head('AMBOKO - Catalogue', ['font' => 'poppins', 'bodyClass' => 'bg-white']);
store_header('catalog', 'catalog', $conn);
?>

<main class="max-w-[1440px] mx-auto px-5 md:px-5 pb-6">
    <h1 class="font-display font-bold text-center text-[clamp(44px,5vw,76px)] leading-none pt-8 pb-8 md:pt-7 md:pb-11 tracking-tight">Catalogue</h1>

    <form id="filterForm" action="katalog.php" method="get">
        <?php foreach (['q' => $q, 'category' => $catSlug !== 'all' ? $catSlug : '', 'material' => $material, 'availability' => $_GET['availability'] ?? '', 'wishlist' => $onlyWish ? 1 : '', 'sale' => $onlySale ? 1 : ''] as $k => $v):
            if ($v !== '' && $v !== null): ?>
            <input type="hidden" name="<?php echo $k; ?>" value="<?php echo e($v); ?>">
        <?php endif; endforeach; ?>
    </form>

    <div>
        <!-- Judul + sort -->
        <div class="flex flex-wrap items-end justify-between gap-3 pb-2.5 border-b-[3px] border-[#e5e5e5]">
            <h2 class="font-sans font-bold text-2xl"><?php echo $onlyWish ? 'My Wishlist' : ($q !== '' ? 'Hasil untuk &ldquo;' . e($q) . '&rdquo;' : 'All Products'); ?></h2>
            <div class="flex items-center gap-4 md:gap-5">
                <span class="text-[13px] md:text-[15px] text-gray-500">Showing <?php echo $total; ?> Style<?php echo $total === 1 ? '' : 's'; ?></span>
                <div class="relative">
                    <select name="sort" form="filterForm" onchange="this.form.submit()" class="appearance-none bg-black text-white font-bold text-[13px] md:text-sm rounded-full pl-5 pr-11 py-2.5 outline-none cursor-pointer">
                        <?php foreach ($sorts as $k => [$label]): ?>
                            <option value="<?php echo $k; ?>" <?php echo $sort === $k ? 'selected' : ''; ?>>Sort by : <?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-white text-sm pointer-events-none"></i>
                </div>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-8 md:gap-6 pt-3">
            <!-- ============ SIDEBAR FILTER ============ -->
            <aside class="md:w-[27%] lg:w-[28%] shrink-0 text-[15px]">
                <p class="text-gray-500 mb-3 text-[15px]">Filter</p>

                <h3 class="font-bold mb-2.5">By Category</h3>
                <div class="flex flex-wrap gap-2 mb-10 max-w-[300px]">
                    <a href="<?php echo e(qs(['category' => null])); ?>" class="px-3.5 py-1 rounded-full text-[13px] font-medium <?php echo $chip($catSlug === 'all' || $catSlug === ''); ?>">All</a>
                    <?php foreach ($cats as $c): ?>
                        <a href="<?php echo e(qs(['category' => $c['slug']])); ?>" class="px-3.5 py-1 rounded-full text-[13px] font-medium <?php echo $chip($catSlug === $c['slug']); ?>"><?php echo e($c['name']); ?></a>
                    <?php endforeach; ?>
                    <a href="<?php echo e(qs(['category' => 'new', 'availability' => 'any'])); ?>" class="px-3.5 py-1 rounded-full text-[13px] font-medium <?php echo $chip($catSlug === 'new'); ?>">New Edition</a>
                </div>

                <h3 class="font-bold mb-2 pl-0.5">Price Range</h3>
                <p class="text-[13px] mb-3 pl-0.5"><span id="priceLabel"><?php echo e(rp_dot($min) . ' - ' . rp_dot($max)); ?></span></p>
                <div class="range-wrap max-w-[190px] mb-10 ml-1.5" data-min="<?php echo $priceMin; ?>" data-max="<?php echo $priceMax; ?>">
                    <div class="range-track"></div><div class="range-fill" id="rangeFill"></div>
                    <input type="range" form="filterForm" name="min" id="rMin" min="<?php echo $priceMin; ?>" max="<?php echo $priceMax; ?>" step="5000" value="<?php echo $min; ?>" aria-label="Harga minimum">
                    <input type="range" form="filterForm" name="max" id="rMax" min="<?php echo $priceMin; ?>" max="<?php echo $priceMax; ?>" step="5000" value="<?php echo $max; ?>" aria-label="Harga maksimum">
                </div>

                <h3 class="font-bold mb-2.5 pl-0.5">Material</h3>
                <div class="flex flex-wrap gap-2 mb-10 max-w-[300px]">
                    <?php foreach ($materials as $m): $on = $material === $m; ?>
                        <a href="<?php echo e(qs(['material' => $on ? null : $m])); ?>" class="px-3.5 py-1 rounded-full text-[13px] <?php echo $chip($on); ?>"><?php echo e($m); ?></a>
                    <?php endforeach; ?>
                </div>

                <h3 class="font-bold mb-2.5 pl-0.5">Availability</h3>
                <div class="flex flex-wrap gap-2 max-w-[300px]">
                    <?php foreach (['in_stock' => 'In Stock', 'pre_order' => 'Pre-Order', 'limited' => 'Limited Edition'] as $k => $label): $on = $avail === $k; ?>
                        <a href="<?php echo e(qs(['availability' => $on ? 'any' : $k])); ?>" class="px-3.5 py-1 rounded-full text-[13px] font-medium <?php echo $chip($on); ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- ============ GRID PRODUK ============ -->
            <section class="flex-1 min-w-0">
                <?php if (!$products): ?>
                    <div class="py-24 text-center text-gray-500">
                        <i class="fa-regular fa-face-frown text-4xl mb-4"></i>
                        <p class="font-semibold text-black">Belum ada produk yang cocok.</p>
                        <p class="text-sm mt-1">Coba ubah filter atau <a class="underline" href="katalog.php?availability=any">tampilkan semua produk</a>.</p>
                    </div>
                <?php else: ?>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-8 md:gap-y-9">
                    <?php foreach ($products as $p):
                        $isWish = isset($wish[(int)$p['id']]);
                        $name = product_card_name($p);
                    ?>
                    <article class="bg-[#d0cecd] rounded-xl p-2.5 md:p-3 pb-3 relative group transition hover:shadow-lg">
                        <button type="button" data-wish="<?php echo (int)$p['id']; ?>" data-csrf="<?php echo e(csrf_token()); ?>"
                                class="absolute top-4 right-4 md:top-5 md:right-5 z-10 text-[22px] drop-shadow <?php echo $isWish ? 'text-red-500' : 'text-white'; ?>" aria-label="Wishlist">
                            <i class="fa-solid fa-heart"></i>
                        </button>
                        <a href="product.php?id=<?php echo (int)$p['id']; ?>" class="block rounded-md overflow-hidden bg-white aspect-[3/4] relative">
                            <img src="<?php echo e(img($p['image_url'])); ?>" alt="<?php echo e($name); ?>" loading="lazy" class="w-full h-full object-cover object-top group-hover:scale-[1.03] transition duration-500">
                            <?php if ($p['sale_percent'] > 0): ?>
                                <span class="absolute left-2 bottom-2 bg-white/95 border border-black text-[11px] font-medium px-1.5 py-0.5 rounded-[3px]">-<?php echo (int)$p['sale_percent']; ?>%</span>
                            <?php endif; ?>
                            <?php if ($p['availability'] !== 'in_stock'): ?>
                                <span class="absolute left-2 top-2 bg-black text-white text-[10px] font-bold tracking-wide px-2 py-0.5 rounded-full uppercase"><?php echo $p['availability'] === 'pre_order' ? 'Pre-Order' : 'Limited'; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="flex items-end justify-between gap-2 px-1 pt-2.5">
                            <div class="min-w-0">
                                <a href="product.php?id=<?php echo (int)$p['id']; ?>"><h3 class="font-bold text-[17px] md:text-[19px] leading-tight truncate"><?php echo e($name); ?></h3></a>
                                <p class="text-[13px] text-gray-800 mt-1 ml-0.5"><?php echo e(rp_dot($p['price'])); ?></p>
                            </div>
                            <form action="keranjang.php" method="post" class="shrink-0">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                <input type="hidden" name="back" value="<?php echo e('katalog.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '')); ?>">
                                <button type="submit" name="add_to_cart" value="1" class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-black text-white flex items-center justify-center text-lg hover:scale-110 active:scale-95 transition" aria-label="Tambah ke keranjang">
                                    <i class="fa-solid fa-plus text-[15px]"></i>
                                </button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php
store_footer('plain');
page_end("<script>
(function(){
  var a=document.getElementById('rMin'), b=document.getElementById('rMax'), fill=document.getElementById('rangeFill'), lab=document.getElementById('priceLabel');
  if(!a||!b) return;
  var lo=+a.min, hi=+a.max, t;
  function fmt(n){return 'Rp.'+n.toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');}
  function upd(src){
    var x=+a.value, y=+b.value;
    if(x>y){ if(src===a){a.value=y;x=y;} else {b.value=x;y=x;} }
    var w=hi-lo||1;
    fill.style.left=((x-lo)/w*100)+'%'; fill.style.width=((y-x)/w*100)+'%';
    lab.textContent=fmt(x)+' - '+fmt(y);
  }
  [a,b].forEach(function(el){
    el.addEventListener('input',function(){upd(el);});
    el.addEventListener('change',function(){clearTimeout(t);t=setTimeout(function(){document.getElementById('filterForm').submit();},250);});
  });
  upd();
})();
</script>");

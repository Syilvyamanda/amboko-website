<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

// Kartu miring: produk yang ditandai home_sort (urut)
$featured = [];
$r = $conn->query("SELECT * FROM products WHERE is_active = 1 AND home_sort IS NOT NULL ORDER BY home_sort LIMIT 4");
while ($row = $r->fetch_assoc()) $featured[] = $row;

$cats = [];
$r = $conn->query("SELECT name, slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order");
while ($row = $r->fetch_assoc()) $cats[] = $row;

page_head('AMBOKO - Home', ['font' => 'poppins', 'bodyClass' => 'bg-white']);
store_header('home', 'home', $conn);
?>

<!-- HERO -->
<section class="relative bg-black text-white overflow-hidden h-[62vh] min-h-[360px] md:h-[min(52vw,760px)]">
    <img src="asset/catalog.jpg" alt="" class="absolute inset-0 w-full h-full object-cover object-[50%_64%] blur-[1px]">
    <div class="absolute inset-0 bg-black/55"></div>
    <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-[7%] flex flex-col justify-center">
        <h1 class="leading-[1.05] text-[clamp(28px,5.4vw,78px)]">
            <span class="block font-poppins font-medium">&ldquo;Built for <span class="font-serif font-black">Motion,</span></span>
            <span class="block font-poppins font-medium ml-[8%] md:ml-[20%]">worn for <span class="font-serif font-black">Statement.</span>&rdquo;</span>
        </h1>
    </div>
</section>

<?php ticker_scroll(array_fill(0, 6, 'Popular this month'), 'py-2 text-base md:text-xl font-medium'); ?>

<!-- KATEGORI -->
<section class="px-4 md:px-[5.5%] py-10 md:py-14">
    <div class="border-2 border-black grid md:grid-cols-2 bg-white">
        <a href="katalog.php?availability=any&sale=1" class="block p-4 md:p-5 md:border-r-2 border-black group" aria-label="Pre-Black Friday Sale">
            <div class="overflow-hidden h-full">
                <img src="asset/16.jpg" alt="Pre-Black Friday Sale - 10-15% off sale items" class="w-full h-full min-h-[380px] max-h-[680px] object-cover object-top group-hover:scale-[1.02] transition duration-500">
            </div>
        </a>
        <div class="flex flex-col">
            <?php foreach ($cats as $c): ?>
                <a href="katalog.php?category=<?php echo e($c['slug']); ?>&availability=any" class="flex-1 flex items-center justify-between px-5 md:px-8 py-5 md:py-6 border-b-2 border-black hover:bg-black hover:text-white transition group">
                    <span class="font-semibold text-[clamp(20px,3.1vw,44px)] tracking-tight uppercase"><?php echo e($c['name']); ?></span>
                    <span class="text-[clamp(26px,3.4vw,52px)] leading-none font-light" aria-hidden="true">&#10230;</span>
                </a>
            <?php endforeach; ?>
            <a href="katalog.php?category=new&availability=any" class="flex-1 flex items-center justify-between px-5 md:px-8 py-5 md:py-6 hover:bg-black hover:text-white transition">
                <span class="font-semibold text-[clamp(20px,3.1vw,44px)] tracking-tight uppercase">New Edition</span>
                <span class="text-[clamp(26px,3.4vw,52px)] leading-none font-light" aria-hidden="true">&#10230;</span>
            </a>
        </div>
    </div>
</section>

<?php ticker_scroll(array_fill(0, 6, 'Sale up to -15%'), 'py-2 text-base md:text-xl font-medium'); ?>

<!-- PRODUK SALE (kartu miring) -->
<section class="px-4 md:px-6 py-12 md:py-16 overflow-hidden">
    <div class="max-w-[1440px] mx-auto grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-12 items-start">
        <?php
        $tilts = ['tilt-a lg:mt-2', 'tilt-b lg:-mt-6', 'tilt-c lg:mt-4', 'tilt-d lg:-mt-2'];
        foreach ($featured as $i => $p):
            $label = product_card_name($p);
        ?>
            <a href="product.php?id=<?php echo (int)$p['id']; ?>" class="tilt-card <?php echo $tilts[$i % 4]; ?> block text-center group">
                <div class="relative border-2 border-black bg-white p-2 md:p-3">
                    <?php if ($p['sale_percent'] > 0): ?>
                        <span class="absolute -top-3 right-5 md:right-8 z-10 bg-white border border-black rounded-[4px] text-[11px] md:text-xs font-medium px-2 py-0.5 -rotate-3">-<?php echo (int)$p['sale_percent']; ?>%</span>
                    <?php endif; ?>
                    <div class="aspect-[4/5] bg-white flex items-center justify-center overflow-hidden">
                        <img src="<?php echo e(img($p['home_image_url'] ?: $p['image_url'])); ?>" alt="<?php echo e($label); ?>" class="w-full h-full object-contain">
                    </div>
                </div>
                <p class="mt-3 font-bold text-base md:text-lg"><?php echo e($label); ?></p>
                <p class="text-sm md:text-lg font-normal"><?php echo e(rp_dot($p['price'])); ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php ticker_scroll(array_fill(0, 6, 'Blog and News'), 'py-1.5 text-sm md:text-base font-medium'); ?>

<!-- MAGAZINE -->
<section class="py-10 md:py-14 overflow-hidden">
    <h2 class="font-poppins font-normal text-[clamp(44px,6vw,88px)] leading-none px-6 md:px-[3%] mb-4 md:mb-6">Magazine</h2>
    <div class="relative">
        <div id="magTrack" class="flex gap-3 md:gap-4 overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth pl-6 md:pl-[3%] pr-6 md:pr-[3%]">
            <?php foreach (['Magazine3.jpg' => 'Celana pendek abu-abu dengan list hitam', 'Magazine4.jpg' => 'Rak pakaian koleksi AMBOKO', 'Magazine2.jpg' => 'Tumpukan hoodie dan sweatpants AMBOKO', 'Magazine1.jpg' => 'Celana pendek abu-abu di rak besi'] as $f => $alt): ?>
                <figure class="snap-start shrink-0 w-[62%] sm:w-[40%] md:w-[28%] lg:w-[26%] aspect-[3/4] rounded-2xl overflow-hidden bg-[#e9e9ea]">
                    <img src="asset/<?php echo e($f); ?>" alt="<?php echo e($alt); ?>" loading="lazy" class="w-full h-full object-cover">
                </figure>
            <?php endforeach; ?>
        </div>
        <button type="button" id="magPrev" class="absolute left-2 md:left-5 top-1/2 -translate-y-1/2 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/90 shadow-md flex items-center justify-center hover:bg-white transition" aria-label="Sebelumnya"><i class="fa-solid fa-chevron-left text-sm"></i></button>
        <button type="button" id="magNext" class="absolute right-2 md:right-5 top-1/2 -translate-y-1/2 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/90 shadow-md flex items-center justify-center hover:bg-white transition" aria-label="Berikutnya"><i class="fa-solid fa-chevron-right text-sm"></i></button>
    </div>
</section>

<?php
store_footer('plain');
page_end();

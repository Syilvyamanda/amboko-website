<?php
// =====================================================================
//  AMBOKO ERP - Layout toko (header / ticker / footer)
//  Varian header mengikuti desain:
//    'home'    -> logo gambar, menu kanan, kotak cari kecil (Desktop Home)
//    'catalog' -> logo gambar, "Home" di tengah (Catalogue)
//    'app'     -> logo kotak biru + pill pencarian (Produk / Keranjang / Checkout / Konfirmasi)
// =====================================================================

/**
 * @param array $o  font: 'dm'|'poppins', bodyClass: string, noindex: bool
 */
function page_head(string $title, array $o = []): void
{
    $font = $o['font'] ?? 'dm';
    $bodyClass = $o['bodyClass'] ?? 'bg-white';
    $fontClass = $font === 'poppins' ? 'font-poppins' : 'font-sans';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?></title>
    <link rel="icon" href="asset/logo.JPG">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,800;0,900;1,800;1,900&family=Poppins:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: {
                    sans: ['"DM Sans"', 'system-ui', 'sans-serif'],
                    head: ['"Bricolage Grotesque"', '"DM Sans"', 'sans-serif'],
                    display: ['Syne', '"DM Sans"', 'sans-serif'],
                    serif: ['"Playfair Display"', 'Georgia', 'serif'],
                    poppins: ['Poppins', 'system-ui', 'sans-serif']
                },
                colors: { brand: '#0c82c5', paper: '#f2f2f3', ink: '#0a0a0a' }
            } }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/amboko.css" rel="stylesheet">
</head>
<body class="<?php echo e($fontClass . ' ' . $bodyClass); ?> text-ink antialiased">
<?php
}

function page_end(string $extraScript = ''): void
{
    ?>
<script src="js/amboko.js"></script>
<?php echo $extraScript; ?>
</body>
</html>
<?php
}

/** Toast untuk pesan flash (sukses / error) */
function render_flash(): void
{
    $msgs = flash_pull();
    if (!$msgs) return;
    echo '<div id="toasts" class="fixed top-20 right-4 z-[60] space-y-2 w-[min(92vw,360px)]">';
    foreach ($msgs as $m) {
        $ok = $m['type'] === 'success';
        echo '<div class="toast flex items-start gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-lg ' .
            ($ok ? 'bg-black text-white' : 'bg-red-600 text-white') . '">' .
            '<i class="fa-solid ' . ($ok ? 'fa-circle-check' : 'fa-circle-exclamation') . ' mt-0.5"></i><span>' . e($m['msg']) . '</span></div>';
    }
    echo '</div>';
}

function search_box(string $variant): void
{
    $q = $_GET['q'] ?? '';
    if ($variant === 'app') {
        echo '<form action="katalog.php" method="get" class="search-form flex-1 max-w-[520px] mx-6 hidden md:block">' .
            '<label class="flex items-center gap-2 bg-paper rounded-full px-4 py-2.5 text-gray-500">' .
            '<i class="fa-solid fa-magnifying-glass text-sm"></i>' .
            '<input type="text" name="q" value="' . e($q) . '" placeholder="Search collections, garments..." class="flex-1 bg-transparent outline-none text-[13px] text-gray-800 placeholder-gray-500 min-w-0">' .
            '<button type="button" class="search-clear text-gray-500 hover:text-black" aria-label="Hapus"><i class="fa-solid fa-xmark text-sm"></i></button>' .
            '</label></form>';
    } else {
        echo '<form action="katalog.php" method="get" class="search-form hidden sm:block">' .
            '<label class="flex items-center gap-1.5 border border-gray-400 rounded-[4px] px-2 py-1 w-[150px] lg:w-[190px] bg-white">' .
            '<i class="fa-solid fa-magnifying-glass text-[10px]"></i>' .
            '<input type="text" name="q" value="' . e($q) . '" class="flex-1 min-w-0 outline-none text-xs bg-transparent">' .
            '<button type="button" class="search-clear text-[10px]" aria-label="Hapus"><i class="fa-solid fa-xmark"></i></button>' .
            '</label></form>';
    }
}

function header_icons(string $variant, mysqli $conn): void
{
    $count = cart_count();
    $logged = is_logged_in();
    $name = $_SESSION['name'] ?? '';
    $app = $variant === 'app';
    $bagCls = $app ? 'text-[17px]' : 'text-[22px]';
    $userCls = $app ? 'w-8 h-8 text-[12px]' : 'w-9 h-9 text-[15px]';

    echo '<a href="keranjang.php" class="relative inline-flex items-center justify-center ' . $bagCls . '" aria-label="Keranjang">' .
        '<i class="fa-solid fa-bag-shopping"></i>';
    if ($count > 0) {
        echo '<span class="absolute -top-2 -right-2.5 min-w-[15px] h-[15px] px-1 rounded-full bg-black text-white text-[9px] font-bold flex items-center justify-center ring-2 ring-white">' . $count . '</span>';
    }
    echo '</a>';

    if (!$logged) {
        echo '<a href="login.php" class="inline-flex items-center justify-center rounded-full bg-black text-white ' . $userCls . '" aria-label="Login"><i class="fa-solid fa-user"></i></a>';
        return;
    }
    echo '<div class="relative" id="user-menu">' .
        '<button type="button" id="user-menu-btn" class="inline-flex items-center justify-center rounded-full bg-black text-white ' . $userCls . '" aria-label="Akun"><i class="fa-solid fa-user"></i></button>' .
        '<div id="user-menu-panel" class="hidden absolute right-0 mt-3 w-56 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden z-50 text-sm">' .
        '<div class="px-4 py-3 border-b border-gray-100"><p class="text-xs text-gray-500">Masuk sebagai</p><p class="font-bold truncate">' . e($name) . '</p></div>';
    if (is_admin()) {
        echo '<a class="block px-4 py-2.5 hover:bg-paper" href="dashboard.php"><i class="fa-solid fa-gauge w-5"></i> Dashboard Admin</a>';
    }
    echo '<a class="block px-4 py-2.5 hover:bg-paper" href="pesanan.php"><i class="fa-solid fa-box w-5"></i> Pesanan Saya</a>' .
        '<a class="block px-4 py-2.5 hover:bg-paper" href="katalog.php?wishlist=1"><i class="fa-regular fa-heart w-5"></i> Wishlist</a>' .
        '<a class="block px-4 py-2.5 hover:bg-paper text-red-600 border-t border-gray-100" href="logout.php"><i class="fa-solid fa-right-from-bracket w-5"></i> Logout</a>' .
        '</div></div>';
}

/**
 * @param string $variant 'home' | 'catalog' | 'app'
 * @param string $active  'home' | 'catalog' | ''
 */
function store_header(string $variant, string $active, mysqli $conn): void
{
    if ($variant === 'app') {
        $nav = fn($href, $label, $key) => '<a href="' . $href . '" class="text-[13px] font-semibold tracking-wide uppercase ' . ($active === $key ? 'text-black' : 'text-gray-800 hover:text-gray-500') . ' transition">' . $label . '</a>';
        ?>
<header class="bg-white sticky top-0 z-40 border-b border-gray-100">
    <div class="max-w-[1440px] mx-auto flex items-center justify-between px-5 md:px-8 h-16">
        <div class="flex items-center gap-8">
            <a href="index.php" class="flex items-center gap-2" aria-label="AMBOKO">
                <span class="inline-block w-3 h-3 bg-brand"></span>
                <span class="font-head font-extrabold text-[22px] tracking-tight leading-none">AMBOKO</span>
            </a>
            <nav class="hidden sm:flex items-center gap-6">
                <?php echo $nav('index.php', 'Home', 'home') . $nav('katalog.php', 'Catalogue', 'catalog'); ?>
            </nav>
        </div>
        <?php search_box('app'); ?>
        <div class="flex items-center gap-5"><?php header_icons('app', $conn); ?></div>
    </div>
</header>
<?php
    } elseif ($variant === 'catalog') {
        ?>
<header class="bg-white relative z-40">
    <div class="max-w-[1440px] mx-auto grid grid-cols-[auto_1fr_auto] items-center px-5 md:px-8 h-16 gap-4">
        <a href="index.php" aria-label="AMBOKO"><img src="asset/logo.JPG" alt="AMBOKO" class="h-6 md:h-7 w-auto"></a>
        <nav class="text-center">
            <a href="index.php" class="font-display font-bold text-xl md:text-2xl">Home</a>
        </nav>
        <div class="flex items-center gap-4"><?php search_box('catalog'); header_icons('catalog', $conn); ?></div>
    </div>
</header>
<?php
    } else { // home
        ?>
<header class="bg-white relative z-40">
    <div class="flex items-center justify-between px-4 md:px-4 h-[60px]">
        <a href="index.php" aria-label="AMBOKO"><img src="asset/logo.JPG" alt="AMBOKO" class="h-6 md:h-7 w-auto"></a>
        <div class="flex items-center gap-3 md:gap-4">
            <nav class="flex items-center gap-4 font-display font-bold text-lg md:text-2xl">
                <a href="index.php" class="hover:opacity-60 transition">Home</a>
                <a href="katalog.php" class="hover:opacity-60 transition">Catalogue</a>
            </nav>
            <?php search_box('home'); header_icons('home', $conn); ?>
        </div>
    </div>
</header>
<?php
    }
    render_flash();
}

/** Bar hitam berjalan (marquee) – dipakai di Home & halaman produk. */
function ticker_scroll(array $items, string $extra = '', string $dot = '●'): void
{
    $group = '';
    foreach ($items as $t) {
        $group .= '<span class="mx-5">' . e($t) . '</span><span class="mx-1 text-[0.6em] align-middle">' . $dot . '</span>';
    }
    echo '<div class="marquee bg-black text-white ' . $extra . '"><div class="marquee__track">' .
        '<div class="marquee__group">' . $group . '</div><div class="marquee__group" aria-hidden="true">' . $group . '</div></div></div>';
}

/** Bar hitam statis kecil (keranjang / checkout / konfirmasi). */
function ticker_static(array $items, bool $blueDots = true): void
{
    echo '<div class="bg-black text-white overflow-hidden"><div class="max-w-[1440px] mx-auto px-5 md:px-8 py-1.5 flex items-center justify-between gap-6 whitespace-nowrap overflow-x-auto text-[10px] md:text-[11px] font-bold tracking-[0.12em] uppercase no-scrollbar">';
    foreach ($items as $i => $t) {
        if ($t === '·') {
            echo '<span class="inline-block w-1.5 h-1.5 rounded-full ' . ($blueDots ? 'bg-brand' : 'bg-white') . ' shrink-0"></span>';
        } else {
            echo '<span>' . e($t) . '</span>';
        }
    }
    echo '</div></div>';
}

function store_footer(string $variant = 'app'): void
{
    $links = [
        'AMBOKO'     => [['About Us', 'info.php?p=about'], ['Instagram', INSTAGRAM_URL], ['TikTok', TIKTOK_URL]],
        'ASSISTANCE' => [['Contact us', 'info.php?p=contact'], ['FAQ', 'info.php?p=faq'], ['Shipping Info', 'info.php?p=shipping'], ['Order Tracking', 'lacak.php']],
        'LEGAL'      => [['Syarat & Ketentuan', 'info.php?p=terms'], ['Kebijakan Privasi', 'info.php?p=privacy']],
    ];
    if ($variant === 'plain') { // Home & Catalogue (3 kolom, Poppins)
        echo '<footer class="bg-black text-white mt-16"><div class="max-w-[1440px] mx-auto px-6 md:px-10 pt-10 pb-8 grid grid-cols-2 sm:grid-cols-3 gap-8 md:flex md:gap-24 text-sm">';
        foreach ($links as $h => $items) {
            echo '<div><h4 class="font-medium mb-3 tracking-wide">' . $h . '</h4><ul class="space-y-1.5">';
            foreach ($items as [$label, $href]) {
                $ext = str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '';
                echo '<li><a class="font-medium hover:text-gray-300" href="' . e($href) . '"' . $ext . '>' . e($label) . '</a></li>';
            }
            echo '</ul></div>';
        }
        echo '</div><p class="text-center text-xs font-bold py-6 tracking-wide">@2026 AMBOKO. ALL RIGHTS RESERVED</p></footer>';
        return;
    }
    echo '<footer class="bg-[#050505] text-white mt-20"><div class="max-w-[1440px] mx-auto px-5 md:px-8 pt-10 pb-8">' .
        '<div class="grid grid-cols-2 md:grid-cols-4 gap-10 text-[13px]">' .
        '<div class="col-span-2 md:col-span-1"><div class="flex items-center gap-2 mb-5"><span class="inline-block w-3 h-3 bg-brand"></span><span class="font-head font-extrabold text-[22px] tracking-tight leading-none">AMBOKO</span></div>' .
        '<p class="text-gray-300 leading-relaxed max-w-[240px] text-[12px]">Contemporary urban streetwear rooted in utilitarian minimalism and architectural silhouettes.</p></div>';
    foreach ($links as $h => $items) {
        echo '<div><h4 class="font-bold text-[12px] tracking-[0.12em] mb-4">' . $h . '</h4><ul class="space-y-2.5 text-gray-300 text-[12px]">';
        foreach ($items as [$label, $href]) {
            $ext = str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '';
            echo '<li><a class="hover:text-white transition" href="' . e($href) . '"' . $ext . '>' . e($label) . '</a></li>';
        }
        echo '</ul></div>';
    }
    echo '</div><p class="text-center text-[10px] font-bold tracking-[0.14em] text-gray-300 mt-16">@2026 AMBOKO. ALL RIGHTS RESERVED</p></div></footer>';
}

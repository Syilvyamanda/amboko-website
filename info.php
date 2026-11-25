<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

$pages = [
    'about'    => ['About Us', ['AMBOKO adalah brand streetwear urban yang berakar pada minimalisme utilitarian dan siluet arsitektural, menghadirkan jaket, kaos, serta celana pendek dan panjang yang nyaman dipakai di rumah maupun beraktivitas di luar.', 'Setiap produk dibuat dari bahan berkualitas ekspor garmen yang tebal, lembut, dan tidak mudah menerawang, dengan jahitan rapi, kuat, dan aman, serta finishing bersih yang tahan pemakaian dan pencucian. Desainnya sederhana, tegas, dan mudah dipadupadankan.']],
    'contact'  => ['Contact us', ['Tim Customer Care kami siap membantu setiap hari.', 'Email: care@amboko.com  |  WhatsApp: 0812-0000-0000  |  Jam layanan: 09.00 - 21.00 WIB.']],
    'faq'      => ['FAQ', ['Berapa lama pengiriman? Reguler 2-3 hari kerja, Express Next Day 1 hari kerja.', 'Apakah bisa tukar ukuran? Ya, tukar ukuran maksimal 3 hari sejak barang diterima, kondisi belum dicuci dan tag utuh.', 'Metode bayar apa saja? QRIS, Virtual Account (BCA, Mandiri, BNI, BRI, Permata), dan kartu kredit/debit.']],
    'shipping' => ['Shipping Info', ['Gratis ongkir reguler untuk belanja minimal ' . 'Rp200.000. Di bawah itu ongkos kirim reguler Rp25.000.', 'Express Next Day dikenakan tambahan Rp15.000. Pesanan dikirim (dispatch) maksimal 24 jam setelah pembayaran terverifikasi.']],
    'size'     => ['Size Guide', ['All Size cocok untuk lingkar badan/pinggang kurang lebih 90-110 cm. Untuk ukuran S, M, L, XL gunakan acuan tinggi & berat badan di ulasan pembeli, atau hubungi Customer Care.', 'Potongan loose/boxy: pilih satu ukuran lebih kecil jika menginginkan tampilan lebih pas badan.']],
    'terms'    => ['Syarat & Ketentuan', ['Ringkasan ketentuan berbelanja dan kepemilikan rilisan AMBOKO.']],
    'privacy'  => ['Kebijakan Privasi', ['Kami hanya menyimpan data yang diperlukan untuk memproses pesanan (nama, kontak, alamat).', 'Data tidak dijual kepada pihak ketiga. Kata sandi disimpan dalam bentuk hash terenkripsi.']],
];
$key = $_GET['p'] ?? 'about';
if (!isset($pages[$key])) $key = 'about';
[$title, $paras] = $pages[$key];

/* ------------------------------------------------------------------
   Halaman dengan desain khusus: about, contact, faq, terms, privacy.
   Halaman lain (shipping, size) tetap memakai template teks sederhana.
------------------------------------------------------------------- */
$custom = ['about', 'contact', 'faq', 'terms', 'privacy'];

if (in_array($key, $custom, true)) {
    page_head($title . ' - AMBOKO', ['bodyClass' => 'bg-[#fafafa]']);
    store_header('app', '', $conn);

    if ($key === 'about'):
        // Bagian KELOMPOK: ubah nama & peran anggota di sini.
        $team = [
            ['Syilvani Amanda', 'Project Manager & Frontend'],
            ['Mushthafa', 'Backend'],
            ['Sekar Ayu Namira', 'Sekretaris'],
            ['Diva Nur Aulia', 'Anggota Tim'],
        ];
        $pillars = [
            ['01', 'Bahan Kelas Ekspor', "Kain tebal dan lembut, tidak menerawang.\nNyaman dipakai seharian, jatuh rapi di badan."],
            ['02', 'Jahitan Kuat & Rapi', "Dari kerah sampai kelim dikerjakan teliti.\nAman, awet, dan tahan dicuci berulang kali."],
            ['03', 'Nyaman Dipakai Di Mana Saja', "Dari santai di rumah sampai nongkrong di luar.\nSimpel, rapi, dan tetap bergaya."],
        ];
?>
<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-10 md:pt-14 pb-12 md:pb-16">
    <!-- Judul + deskripsi -->
    <section class="grid lg:grid-cols-2 gap-8 lg:gap-14 items-start">
        <h1 class="font-head font-extrabold uppercase tracking-[-0.045em] leading-[0.84] text-[clamp(84px,14vw,200px)]">About<br>Us</h1>
        <div class="space-y-6 text-[clamp(18px,1.75vw,26px)] leading-[1.3] text-black lg:pt-3">
            <?php foreach ($paras as $p): ?><p><?php echo preg_replace('/^AMBOKO\b/', '<b class="font-bold">AMBOKO</b>', e($p)); ?></p><?php endforeach; ?>
        </div>
    </section>

    <!-- Pilar -->
    <section class="mt-12 md:mt-16">
        <p class="text-[11px] font-bold tracking-[0.14em] uppercase text-gray-500 mb-4">Yang Kami Pegang</p>
        <div class="grid md:grid-cols-3 gap-4 md:gap-5">
            <?php foreach ($pillars as [$no, $h, $t]): ?>
                <article class="bg-[#f3f3f4] rounded-2xl p-6 md:p-7">
                    <p class="font-head font-extrabold text-[30px] leading-none tracking-tight"><?php echo e($no); ?></p>
                    <h2 class="font-head font-bold text-[20px] mt-5 leading-tight"><?php echo e($h); ?></h2>
                    <p class="text-[13px] leading-relaxed text-gray-600 mt-2"><?php echo nl2br(e($t)); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Kelompok -->
    <section class="mt-12 md:mt-16">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <h2 class="font-head font-extrabold uppercase tracking-[-0.04em] text-[clamp(32px,4vw,54px)] leading-none">Kelompok</h2>
            <p class="text-[11px] font-bold tracking-[0.14em] uppercase text-gray-500">Tim Website AMBOKO</p>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
            <?php foreach ($team as [$name, $role]): ?>
                <article class="bg-white rounded-2xl p-5 md:p-6 shadow-[0_1px_8px_rgba(0,0,0,.06)]">
                    <span class="w-12 h-12 rounded-full bg-black text-white font-bold text-[13px] flex items-center justify-center"><?php echo e(initials($name)); ?></span>
                    <h3 class="font-head font-bold text-[17px] leading-tight mt-4"><?php echo e($name); ?></h3>
                    <p class="text-[12px] text-gray-500 mt-1"><?php echo e($role); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php
    elseif ($key === 'contact'):
?>
<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-8 pb-10">
    <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] md:text-[11px] font-bold tracking-[0.12em] uppercase text-gray-700">
        <span>Arc / Division : Inquiry &amp; Visits</span>
        <span>Loc. ID : Depok &mdash; 16454</span>
    </div>

    <h1 class="mt-14 md:mt-20 font-head font-extrabold uppercase tracking-[-0.04em] leading-[0.9] text-[clamp(56px,12.5vw,190px)] inline-block border-b-[6px] border-black pb-1 md:pb-2">Contact Us</h1>

    <div class="mt-10 md:mt-14 grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-5 items-start">
        <div class="grid sm:grid-cols-2 lg:contents gap-5">
            <!-- Studio -->
            <section class="bg-[#f3f3f4] rounded-2xl p-6 md:p-7 flex flex-col min-h-[280px] lg:h-[300px]">
                <p class="text-[10px] font-bold tracking-[0.12em] uppercase underline underline-offset-2 text-gray-600">Studio / Atelier</p>
                <h2 class="font-head font-bold text-[20px] uppercase underline underline-offset-4 mt-3">Amboko Atelier</h2>
                <a href="<?php echo e(store_maps_link()); ?>" target="_blank" rel="noopener" class="text-[15px] leading-snug text-gray-700 mt-2 hover:text-black hover:underline underline-offset-4 transition" title="Buka di Google Maps"><?php echo implode('<br>', array_map('e', address_lines(site_setting('address', DEFAULT_STORE_ADDRESS)))); ?> <i class="fa-solid fa-location-arrow text-[11px] ml-0.5"></i></a>
                <p class="text-[10px] font-bold tracking-[0.1em] uppercase text-gray-700 mt-2">Studio &amp; Creative Space</p>
                <div class="mt-auto pt-6">
                    <p class="text-[10px] font-bold tracking-[0.12em] uppercase underline underline-offset-2 text-gray-600">Direct Line / WA</p>
                    <a href="https://wa.me/6281289203301" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-bold text-[18px] underline underline-offset-4 mt-1 hover:opacity-60 transition">+62 812-8920-3301 <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i></a>
                    <p class="text-[12px] text-gray-500 mt-0.5">Fast desk: 15&ndash;30 mins</p>
                </div>
            </section>

            <!-- Jam & Email -->
            <section class="bg-[#f3f3f4] rounded-2xl p-6 md:p-7 flex flex-col min-h-[280px] lg:h-[300px]">
                <p class="text-[10px] font-bold tracking-[0.12em] uppercase underline underline-offset-2 text-gray-600">Jam Operasional</p>
                <p class="font-bold text-[14px] uppercase underline underline-offset-4 mt-3">Senin &mdash; Jumat</p>
                <p class="text-[15px] text-gray-700">10:00 &mdash; 20:00 WIB</p>
                <p class="font-bold text-[14px] uppercase underline underline-offset-4 mt-3">Sabtu &mdash; Minggu</p>
                <p class="text-[15px] text-gray-700">12:00 &mdash; 21:00 WIB</p>
                <div class="mt-auto pt-6">
                    <p class="text-[10px] font-bold tracking-[0.12em] uppercase underline underline-offset-2 text-gray-600">Electronic Mail</p>
                    <a href="mailto:info@amboko.id" class="font-bold text-[16px] underline underline-offset-4 inline-block mt-1 hover:opacity-60 transition">info@amboko.id</a>
                    <p class="text-[15px] text-gray-700">custom@amboko.id</p>
                    <p class="text-[12px] text-gray-500">Press / Bespoke Orders</p>
                </div>
            </section>
        </div>

        <!-- Foto -->
        <div class="flex gap-4 lg:gap-5 items-start">
            <img src="asset/contact-atelier.jpg" alt="Rak pakaian di atelier AMBOKO" class="w-1/2 lg:w-[252px] aspect-[286/334] object-cover">
            <img src="asset/contact-sewing.jpg" alt="Mesin jahit di atelier AMBOKO" class="w-1/2 lg:w-[198px] aspect-[223/336] object-cover">
        </div>
    </div>

    <!-- Peta lokasi -->
    <section class="mt-8 md:mt-10 bg-[#f3f3f4] rounded-2xl p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-3 px-2 md:px-3 pt-2 pb-3">
            <p class="text-[10px] font-bold tracking-[0.12em] uppercase text-gray-600">Lokasi Atelier</p>
            <a href="<?php echo e(store_maps_link()); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-black text-white rounded-full px-5 py-2 text-[11px] font-bold tracking-[0.08em] uppercase hover:bg-gray-800 transition"><i class="fa-solid fa-diamond-turn-right"></i> Petunjuk Arah</a>
        </div>
        <div class="rounded-xl overflow-hidden bg-[#e4e4e5]">
            <iframe src="<?php echo e(store_maps_embed()); ?>" title="Peta lokasi AMBOKO Atelier" class="w-full h-[320px] md:h-[400px] border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
    </section>
</main>
<?php
    elseif ($key === 'faq'):
        $cats = ['all' => 'Semua', 'order' => 'Pesanan & Pembayaran', 'material' => 'Bahan & Ukuran', 'shipping' => 'Pengiriman', 'atelier' => 'Atelier Depok'];
        $faqs = [
            ['material', 'Bagaimana cara menentukan ukuran (size chart) yang pas?', 'Setiap rilisan AMBOKO dirancang dengan siluet utilitarian relaxed/loose fit kontemporer. Kami menyarankan untuk memeriksa panduan ukuran komprehensif pada deskripsi produk (lebar dada, bahu turun, dan panjang badan) atau berkonsultasi langsung via WhatsApp atelier kami untuk rekomendasi sizing yang spesifik.'],
            ['material', 'Dari mana asal material kain yang digunakan AMBOKO?', 'AMBOKO menggunakan bahan baku deadstock pilihan dengan bobot tebal yang nyaman dan jahitan rapi. Detail material tiap produk tercantum pada halaman deskripsi produk masing-masing.'],
            ['shipping', 'Berapa lama estimasi pengiriman pesanan?', 'Pengiriman reguler 2-3 hari kerja dan Express Next Day 1 hari kerja. Pesanan dikirim maksimal 24 jam setelah pembayaran terverifikasi. Gratis ongkir reguler untuk belanja minimal Rp200.000.'],
            ['atelier', 'Apakah bisa melakukan fitting langsung di studio atelier Depok?', 'Bisa. Atelier kami berada di Jl. Kemang No.46, RT.5/RW.24, Sukatani, Kec. Tapos, Kota Depok, Jawa Barat 16454. Buka Senin-Jumat pukul 10:00-20:00 WIB dan Sabtu-Minggu pukul 12:00-21:00 WIB. Kami sarankan membuat janji lewat WhatsApp terlebih dahulu.'],
            ['order', 'Bagaimana kebijakan retur atau penukaran barang?', 'Penukaran ukuran maksimal 3 hari sejak barang diterima, kondisi belum dicuci dan tag utuh.'],
            ['order', 'Metode pembayaran apa saja yang tersedia?', 'QRIS, Virtual Account (BCA, Mandiri, BNI, BRI, Permata), dan kartu kredit/debit.'],
        ];
?>
<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-12 md:pt-16 pb-10">
    <header class="text-center max-w-[640px] mx-auto">
        <p class="text-[10px] md:text-[11px] font-bold tracking-[0.22em] uppercase text-gray-500">Bantuan &amp; Informasi</p>
        <h1 class="font-head font-extrabold tracking-tight leading-[1.02] text-[clamp(38px,5vw,60px)] mt-4">Frequently Asked Questions</h1>
        <p class="text-[15px] leading-relaxed text-gray-600 mt-5">Temukan jawaban cepat seputar pemesanan, bahan baku deadstock, pengiriman, dan panduan ukuran atelier AMBOKO.</p>
    </header>

    <div class="flex flex-wrap justify-center gap-2.5 mt-9" id="faqFilters">
        <?php foreach ($cats as $k => $lbl): ?>
            <button type="button" data-filter="<?php echo e($k); ?>" class="faq-pill rounded-full px-4 py-2 text-[10px] md:text-[11px] font-bold tracking-[0.08em] uppercase transition <?php echo $k === 'all' ? 'bg-black text-white' : 'bg-[#eeeeef] text-gray-800 hover:bg-[#e2e2e3]'; ?>"><?php echo e($lbl); ?></button>
        <?php endforeach; ?>
    </div>

    <div class="max-w-[820px] mx-auto mt-10 space-y-3.5" id="faqList">
        <?php foreach ($faqs as $i => [$cat, $q, $a]): $open = $i === 0; ?>
        <div class="faq-item bg-[#f3f3f4] rounded-3xl" data-cat="<?php echo e($cat); ?>">
            <button type="button" class="faq-q w-full flex items-center justify-between gap-4 px-6 md:px-7 py-5 md:py-6 text-left" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
                <span class="font-bold text-[16px] md:text-[18px] leading-snug"><?php echo e($q); ?></span>
                <span class="w-10 h-10 rounded-full bg-[#e4e4e5] flex items-center justify-center shrink-0"><i class="faq-ico fa-solid <?php echo $open ? 'fa-xmark' : 'fa-plus'; ?> text-[13px]"></i></span>
            </button>
            <div class="faq-a <?php echo $open ? '' : 'hidden'; ?> px-6 md:px-7 pb-7 text-[15px] leading-relaxed text-gray-600"><?php echo e($a); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
<?php
    elseif ($key === 'terms'):
        $rules = [
            ['01', 'Pemesanan',   'Produk dirilis terbatas. Pesanan diproses setelah pembayaran terverifikasi.'],
            ['02', 'Pengiriman',  'Pengiriman dari Depok dalam 1–3 hari kerja dengan nomor resi otomatis.'],
            ['03', 'Penukaran',   'Penukaran ukuran maksimal 3 hari sejak barang diterima, kondisi belum dicuci dan tag utuh.'],
            ['04', 'Orisinalitas', 'Semua desain dan produk adalah karya orisinal AMBOKO.'],
        ];
?>
<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-10 md:pt-14 pb-10">
    <header class="pb-8 border-b border-gray-300">
        <h1 class="font-head font-extrabold uppercase tracking-[-0.02em] leading-none text-[clamp(34px,5vw,56px)]">Syarat &amp; Ketentuan</h1>
        <p class="text-[15px] md:text-[16px] text-gray-700 mt-4">Ringkasan ketentuan berbelanja dan kepemilikan rilisan AMBOKO.</p>
    </header>

    <div class="grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-8 lg:gap-16 items-start mt-10 md:mt-12">
        <div class="bg-[#e9e9ea] rounded-2xl overflow-hidden aspect-square">
            <img src="asset/syaratdanketentuan.jpg" alt="Hang tag AMBOKO" class="w-full h-full object-cover">
        </div>

        <ol class="divide-y divide-gray-300">
            <?php foreach ($rules as $i => [$no, $h, $t]): ?>
            <li class="<?php echo $i === 0 ? 'pb-6' : 'py-6'; ?>">
                <div class="flex items-baseline gap-3">
                    <span class="font-head font-extrabold text-[26px] md:text-[28px] leading-none"><?php echo e($no); ?></span>
                    <h2 class="font-bold text-[13px] md:text-[14px] uppercase tracking-[0.1em]"><?php echo e($h); ?></h2>
                </div>
                <p class="text-[14px] md:text-[15px] leading-relaxed text-gray-700 mt-3"><?php echo e($t); ?></p>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</main>
<?php
    else: // privacy
        $cards = [
            ['01', 'Data yang Dikumpulkan', 'Hanya mencakup informasi kontak, alamat pengiriman, dan riwayat pesanan untuk kelancaran transaksi.', ['Nama & Alamat', 'No. Telepon', 'Email Notifikasi'], 'fa-box-archive'],
            ['02', 'Penggunaan Informasi', 'Digunakan murni untuk pemrosesan order, pelacakan paket ekspedisi, serta pembaruan katalog eksklusif.', ['Fulfillment', 'Resi Ekspedisi', 'Drop Bulletins'], 'fa-truck'],
            ['03', 'Keamanan & Privasi', 'Data terenkripsi penuh dan tidak akan pernah dijual atau dibagikan ke pihak ketiga tanpa izin Anda.', ['256-Bit Encryption', 'No Third-Party Sale'], 'fa-shield-halved'],
            ['04', 'Kendali Pengguna', 'Anda berhak meminta pembaruan atau penghapusan data akun kapan saja melalui tim layanan kami.', ['Hak Akses', 'Hapus Rekam Jejak', 'Respon 1x24 Jam'], 'fa-user-gear'],
        ];
?>
<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-10 md:pt-12 pb-10">
    <header class="text-center max-w-[640px] mx-auto">
        <p class="text-[10px] md:text-[11px] font-bold tracking-[0.18em] uppercase text-gray-600">AMBOKO <span class="mx-2 text-gray-400">/</span> <span class="text-black">Legal &amp; Ethics</span></p>
        <h1 class="font-head font-extrabold uppercase tracking-[-0.03em] leading-none text-[clamp(40px,6vw,76px)] mt-3">Kebijakan Privasi</h1>
        <p class="text-[15px] leading-relaxed text-gray-600 mt-5">Komitmen kami menjaga keamanan data, transparansi transaksi, dan privasi Anda dengan integritas tanpa kompromi.</p>
    </header>
    <hr class="border-gray-200 mt-9 mb-9 md:mb-12">

    <div class="grid md:grid-cols-2 gap-5">
        <?php foreach ($cards as [$no, $h, $t, $tags, $ic]): ?>
        <article class="bg-white rounded-2xl p-6 md:p-7 shadow-[0_1px_8px_rgba(0,0,0,.05)] flex flex-col">
            <div class="flex items-start justify-between">
                <span class="inline-flex items-center justify-center min-w-[44px] h-[44px] px-2 rounded-lg bg-[#eeeeef] font-head font-extrabold text-[22px]"><?php echo e($no); ?></span>
                <i class="fa-solid <?php echo e($ic); ?> text-gray-500 text-[15px]"></i>
            </div>
            <h2 class="font-head font-extrabold uppercase text-[22px] md:text-[24px] tracking-tight mt-4"><?php echo e($no . ' ' . $h); ?></h2>
            <p class="text-[15px] leading-relaxed text-gray-600 mt-2.5 mb-5"><?php echo e($t); ?></p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1 border-t border-gray-200 mt-auto pt-4 text-[10px] font-bold tracking-[0.1em] uppercase text-gray-600">
                <?php foreach ($tags as $tg): ?><li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full bg-gray-700"></span><?php echo e($tg); ?></li><?php endforeach; ?>
            </ul>
        </article>
        <?php endforeach; ?>
    </div>

    <aside class="mt-6 bg-black text-white rounded-2xl px-5 md:px-6 py-5 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <span class="w-11 h-11 rounded-full bg-[#1c1c1c] flex items-center justify-center"><i class="fa-regular fa-envelope"></i></span>
            <div>
                <p class="font-bold text-[13px] md:text-[14px] uppercase tracking-wide">Ada pertanyaan khusus terkait privasi?</p>
                <p class="text-[12px] text-gray-300">Hubungi langsung bagian kepatuhan data studio AMBOKO.</p>
            </div>
        </div>
        <a href="info.php?p=contact" class="bg-[#fafafa] text-black rounded-lg px-6 py-3 text-[12px] font-bold tracking-[0.08em] uppercase hover:bg-gray-200 transition">Hubungi Tim Legal</a>
    </aside>
</main>
<?php
    endif;

    store_footer('plain');
    $faqJs = '';
    if ($key === 'faq') {
        $faqJs = "<script>
(function(){
  var items=document.querySelectorAll('.faq-item');
  items.forEach(function(it){
    it.querySelector('.faq-q').addEventListener('click',function(){
      var btn=it.querySelector('.faq-q'), ans=it.querySelector('.faq-a'), ico=it.querySelector('.faq-ico');
      var open=ans.classList.contains('hidden');
      ans.classList.toggle('hidden',!open);
      btn.setAttribute('aria-expanded',open?'true':'false');
      ico.classList.toggle('fa-xmark',open); ico.classList.toggle('fa-plus',!open);
    });
  });
  document.querySelectorAll('.faq-pill').forEach(function(p){
    p.addEventListener('click',function(){
      var f=p.dataset.filter;
      document.querySelectorAll('.faq-pill').forEach(function(x){
        var on=x===p;
        x.classList.toggle('bg-black',on); x.classList.toggle('text-white',on);
        x.classList.toggle('bg-[#eeeeef]',!on); x.classList.toggle('text-gray-800',!on);
      });
      items.forEach(function(it){ it.classList.toggle('hidden', f!=='all' && it.dataset.cat!==f); });
    });
  });
})();
</script>";
    }
    page_end($faqJs);
    exit;
}

page_head($title . ' - AMBOKO');
store_header('app', '', $conn);
?>
<main class="max-w-[760px] mx-auto px-5 md:px-8 pt-10 min-h-[50vh]">
    <h1 class="font-head font-extrabold text-[clamp(30px,4vw,44px)] tracking-tight leading-none mb-6"><?php echo e($title); ?></h1>
    <div class="space-y-4 text-[15px] leading-relaxed text-gray-700">
        <?php foreach ($paras as $p): ?><p><?php echo e($p); ?></p><?php endforeach; ?>
    </div>
</main>
<?php store_footer('app'); page_end();

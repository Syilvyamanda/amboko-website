<?php
// Form tambah / edit produk (dipakai tambah_produk.php & edit_produk.php)
// Variabel dari pemanggil: $conn, $editId (int|null)
$editing = !empty($editId);
$cats = $conn->query("SELECT id, name, parent_id FROM categories ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

$p = ['name' => '', 'short_name' => '', 'sku' => '', 'category_id' => '', 'price' => '', 'old_price' => '', 'sale_percent' => 0, 'badge_text' => '',
      'stock' => 0, 'material' => '', 'availability' => 'in_stock', 'is_new' => 0, 'is_active' => 1, 'popularity' => 50, 'home_sort' => '',
      'description' => '', 'image_url' => '', 'ui_config' => null];
$gallery = [];
if ($editing) {
    $st = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $st->bind_param('i', $editId);
    $st->execute();
    $found = $st->get_result()->fetch_assoc();
    if (!$found) { flash('error', 'Produk tidak ditemukan.'); redirect('admin_produk.php'); }
    $p = $found;
    $gallery = $conn->query("SELECT * FROM product_images WHERE product_id = " . (int)$editId . " ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
}
$optionsText = $editing ? options_to_text(product_options($conn, $editId)) : "Select Size: All Size, S, M, L, XL\nColor / Pilihan Warna [warna]: Hitam=#111111, Putih=#f5f5f5";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = [
        'name' => trim($_POST['name'] ?? ''), 'short_name' => trim($_POST['short_name'] ?? ''), 'sku' => strtoupper(trim($_POST['sku'] ?? '')),
        'category_id' => (int)($_POST['category_id'] ?? 0), 'price' => (int)($_POST['price'] ?? 0),
        'old_price' => ($_POST['old_price'] ?? '') !== '' ? (int)$_POST['old_price'] : null,
        'sale_percent' => max(0, min(90, (int)($_POST['sale_percent'] ?? 0))), 'badge_text' => trim($_POST['badge_text'] ?? '') ?: null,
        'stock' => max(0, (int)($_POST['stock'] ?? 0)), 'material' => trim($_POST['material'] ?? '') ?: null,
        'availability' => in_array($_POST['availability'] ?? '', ['in_stock', 'pre_order', 'limited'], true) ? $_POST['availability'] : 'in_stock',
        'is_new' => !empty($_POST['is_new']) ? 1 : 0, 'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'popularity' => (int)($_POST['popularity'] ?? 0), 'home_sort' => ($_POST['home_sort'] ?? '') !== '' ? (int)$_POST['home_sort'] : null,
        'description' => trim($_POST['description'] ?? ''),
    ];
    if ($in['name'] === '') $errors[] = 'Nama produk wajib diisi.';
    if ($in['price'] < 1) $errors[] = 'Harga harus lebih dari 0.';
    if (!$in['category_id']) $errors[] = 'Pilih kategori.';
    if ($in['old_price'] !== null && $in['old_price'] <= $in['price']) $in['old_price'] = null;

    // SKU unik
    if ($in['sku'] === '') {
        $maxNo = (int)$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(sku, 5) AS UNSIGNED)), 0) FROM products WHERE sku LIKE 'AMB-%'")->fetch_row()[0];
        $in['sku'] = 'AMB-' . str_pad((string)($maxNo + 1), 2, '0', STR_PAD_LEFT);
    }
    $q = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id <> ?");
    $selfId = $editId ?: 0;
    $q->bind_param('si', $in['sku'], $selfId);
    $q->execute();
    if ($q->get_result()->num_rows) $errors[] = 'SKU ' . $in['sku'] . ' sudah dipakai produk lain.';

    // upload
    $upErr = null;
    $mainUpload = save_upload($_FILES['image'] ?? [], $upErr);
    if ($upErr) $errors[] = $upErr;
    $galleryUploads = [];
    foreach (files_array('gallery') as $gf) {
        $ge = null; $path = save_upload($gf, $ge);
        if ($ge) $errors[] = $ge; elseif ($path) $galleryUploads[] = $path;
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            // slug unik
            $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($in['name'])), '-') ?: 'produk';
            $slug = $base; $n = 1;
            while (true) {
                $c = $conn->prepare("SELECT id FROM products WHERE slug = ? AND id <> ?");
                $c->bind_param('si', $slug, $selfId);
                $c->execute();
                if (!$c->get_result()->num_rows) break;
                $slug = $base . '-' . (++$n);
            }
            $image = $mainUpload ?: ($p['image_url'] ?: ($galleryUploads[0] ?? null));
            $short = $in['short_name'] ?: null;

            if ($editing) {
                $oldStock = (int)$p['stock'];
                $u = $conn->prepare("UPDATE products SET sku=?, slug=?, name=?, short_name=?, category_id=?, price=?, old_price=?, sale_percent=?, badge_text=?, stock=?, material=?, availability=?, is_new=?, is_active=?, popularity=?, home_sort=?, description=?, image_url=? WHERE id=?");
                $u->bind_param('ssssiiiisissiiiissi', $in['sku'], $slug, $in['name'], $short, $in['category_id'], $in['price'], $in['old_price'], $in['sale_percent'], $in['badge_text'], $in['stock'], $in['material'], $in['availability'], $in['is_new'], $in['is_active'], $in['popularity'], $in['home_sort'], $in['description'], $image, $editId);
                $u->execute();
                $pid = $editId;
                if ($in['stock'] !== $oldStock) stock_log($conn, $pid, 'adjustment', $in['stock'] - $oldStock, 'Koreksi dari form edit produk', null, user_id());
            } else {
                $ui = '{"label_style":"title","review_style":"none","review_layout":"cards"}';
                $i = $conn->prepare("INSERT INTO products (sku, slug, name, short_name, category_id, price, old_price, sale_percent, badge_text, stock, material, availability, is_new, is_active, popularity, home_sort, description, image_url, home_image_url, rating_avg, ui_config) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)");
                $i->bind_param('ssssiiiisissiiiissss', $in['sku'], $slug, $in['name'], $short, $in['category_id'], $in['price'], $in['old_price'], $in['sale_percent'], $in['badge_text'], $in['stock'], $in['material'], $in['availability'], $in['is_new'], $in['is_active'], $in['popularity'], $in['home_sort'], $in['description'], $image, $image, $ui);
                $i->execute();
                $pid = $conn->insert_id;
                if ($in['stock'] > 0) stock_log($conn, $pid, 'initial', $in['stock'], 'Stok awal', null, user_id());
            }

            // galeri: hapus yang dicentang, tambah baru
            foreach ((array)($_POST['del_img'] ?? []) as $did) {
                $d = $conn->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
                $d->bind_param('ii', $did, $pid); $d->execute();
            }
            $so = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id = $pid")->fetch_row()[0];
            $ig = $conn->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?,?,?)");
            foreach (array_filter(array_merge($mainUpload ? [$mainUpload] : [], $galleryUploads)) as $path) { $so++; $ig->bind_param('isi', $pid, $path, $so); $ig->execute(); }

            // varian (hanya jika teks berubah)
            $newText = trim(str_replace("\r", '', $_POST['options_text'] ?? ''));
            if (!$editing || $newText !== trim($optionsText)) save_options($conn, $pid, $newText);

            $conn->commit();
            flash('success', $editing ? 'Produk berhasil diperbarui.' : 'Produk berhasil ditambahkan.');
            redirect('admin_produk.php');
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log('Simpan produk gagal: ' . $ex->getMessage());
            $errors[] = 'Gagal menyimpan produk: ' . $ex->getMessage();
        }
    }
    $p = array_merge($p, $in);
    $optionsText = $_POST['options_text'] ?? $optionsText;
}

$inp = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black outline-none text-sm bg-white';
$lab = 'block text-gray-700 text-xs font-bold tracking-wide mb-1.5 uppercase';
admin_head($editing ? 'Edit Produk' : 'Tambah Produk Baru', 'produk');
?>
<div class="max-w-4xl">
    <a href="admin_produk.php" class="text-sm text-gray-500 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke produk</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-2 mb-6"><?php echo $editing ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h1>

    <?php if ($errors): ?><div class="bg-red-50 text-red-700 rounded-lg px-4 py-3 mb-5 text-sm"><ul class="list-disc ml-4"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-6">
        <?php echo csrf_field(); ?>
        <section class="bg-white rounded-xl border border-gray-200 p-6 grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="<?php echo $lab; ?>">Nama Produk (judul halaman)</label><input name="name" value="<?php echo e($p['name']); ?>" class="<?php echo $inp; ?>" required></div>
            <div><label class="<?php echo $lab; ?>">Nama Singkat (katalog &amp; home)</label><input name="short_name" value="<?php echo e($p['short_name']); ?>" class="<?php echo $inp; ?>" placeholder="opsional"></div>
            <div><label class="<?php echo $lab; ?>">SKU</label><input name="sku" value="<?php echo e($p['sku']); ?>" class="<?php echo $inp; ?>" placeholder="otomatis jika kosong"></div>
            <div><label class="<?php echo $lab; ?>">Kategori</label>
                <select name="category_id" class="<?php echo $inp; ?>" required><option value="">- pilih -</option>
                    <?php foreach ($cats as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$p['category_id'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo ($c['parent_id'] ? '&nbsp;&nbsp;↳ ' : '') . e($c['name']); ?></option><?php endforeach; ?></select></div>
            <div><label class="<?php echo $lab; ?>">Material (filter katalog)</label><input name="material" value="<?php echo e($p['material']); ?>" list="materials" class="<?php echo $inp; ?>" placeholder="Heavy Terry / Ripstop / ..."><datalist id="materials"><option>Heavy Terry</option><option>Ripstop</option><option>Micro-Twill</option><option>Combed Cotton</option></datalist></div>
            <div><label class="<?php echo $lab; ?>">Harga (Rp)</label><input type="number" min="1" name="price" value="<?php echo e($p['price']); ?>" class="<?php echo $inp; ?>" required></div>
            <div><label class="<?php echo $lab; ?>">Harga Coret (opsional)</label><input type="number" min="0" name="old_price" value="<?php echo e($p['old_price']); ?>" class="<?php echo $inp; ?>"></div>
            <div><label class="<?php echo $lab; ?>">Label Diskon di Katalog (%)</label><input type="number" min="0" max="90" name="sale_percent" value="<?php echo e($p['sale_percent']); ?>" class="<?php echo $inp; ?>"></div>
            <div><label class="<?php echo $lab; ?>">Badge Harga (PROMO / READY STOCK)</label><input name="badge_text" value="<?php echo e($p['badge_text']); ?>" class="<?php echo $inp; ?>"></div>
            <div><label class="<?php echo $lab; ?>"><?php echo $editing ? 'Stok Saat Ini' : 'Stok Awal'; ?></label><input type="number" min="0" name="stock" value="<?php echo e($p['stock']); ?>" class="<?php echo $inp; ?>" required>
                <?php if ($editing): ?><p class="text-[11px] text-gray-400 mt-1">Perubahan angka dicatat sebagai koreksi di ledger stok.</p><?php endif; ?></div>
            <div><label class="<?php echo $lab; ?>">Ketersediaan</label>
                <select name="availability" class="<?php echo $inp; ?>"><?php foreach (['in_stock' => 'In Stock', 'pre_order' => 'Pre-Order', 'limited' => 'Limited Edition'] as $k => $v) echo '<option value="' . $k . '"' . ($p['availability'] === $k ? ' selected' : '') . '>' . $v . '</option>'; ?></select></div>
            <div><label class="<?php echo $lab; ?>">Popularitas (urutan "Most Popular")</label><input type="number" name="popularity" value="<?php echo e($p['popularity']); ?>" class="<?php echo $inp; ?>"></div>
            <div><label class="<?php echo $lab; ?>">Urutan di Home (kosong = tidak tampil)</label><input type="number" min="1" name="home_sort" value="<?php echo e($p['home_sort']); ?>" class="<?php echo $inp; ?>"></div>
            <div class="sm:col-span-2 flex flex-wrap gap-6 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?php echo $p['is_active'] ? 'checked' : ''; ?>> Tampilkan di toko (aktif)</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_new" value="1" <?php echo $p['is_new'] ? 'checked' : ''; ?>> New Edition</label>
            </div>
            <div class="sm:col-span-2"><label class="<?php echo $lab; ?>">Deskripsi &amp; Fit</label><textarea name="description" rows="4" class="<?php echo $inp; ?>"><?php echo e($p['description']); ?></textarea></div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold mb-1">Varian (Size / Warna / dll.)</h2>
            <p class="text-xs text-gray-500 mb-3">Satu grup per baris. Tambahkan <code class="bg-gray-100 px-1 rounded">[warna]</code> pada nama grup untuk pilihan warna dan <code class="bg-gray-100 px-1 rounded">Nama=#hex</code> untuk kode warnanya. Nilai pertama menjadi pilihan default. Foto per warna yang sudah ada tetap dipertahankan.</p>
            <textarea name="options_text" rows="5" class="<?php echo $inp; ?> font-mono text-xs"><?php echo e($optionsText); ?></textarea>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold mb-3">Foto Produk</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="<?php echo $lab; ?>">Foto Utama (kartu katalog)</label>
                    <?php if ($p['image_url']): ?><img src="<?php echo e(img($p['image_url'])); ?>" class="w-24 h-32 object-cover rounded-lg mb-2 bg-gray-100" alt=""><?php endif; ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="text-sm"></div>
                <div><label class="<?php echo $lab; ?>">Tambah Foto Galeri (boleh banyak)</label><input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" class="text-sm"><p class="text-[11px] text-gray-400 mt-1">JPG / PNG / WEBP, maks. 4 MB per file.</p></div>
            </div>
            <?php if ($gallery): ?>
                <p class="<?php echo $lab; ?> mt-5">Galeri saat ini (centang untuk menghapus)</p>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($gallery as $g): ?>
                        <label class="relative cursor-pointer"><img src="<?php echo e(img($g['image_url'])); ?>" class="w-16 h-20 object-cover rounded-lg bg-gray-100" alt=""><input type="checkbox" name="del_img[]" value="<?php echo (int)$g['id']; ?>" class="absolute top-1 left-1"></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="flex justify-end gap-2">
            <a href="admin_produk.php" class="bg-gray-200 text-gray-800 font-bold py-2.5 px-5 rounded-lg hover:bg-gray-300 text-sm">Batal</a>
            <button type="submit" class="bg-black text-white font-bold py-2.5 px-6 rounded-lg hover:bg-gray-800 text-sm"><?php echo $editing ? 'Simpan Perubahan' : 'Simpan Produk'; ?></button>
        </div>
    </form>
</div>
<?php admin_foot();

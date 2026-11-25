<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$showArchived = !empty($_GET['archived']);

$where = []; $types = ''; $args = [];
if (!$showArchived) $where[] = 'p.is_active = 1';
if ($cat) { $where[] = '(p.category_id = ? OR c.parent_id = ?)'; $types .= 'ii'; array_push($args, $cat, $cat); }
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $types .= 'ss'; $l = "%$q%"; array_push($args, $l, $l); }
$st = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY p.id DESC");
if ($types) $st->bind_param($types, ...$args);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$cats = $conn->query("SELECT id, name, parent_id FROM categories ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

admin_head('Produk', 'produk');
$inp = ADMIN_INP;
$keep = http_build_query(array_filter(['q' => $q, 'cat' => $cat, 'archived' => $showArchived ? 1 : null]));
?>
<div class="flex flex-wrap items-center gap-3 mb-5">
    <form class="flex flex-wrap items-center gap-2">
        <input name="q" value="<?php echo e($q); ?>" placeholder="Cari nama / SKU" class="<?php echo $inp; ?> !w-56">
        <select name="cat" class="<?php echo $inp; ?> !w-44" onchange="this.form.submit()">
            <option value="0">Semua kategori</option>
            <?php foreach ($cats as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $cat === (int)$c['id'] ? 'selected' : ''; ?>><?php echo ($c['parent_id'] ? '&nbsp;&nbsp;↳ ' : '') . e($c['name']); ?></option><?php endforeach; ?>
        </select>
        <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    </form>
    <a href="?<?php echo http_build_query(array_filter(['q' => $q, 'cat' => $cat, 'archived' => $showArchived ? null : 1])); ?>" class="text-sm text-gray-500 hover:text-black underline"><?php echo $showArchived ? 'Sembunyikan arsip' : 'Tampilkan arsip'; ?></a>
    <div class="ml-auto flex gap-2">
        <a href="admin_kategori.php" class="border border-gray-300 bg-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100"><i class="fa-solid fa-tags mr-2"></i>Kategori</a>
        <a href="tambah_produk.php" class="bg-black text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800"><i class="fa-solid fa-plus mr-2"></i>Tambah Produk</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
            <th class="px-5 py-3">Produk</th><th class="px-5 py-3">SKU</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Harga</th><th class="px-5 py-3">Stok</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Aksi</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($rows as $r): $low = (int)$r['stock'] <= LOW_STOCK_LIMIT; ?>
            <tr class="hover:bg-gray-50 <?php echo $r['is_active'] ? '' : 'opacity-50'; ?>">
                <td class="px-5 py-3"><div class="flex items-center gap-3"><img src="<?php echo e(img($r['image_url'])); ?>" alt="" class="w-10 h-12 object-cover rounded-md bg-gray-100"><a href="product.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="font-medium hover:underline"><?php echo e($r['name']); ?></a></div></td>
                <td class="px-5 py-3 text-gray-500"><?php echo e($r['sku']); ?></td>
                <td class="px-5 py-3"><?php echo e($r['category_name'] ?: '-'); ?></td>
                <td class="px-5 py-3"><?php echo e(rp($r['price'])); ?></td>
                <td class="px-5 py-3 font-bold <?php echo $low ? 'text-red-600' : 'text-green-600'; ?>"><?php echo (int)$r['stock']; ?> pcs</td>
                <td class="px-5 py-3"><?php echo $r['is_active'] ? admin_pill('Aktif', 'bg-green-50 text-green-700') : admin_pill('Diarsipkan'); ?></td>
                <td class="px-5 py-3 whitespace-nowrap">
                    <a href="edit_produk.php?id=<?php echo (int)$r['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                    <form method="post" action="hapus_produk.php" class="inline" onsubmit="return confirm('Yakin ingin menghapus / mengarsipkan produk ini?')">
                        <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="back" value="admin_produk.php">
                        <button class="text-red-500 hover:text-red-700 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">Tidak ada produk.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<p class="text-xs text-gray-400 mt-3">Foto produk (utama &amp; galeri) diatur di halaman Edit / Tambah Produk. Produk yang pernah dipesan otomatis diarsipkan saat dihapus.</p>
<?php admin_foot();

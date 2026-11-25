-- =====================================================================
--  AMBOKO ERP  -  UPGRADE database LAMA -> struktur baru
--
--  Gunakan file ini HANYA jika Anda sudah punya data asli (akun pelanggan,
--  pesanan) di database `amboko_erp` versi lama dan ingin mempertahankannya.
--  * Akun, pesanan, dan produk lama TETAP ADA (password lama otomatis diubah
--    menjadi hash saat pemiliknya login pertama kali).
--  * Produk lama diberi data standar (SKU, slug, kategori). Foto/varian/ulasan
--    sesuai desain ada di database.sql (instalasi baru).
--  * Butuh MariaDB (XAMPP) karena memakai "ADD COLUMN IF NOT EXISTS".
--  BACKUP DULU database Anda sebelum menjalankan skrip ini.
-- =====================================================================
USE amboko_erp;
SET FOREIGN_KEY_CHECKS = 0;

-- USERS ---------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS address TEXT NULL,
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS postal_code VARCHAR(10) NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- KATEGORI ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(60) NOT NULL, slug VARCHAR(60) NOT NULL UNIQUE,
    tag_label VARCHAR(60) NOT NULL, group_label VARCHAR(30) NOT NULL, sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO categories (id, parent_id, name, slug, tag_label, group_label, sort_order) VALUES
(1, NULL, 'Trousers/pants', 'trousers-pants', 'TROUSERS / PANTS', 'BOTTOMS', 1),
(2, NULL, 'T-Shirt', 't-shirt', 'T-SHIRT', 'TOPS', 2),
(3, NULL, 'Long Pants', 'long-pants', 'LONG PANTS', 'BOTTOMS', 3),
(4, NULL, 'Jackets', 'jackets', 'JACKET', 'OUTERWEAR', 4),
(5, 1, 'Short Pants', 'short-pants', 'SHORT PANTS', 'BOTTOMS', 5);

-- PRODUK --------------------------------------------------------------
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS sku VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS slug VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS short_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS cart_name VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS checkout_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS category_id INT NULL,
    ADD COLUMN IF NOT EXISTS old_price INT NULL,
    ADD COLUMN IF NOT EXISTS sale_percent TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS badge_text VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS material VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS availability ENUM('in_stock','pre_order','limited') NOT NULL DEFAULT 'in_stock',
    ADD COLUMN IF NOT EXISTS is_new TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS popularity INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS home_sort INT NULL,
    ADD COLUMN IF NOT EXISTS description TEXT NULL,
    ADD COLUMN IF NOT EXISTS home_image_url VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS rating_avg DECIMAL(2,1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS review_total INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_5 INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_4 INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_3 INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_2 INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_1 INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS recommend_pct TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS recommend_note VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS ui_config TEXT NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE products MODIFY COLUMN category VARCHAR(50) NULL;
UPDATE products p JOIN categories c ON c.name = p.category SET p.category_id = c.id WHERE p.category_id IS NULL;
UPDATE products SET category_id = 4 WHERE category_id IS NULL AND (name LIKE '%jacket%' OR name LIKE '%jaket%');
UPDATE products SET category_id = 2 WHERE category_id IS NULL AND (name LIKE '%shirt%' OR name LIKE '%kaos%');
UPDATE products SET category_id = 1 WHERE category_id IS NULL;
UPDATE products SET sku = CONCAT('AMB-', LPAD(id, 2, '0')) WHERE sku IS NULL OR sku = '';
UPDATE products SET slug = CONCAT('produk-', id) WHERE slug IS NULL OR slug = '';
UPDATE products SET popularity = 50 WHERE popularity = 0;
UPDATE products SET ui_config = '{"label_style":"title","review_style":"none","review_layout":"cards"}' WHERE ui_config IS NULL;
UPDATE products SET image_url = NULL WHERE image_url = 'placeholder.jpg';
-- Foto bawaan desain untuk 3 produk contoh lama (berdasarkan nama)
UPDATE products SET image_url = 'catalog.jpg',  home_image_url = 'Jacket1.png'        WHERE image_url IS NULL AND name LIKE '%jacket%';
UPDATE products SET image_url = 'catalog2.jpg', home_image_url = 'KAOS1.png'          WHERE image_url IS NULL AND (name LIKE '%shirt%' OR name LIKE '%kaos%');
UPDATE products SET image_url = 'catalog4.jpg', home_image_url = 'CELANAPENDEK1.png'  WHERE image_url IS NULL AND name LIKE '%short%';
ALTER TABLE products MODIFY COLUMN sku VARCHAR(20) NOT NULL, MODIFY COLUMN slug VARCHAR(160) NOT NULL;
ALTER TABLE products ADD UNIQUE INDEX IF NOT EXISTS uq_products_sku (sku), ADD UNIQUE INDEX IF NOT EXISTS uq_products_slug (slug);

-- TABEL PRODUK TAMBAHAN ----------------------------------------------
CREATE TABLE IF NOT EXISTS product_images (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, image_url VARCHAR(255) NOT NULL, sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pimg_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_option_groups (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, title VARCHAR(60) NOT NULL, cart_label VARCHAR(30) NOT NULL,
    type ENUM('pill','color') NOT NULL DEFAULT 'pill', show_value TINYINT(1) NOT NULL DEFAULT 0, guide_label VARCHAR(30) NULL, sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pog_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_option_values (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, label VARCHAR(60) NOT NULL, display_label VARCHAR(80) NULL,
    hex VARCHAR(9) NULL, hex2 VARCHAR(9) NULL, image_url VARCHAR(255) NULL, sort_order INT NOT NULL DEFAULT 0, is_default TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_pov_group FOREIGN KEY (group_id) REFERENCES product_option_groups(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_relations (product_id INT NOT NULL, related_product_id INT NOT NULL, sort_order INT NOT NULL DEFAULT 0, PRIMARY KEY (product_id, related_product_id),
    CONSTRAINT fk_prel_a FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE, CONSTRAINT fk_prel_b FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_metrics (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, label VARCHAR(80) NOT NULL, percent TINYINT NOT NULL, note VARCHAR(120) NULL, sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pmet_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_reviews (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NULL, reviewer_name VARCHAR(80) NOT NULL, rating TINYINT NOT NULL, comment TEXT NOT NULL,
    variant_info VARCHAR(80) NULL, is_verified TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
    CONSTRAINT fk_prev_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE, CONSTRAINT fk_prev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wishlists (user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_wl_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS vouchers (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL UNIQUE, type ENUM('percent','fixed') NOT NULL, value INT NOT NULL, min_purchase INT NOT NULL DEFAULT 0,
    max_discount INT NULL, usage_limit INT NULL, used_count INT NOT NULL DEFAULT 0, expires_at DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB;
INSERT IGNORE INTO vouchers (code, type, value, min_purchase, max_discount, usage_limit, expires_at) VALUES
('AMBOKO10','percent',10,100000,30000,NULL,'2026-12-31'),('WELCOME15','fixed',15000,50000,NULL,500,'2026-12-31'),('BLACKFRIDAY15','percent',15,150000,50000,200,'2026-11-30');

-- PESANAN -------------------------------------------------------------
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS order_code VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS voucher_code VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS service_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status ENUM('pending_payment','placed','packing','shipped','delivered','cancelled') NOT NULL DEFAULT 'delivered',
    ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid',
    ADD COLUMN IF NOT EXISTS courier ENUM('regular','express') NOT NULL DEFAULT 'regular',
    ADD COLUMN IF NOT EXISTS shipping_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS shipping_email VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS shipping_address TEXT NULL,
    ADD COLUMN IF NOT EXISTS shipping_city VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS shipping_postal VARCHAR(10) NULL,
    ADD COLUMN IF NOT EXISTS address_note VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- Pesanan lama dianggap sudah lunas & selesai
UPDATE orders o LEFT JOIN users u ON u.id = o.user_id SET
    o.order_code = CONCAT('AMB-', LPAD(o.id, 6, '0')), o.subtotal = o.total_amount,
    o.shipping_name = COALESCE(o.shipping_name, u.name, 'Pelanggan'), o.shipping_email = COALESCE(o.shipping_email, u.email),
    o.tracking_number = CONCAT('AMB-EXP-', LPAD(o.id, 6, '0')), o.tax_amount = ROUND(o.total_amount * 11 / 111)
WHERE o.order_code IS NULL;
ALTER TABLE orders MODIFY COLUMN order_code VARCHAR(20) NOT NULL;
ALTER TABLE orders ADD UNIQUE INDEX IF NOT EXISTS uq_orders_code (order_code);

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS product_name VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS sku VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS options TEXT NULL;
UPDATE order_items oi JOIN products p ON p.id = oi.product_id SET oi.product_name = COALESCE(p.short_name, p.name), oi.sku = p.sku, oi.image_url = p.image_url WHERE oi.product_name IS NULL;

CREATE TABLE IF NOT EXISTS order_status_history (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, status VARCHAR(30) NOT NULL, note VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE) ENGINE=InnoDB;
INSERT INTO order_status_history (order_id, status, note, created_at)
    SELECT id, 'placed', 'Pesanan masuk', created_at FROM orders WHERE id NOT IN (SELECT order_id FROM order_status_history);
CREATE TABLE IF NOT EXISTS payments (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, method ENUM('qris','va','card') NOT NULL, channel VARCHAR(60) NOT NULL, channel_detail VARCHAR(80) NULL,
    transaction_id VARCHAR(40) NOT NULL, status ENUM('pending','settled','failed','refunded') NOT NULL DEFAULT 'pending', amount DECIMAL(14,2) NOT NULL, paid_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE) ENGINE=InnoDB;
INSERT INTO payments (order_id, method, channel, channel_detail, transaction_id, status, amount, paid_at)
    SELECT id, 'qris', 'QRIS Instant', 'BCA / GoPay / Mandiri', CONCAT('TRX-', RIGHT(order_code, 6), '-IDN'), 'settled', total_amount, created_at FROM orders WHERE id NOT IN (SELECT order_id FROM payments);

-- LEDGER STOK -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, order_id INT NULL, user_id INT NULL,
    type ENUM('initial','sale','restock','adjustment','cancel_return') NOT NULL, qty_change INT NOT NULL, stock_after INT NOT NULL, note VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE, CONSTRAINT fk_sm_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL) ENGINE=InnoDB;
INSERT INTO stock_movements (product_id, type, qty_change, stock_after, note)
    SELECT id, 'initial', stock, stock, 'Saldo awal saat upgrade' FROM products WHERE id NOT IN (SELECT product_id FROM stock_movements);

SET FOREIGN_KEY_CHECKS = 1;
SELECT 'Upgrade selesai. Login admin: admin@amboko.com' AS info;

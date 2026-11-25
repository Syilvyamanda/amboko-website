-- =====================================================================
--  AMBOKO ERP  -  DATABASE LENGKAP (instalasi baru)
--  Import lewat phpMyAdmin / `mysql -u root < database.sql`
--
--  PERHATIAN: file ini menghapus & membuat ulang semua tabel AMBOKO.
--  Jika Anda sudah punya data (user/pesanan) yang ingin dipertahankan,
--  gunakan database_upgrade.sql sebagai gantinya.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS amboko_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amboko_erp;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stock_movements, payments, order_status_history, order_items, orders,
    wishlists, vouchers, product_reviews, product_metrics, product_relations,
    product_option_values, product_option_groups, product_images, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- USERS  (password disimpan sebagai hash bcrypt via password_hash())
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(100) UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL,
    role         ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    phone        VARCHAR(30)  NULL,
    address      TEXT         NULL,
    city         VARCHAR(100) NULL,
    postal_code  VARCHAR(10)  NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- KATEGORI  (Short Pants = anak dari Trousers/pants)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT NULL,
    name        VARCHAR(60) NOT NULL,
    slug        VARCHAR(60) NOT NULL UNIQUE,
    tag_label   VARCHAR(60) NOT NULL,           -- label pill di halaman produk (mis. "TROUSERS / PANTS")
    group_label VARCHAR(30) NOT NULL,           -- label di keranjang (OUTERWEAR / TOPS / BOTTOMS)
    sort_order  INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PRODUK
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    sku            VARCHAR(20)  NOT NULL UNIQUE,
    slug           VARCHAR(160) NOT NULL UNIQUE,
    name           VARCHAR(150) NOT NULL,        -- judul halaman produk
    short_name     VARCHAR(100) NULL,            -- nama di katalog & home
    cart_name      VARCHAR(150) NULL,            -- nama di keranjang
    checkout_name  VARCHAR(100) NULL,            -- nama di ringkasan checkout / konfirmasi
    category_id    INT NULL,
    price          INT NOT NULL,
    old_price      INT NULL,                     -- harga coret
    sale_percent   TINYINT NOT NULL DEFAULT 0,   -- label "-10%" di home & katalog
    badge_text     VARCHAR(30) NULL,             -- PROMO / READY STOCK
    stock          INT NOT NULL DEFAULT 0,
    material       VARCHAR(40) NULL,             -- filter Material
    availability   ENUM('in_stock','pre_order','limited') NOT NULL DEFAULT 'in_stock',
    is_new         TINYINT(1) NOT NULL DEFAULT 0,-- filter "New Edition"
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    popularity     INT NOT NULL DEFAULT 0,       -- urutan "Most Popular"
    home_sort      INT NULL,                     -- urutan di kartu miring Home (NULL = tidak tampil)
    description    TEXT NULL,
    image_url      VARCHAR(255) NULL,            -- foto kartu katalog
    home_image_url VARCHAR(255) NULL,            -- foto kartu miring di home
    -- ringkasan rating (basis dari desain; ikut bertambah saat ada ulasan baru)
    rating_avg     DECIMAL(2,1) NOT NULL DEFAULT 0,
    review_total   INT NOT NULL DEFAULT 0,
    rating_5       INT NOT NULL DEFAULT 0,
    rating_4       INT NOT NULL DEFAULT 0,
    rating_3       INT NOT NULL DEFAULT 0,
    rating_2       INT NOT NULL DEFAULT 0,
    rating_1       INT NOT NULL DEFAULT 0,
    recommend_pct  TINYINT NOT NULL DEFAULT 0,
    recommend_note VARCHAR(120) NULL,
    ui_config      TEXT NULL,                    -- JSON: variasi tampilan per produk (lihat includes/functions.php)
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_prod_active (is_active, popularity)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    image_url   VARCHAR(255) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pimg_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Grup pilihan (Select Size / Printing / Color ...)
CREATE TABLE product_option_groups (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    title       VARCHAR(60) NOT NULL,            -- label di halaman produk
    cart_label  VARCHAR(30) NOT NULL,            -- label chip di keranjang (WARNA / SIZE ...)
    type        ENUM('pill','color') NOT NULL DEFAULT 'pill',
    show_value  TINYINT(1) NOT NULL DEFAULT 0,   -- tampilkan nilai terpilih di kanan judul
    guide_label VARCHAR(30) NULL,                -- "Size Guide" / "Size Chart"
    sort_order  INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pog_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_option_values (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    group_id      INT NOT NULL,
    label         VARCHAR(60) NOT NULL,
    display_label VARCHAR(80) NULL,              -- teks di kanan judul (mis. "Hitam (Washed Black)")
    hex           VARCHAR(9)  NULL,              -- warna titik
    hex2          VARCHAR(9)  NULL,              -- warna kedua (two-tone)
    image_url     VARCHAR(255) NULL,             -- foto yang tampil saat warna dipilih
    sort_order    INT NOT NULL DEFAULT 0,
    is_default    TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_pov_group FOREIGN KEY (group_id) REFERENCES product_option_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_relations (               -- "Complete The Look"
    product_id          INT NOT NULL,
    related_product_id  INT NOT NULL,
    sort_order          INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id, related_product_id),
    CONSTRAINT fk_prel_a FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_prel_b FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_metrics (                 -- bar ringkasan (Tingkat Kepuasan, Kesesuaian Ukuran ...)
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    label       VARCHAR(80) NOT NULL,
    percent     TINYINT NOT NULL,
    note        VARCHAR(120) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pmet_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL,
    user_id       INT NULL,
    reviewer_name VARCHAR(80) NOT NULL,
    rating        TINYINT NOT NULL,
    comment       TEXT NOT NULL,
    variant_info  VARCHAR(80) NULL,              -- "Washed Black / L"
    is_verified   TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL,
    CONSTRAINT fk_prev_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_prev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE wishlists (
    user_id     INT NOT NULL,
    product_id  INT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wl_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vouchers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30) NOT NULL UNIQUE,
    type          ENUM('percent','fixed') NOT NULL,
    value         INT NOT NULL,
    min_purchase  INT NOT NULL DEFAULT 0,
    max_discount  INT NULL,
    usage_limit   INT NULL,
    used_count    INT NOT NULL DEFAULT 0,
    expires_at    DATE NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PESANAN
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    order_code       VARCHAR(20) NOT NULL UNIQUE,            -- AMB-892401
    user_id          INT NULL,
    subtotal         DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
    voucher_code     VARCHAR(30) NULL,
    shipping_cost    DECIMAL(14,2) NOT NULL DEFAULT 0,
    service_fee      DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount       DECIMAL(14,2) NOT NULL DEFAULT 0,       -- PPN 11% yang sudah termasuk di total
    total_amount     DECIMAL(14,2) NOT NULL,
    status           ENUM('pending_payment','placed','packing','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
    payment_status   ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    courier          ENUM('regular','express') NOT NULL DEFAULT 'regular',
    shipping_name    VARCHAR(100) NULL,
    shipping_phone   VARCHAR(30)  NULL,
    shipping_email   VARCHAR(100) NULL,
    shipping_address TEXT NULL,
    shipping_city    VARCHAR(100) NULL,
    shipping_postal  VARCHAR(10)  NULL,
    address_note     VARCHAR(255) NULL,                      -- patokan
    tracking_number  VARCHAR(40)  NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    product_id    INT NOT NULL,
    product_name  VARCHAR(150) NULL,               -- snapshot nama saat transaksi
    sku           VARCHAR(20)  NULL,
    image_url     VARCHAR(255) NULL,
    options       TEXT NULL,                       -- JSON: [{"label":"WARNA","value":"Hitam"}, ...]
    qty           INT NOT NULL,
    price         DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_order_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    status      VARCHAR(30) NOT NULL,
    note        VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL,
    method         ENUM('qris','va','card') NOT NULL,
    channel        VARCHAR(60) NOT NULL,
    channel_detail VARCHAR(80) NULL,
    transaction_id VARCHAR(40) NOT NULL,
    status         ENUM('pending','settled','failed','refunded') NOT NULL DEFAULT 'pending',
    amount         DECIMAL(14,2) NOT NULL,
    paid_at        DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LEDGER STOK (inti ERP: setiap perubahan stok tercatat)
-- ---------------------------------------------------------------------
CREATE TABLE stock_movements (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    product_id   INT NOT NULL,
    order_id     INT NULL,
    user_id      INT NULL,
    type         ENUM('initial','sale','restock','adjustment','cancel_return') NOT NULL,
    qty_change   INT NOT NULL,
    stock_after  INT NOT NULL,
    note         VARCHAR(255) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_prod  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_sm_order FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE SET NULL,
    INDEX idx_sm_prod (product_id, created_at)
) ENGINE=InnoDB;


-- =====================================================================
--  DATA AWAL
-- =====================================================================

-- Akun: admin@amboko.com / admin123  |  aditya.pratama@email.com / customer123  (contoh dari desain)
INSERT INTO users (name, email, password, role, phone, address, city, postal_code) VALUES
('Super Admin', 'admin@amboko.com', '$2y$10$uJm9IfHFzmZYuBWMJrEwYuxTMU3xtiv1/Q9ypXAeeytk2PSmLHvBW', 'admin', NULL, NULL, NULL, NULL),
('Aditya Pratama', 'aditya.pratama@email.com', '$2y$10$paGJWKwJiaz0eKVpdMvdL./lRTk5Z72Trl5K02n4kptsxClZ8zqeC', 'customer',
 '081298457211', 'Jl. Senopati No. 42B, Selong, Kebayoran Baru, Jakarta Selatan', 'Jakarta Selatan', '12110');

INSERT INTO categories (id, parent_id, name, slug, tag_label, group_label, sort_order) VALUES
(1, NULL, 'Trousers/pants', 'trousers-pants', 'TROUSERS / PANTS', 'BOTTOMS',  1),
(2, NULL, 'T-Shirt',        't-shirt',        'T-SHIRT',          'TOPS',      2),
(3, NULL, 'Long Pants',     'long-pants',     'LONG PANTS',       'BOTTOMS',   3),
(4, NULL, 'Jackets',        'jackets',        'JACKET',           'OUTERWEAR', 4),
(5, 1,    'Short Pants',    'short-pants',    'SHORT PANTS',      'BOTTOMS',   5);

-- ---------------------------------------------------------------------
-- PRODUK (7): 6 gaya di desain katalog + 1 "New Edition"
-- ---------------------------------------------------------------------
INSERT INTO products
(id, sku, slug, name, short_name, cart_name, checkout_name, category_id, price, old_price, sale_percent, badge_text,
 stock, material, availability, is_new, popularity, home_sort, description, image_url, home_image_url,
 rating_avg, review_total, rating_5, rating_4, rating_3, rating_2, rating_1, recommend_pct, recommend_note, ui_config)
VALUES
(1, 'AMB-01', 'loose-fit-jacket', 'Loose Fit Jacket', 'Loose fit Jacket', 'Loose Fit Jacket - Oversized Cut', 'Loose Fit Jacket',
 4, 120000, NULL, 15, NULL, 42, 'Heavy Terry', 'in_stock', 0, 100, 1,
 'Loose fit sweatshirt jacket in medium weight cotton-blend fabric with a generous, but not oversized silhouette, jersey lined, drawstring hood, dropped shoulders, and long sleeves. Wide ribbing at cuffs and soft, brush inside.',
 'catalog.jpg', 'Jacket1.png',
 4.9, 50, 46, 4, 0, 0, 0, 98, '49 dari 50 pembeli merasa puas',
 '{"label_style":"upper","color_style":"filled","review_style":"breakdown_pct","review_layout":"list","summary_title":"RATING KESELURUHAN","summary_sub":"Berdasarkan ulasan pembeli terverifikasi"}'),

(2, 'AMB-02', 'jacket-running', 'Jacket Running', 'Jacket Running', NULL, NULL,
 4, 120000, NULL, 10, NULL, 30, 'Ripstop', 'in_stock', 0, 80, NULL,
 'Jaket lari & windbreaker berbahan parasut microfiber ripstop tahan angin dan percikan air ringan (water-repellent). Dilengkapi ventilasi sirkulasi udara di punggung, aksen garis reflektif untuk lari malam hari, dan kantong beresleting aman.',
 'catalog7.png', 'catalog7.png',
 4.9, 50, 46, 4, 0, 0, 0, 98, '98% Pembeli Merekomendasikan',
 '{"label_style":"upper","color_style":"filled","review_style":"metrics","review_layout":"list","summary_title":"RINGKASAN RATING","summary_sub":"Berdasarkan ulasan pembeli terverifikasi"}'),

(3, 'AMB-04', 'kaos-heavyweight-graphic-tee', 'Kaos - Heavyweight Graphic Tee', 'T-Shirt', 'Kaos - Heavyweight Graphic Tee', 'Kaos Graphic Tee',
 2, 50000, 75000, 10, 'PROMO', 12, 'Combed Cotton', 'in_stock', 0, 95, 4,
 'Kaos kasual potongan boxy loose-fit dengan bahan 100% Combed Cotton 24s gramasi tebal yang adem dan menyerap keringat. Kerah rib kokoh tidak mudah melar, jahitan rantai rapi berstandar distro premium.',
 'catalog2.jpg', 'KAOS1.png',
 4.9, 95, 88, 7, 0, 0, 0, 0, NULL,
 '{"label_style":"title","review_style":"breakdown_count","review_layout":"cards","summary_sub":"Berdasarkan ulasan pembeli terverifikasi untuk produk AMBOKO.","complete_look":true,"marquee":"POPULAR THIS MONTH|SALE UP TO -15%|HEAVYWEIGHT COMBED 24S COTTON|BOXY STREETWEAR FIT|FAST NATIONWIDE DISPATCH","icons":true}'),

(4, 'AMB-06', 'celana-bahan', 'Celana Bahan', 'Celana Bahan', NULL, NULL,
 1, 80000, NULL, 0, NULL, 25, 'Micro-Twill', 'in_stock', 0, 75, NULL,
 'Celana bahan berpotongan loose relaxed dengan material semi-wool cotton blend yang jatuh, adem, tidak mudah kusut. Dilengkapi kancing eksklusif, resleting YKK, karet samping fleksibel untuk kenyamanan seharian.',
 'catalog6.jpg', 'CelanaBahan3.jpg',
 4.9, 68, 64, 4, 0, 0, 0, 97, NULL,
 '{"label_style":"title","review_style":"metrics","review_layout":"cards","review_meta":"Ukuran"}'),

(5, 'AMB-08', 'long-pants-street-sweatpants', 'Long Pants - Street Sweatpants', 'Long Pants', 'Celana Panjang - Street Sweatpants', 'Celana Panjang',
 3, 80000, NULL, 10, 'READY STOCK', 15, 'Heavy Terry', 'in_stock', 0, 85, 3,
 'Celana panjang relaxed sweatpants berbahan premium fleece cotton 280gsm. Lembut di bagian dalam, sirkulasi udara baik, jahitan ganda rapi, cocok untuk gaya streetwear santai maupun aktivitas harian.',
 'catalog3.jpg', 'CELANAPANJANG1.png',
 4.9, 65, 61, 4, 0, 0, 0, 98, NULL,
 '{"label_style":"title","review_style":"metrics","review_layout":"cards","summary_sub":"Berdasarkan ulasan pembeli terverifikasi","complete_look":true,"marquee":"POPULAR THIS MONTH|SALE UP TO -15%|UTILITY MINIMALIST FLEECE|FAST DELIVERY|HIGH DENSITY 280GSM COTTON"}'),

(6, 'AMB-07', 'short-pants-bicolor-street-shorts', 'Short Pants - Bicolor Street Shorts', 'Short Pants', NULL, NULL,
 5, 35000, 65000, 10, NULL, 15, 'Ripstop', 'in_stock', 0, 90, 2,
 'Celana pendek kasual streetwear dengan material poliester taslan/micro ripstop yang ringan, cepat kering (quick-dry), dan nyaman. Aksen blok warna kontras modern dengan saku samping fungsional. Dilengkapi tali pinggang elastis kokoh untuk fleksibilitas gaya urban harian.',
 'catalog4.jpg', 'CELANAPENDEK1.png',
 4.9, 112, 106, 6, 0, 0, 0, 98, NULL,
 '{"label_style":"upper","review_style":"metrics","review_layout":"cards_white","summary_sub":"Berdasarkan ulasan pembeli terverifikasi","highlights":["Regular Athletic Fit","Above-Knee Cut","Quick Dry Performance"],"marquee":"POPULAR THIS MONTH|QUICK DRY DESPO MICRO RIPSTOP|SALE UP TO -46%|STREETWEAR ESSENTIALS VOL. 04","rounded_marquee":true,"load_more":true,"full_name_reviews":true}'),

(7, 'AMB-09', 'mix-match-set', 'Mix & Match Set', 'Mix & Match Set', NULL, NULL,
 4, 200000, NULL, 0, NULL, 8, 'Heavy Terry', 'pre_order', 1, 60, NULL,
 'Paket New Edition: zip hoodie dan sweatpants dalam satu set yang bisa dipadu-padankan. Bahan fleece tebal, potongan relaxed, cocok dipakai sepasang maupun terpisah.',
 'catalog5.jpg', 'catalog5.jpg',
 0.0, 0, 0, 0, 0, 0, 0, 0, NULL,
 '{"label_style":"title","review_style":"none","review_layout":"cards"}');

-- Galeri foto (halaman produk)
INSERT INTO product_images (product_id, image_url, sort_order) VALUES
(1,'Jacket2.jpg',1),(1,'Jacket1.png',2),(1,'description loose jacket.png',3),(1,'description loose jacket2.png',4),
(1,'description loose jacket3.png',5),(1,'catalog.jpg',6),(1,'description loose jacket4.jpeg',7),
(1,'description loose jacket5.jpeg',8),(1,'description loose jacket6.jpeg',9),(1,'description loose jacket.jpeg',10),
(1,'37.jpeg',11),(1,'39.jpeg',12),(1,'41.jpeg',13),(1,'38.jpeg',14),(1,'40.jpeg',15),
(2,'JacketRun4.jpg',1),(2,'JacketRun5.jpg',2),(2,'JacketRun3.jpg',3),(2,'JacketRun2.jpg',4),(2,'JacketRun1.jpg',5),(2,'JacketRun6.jpg',6),(2,'catalog7.png',7),
(3,'KAOS1.png',1),(3,'KAOS2.jpg',2),(3,'KAOS3.jpg',3),(3,'KAOS4.jpg',4),(3,'KAOS5.jpg',5),(3,'catalog2.jpg',6),
(4,'CelanaBahan3.jpg',1),(4,'CelanaBahan1.jpg',2),(4,'CelanaBahan2.jpg',3),(4,'catalog6.jpg',4),
(5,'CelanaPanjang2.jpg',1),(5,'CELANAPANJANG1.png',2),(5,'CelanaPanjang3.jpg',3),(5,'catalog3.jpg',4),
(6,'CELANAPENDEK1.png',1),(6,'CELANAPENDEK2.jpg',2),(6,'CELANAPENDEK5.png',3),(6,'CELANAPENDEK3.jpg',4),(6,'CELANAPENDEK4.jpg',5),(6,'catalog4.jpg',6),
(7,'catalog5.jpg',1),(7,'Jacket1.png',2),(7,'CELANAPANJANG1.png',3);

-- Grup opsi ---------------------------------------------------------
INSERT INTO product_option_groups (id, product_id, title, cart_label, type, show_value, guide_label, sort_order) VALUES
-- 1 Loose Fit Jacket
(1, 1, 'Select Size',             'SIZE',     'pill',  0, NULL,         1),
(2, 1, 'Printing',                'PRINTING', 'pill',  0, NULL,         2),
(3, 1, 'Pilihan Warna / Color',   'WARNA',    'color', 1, NULL,         3),
-- 2 Jacket Running
(4, 2, 'Select Size',             'SIZE',     'pill',  0, 'Size Guide', 1),
(6, 2, 'Color',                   'WARNA',    'color', 0, NULL,         3),
-- 3 Kaos
(7, 3, 'Select Size',             'SIZE',     'pill',  0, 'Size Chart', 1),
(9, 3, 'Color / Pilihan Warna',   'WARNA',    'color', 1, NULL,         3),
-- 4 Celana Bahan
(10, 4, 'Select Size',            'SIZE',     'pill',  0, NULL,         1),
(12, 4, 'Color / Pilihan Warna',  'WARNA',    'color', 1, NULL,         3),
-- 5 Long Pants (Color, Size)
(13, 5, 'Color / Pilihan Warna',  'WARNA',    'color', 1, NULL,         1),
(14, 5, 'Select Size',            'SIZE',     'pill',  0, NULL,         2),
-- 6 Short Pants (Color, Size)
(16, 6, 'Color / Pilihan Warna',  'WARNA',    'color', 1, NULL,         1),
(17, 6, 'Select Size',            'SIZE',     'pill',  0, 'Size Guide', 2),
-- 7 Mix & Match Set
(19, 7, 'Select Size',            'SIZE',     'pill',  0, NULL,         1),
(20, 7, 'Color / Pilihan Warna',  'WARNA',    'color', 1, NULL,         2);

-- Nilai opsi --------------------------------------------------------
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
-- sizes (semua produk)
(1,'All Size',NULL,NULL,NULL,NULL,1,1),(1,'S',NULL,NULL,NULL,NULL,2,0),(1,'M',NULL,NULL,NULL,NULL,3,0),(1,'L',NULL,NULL,NULL,NULL,4,0),(1,'XL',NULL,NULL,NULL,NULL,5,0),
(4,'All Size',NULL,NULL,NULL,NULL,1,1),(4,'S',NULL,NULL,NULL,NULL,2,0),(4,'M',NULL,NULL,NULL,NULL,3,0),(4,'L',NULL,NULL,NULL,NULL,4,0),(4,'XL',NULL,NULL,NULL,NULL,5,0),
(7,'All Size',NULL,NULL,NULL,NULL,1,1),(7,'S',NULL,NULL,NULL,NULL,2,0),(7,'M',NULL,NULL,NULL,NULL,3,0),(7,'L',NULL,NULL,NULL,NULL,4,0),(7,'XL',NULL,NULL,NULL,NULL,5,0),
(10,'All Size',NULL,NULL,NULL,NULL,1,1),(10,'S',NULL,NULL,NULL,NULL,2,0),(10,'M',NULL,NULL,NULL,NULL,3,0),(10,'L',NULL,NULL,NULL,NULL,4,0),(10,'XL',NULL,NULL,NULL,NULL,5,0),
(14,'All Size',NULL,NULL,NULL,NULL,1,1),(14,'S',NULL,NULL,NULL,NULL,2,0),(14,'M',NULL,NULL,NULL,NULL,3,0),(14,'L',NULL,NULL,NULL,NULL,4,0),(14,'XL',NULL,NULL,NULL,NULL,5,0),
(17,'All Size',NULL,NULL,NULL,NULL,1,1),(17,'S',NULL,NULL,NULL,NULL,2,0),(17,'M',NULL,NULL,NULL,NULL,3,0),(17,'L',NULL,NULL,NULL,NULL,4,0),(17,'XL',NULL,NULL,NULL,NULL,5,0),
(19,'All Size',NULL,NULL,NULL,NULL,1,1),(19,'S',NULL,NULL,NULL,NULL,2,0),(19,'M',NULL,NULL,NULL,NULL,3,0),(19,'L',NULL,NULL,NULL,NULL,4,0),(19,'XL',NULL,NULL,NULL,NULL,5,0),
-- non-size pills
(2,'Custom',NULL,NULL,NULL,NULL,1,1),(2,'Random',NULL,NULL,NULL,NULL,2,0),
-- warna (setiap warna = satu foto produk di folder asset)
(3,'Hitam',NULL,'#1c1c1c',NULL,'Jacket2.jpg',1,1),
(3,'Abu-abu Muda',NULL,'#afb0b3',NULL,'Jacket1.png',2,0),
(3,'Abu-abu Tua',NULL,'#484648',NULL,'Jacket3.jpg',3,0),
(6,'All Black',NULL,'#1e1e1e',NULL,'JacketRun4.jpg',1,1),
(6,'Black Washed','Black Washed (Acid Wash)','#353436',NULL,'JacketRun5.jpg',2,0),
(6,'Navy',NULL,'#282e45',NULL,'JacketRun3.jpg',3,0),
(6,'Olive Green',NULL,'#565440',NULL,'JacketRun6.jpg',4,0),
(6,'Light Grey',NULL,'#babab5',NULL,'JacketRun2.jpg',5,0),
(6,'Digital Camo','Digital Camo (Grey)','#757471',NULL,'JacketRun1.jpg',6,0),
(9,'Maroon',NULL,'#5f272f',NULL,'KAOS1.png',1,1),
(9,'Olive Green',NULL,'#3e3f21',NULL,'KAOS2.jpg',2,0),
(9,'White',NULL,'#d1cfd3',NULL,'KAOS3.jpg',3,0),
(9,'Heather Grey',NULL,'#747579',NULL,'KAOS4.jpg',4,0),
(9,'Charcoal',NULL,'#5c5657',NULL,'KAOS5.jpg',5,0),
(12,'Black',NULL,'#272729',NULL,'CelanaBahan3.jpg',1,1),
(12,'Charcoal Grey',NULL,'#525257',NULL,'CelanaBahan1.jpg',2,0),
(12,'Olive Green',NULL,'#6d6f63',NULL,'CelanaBahan2.jpg',3,0),
(13,'Charcoal Black',NULL,'#2b2a2b',NULL,'CelanaPanjang2.jpg',1,1),
(13,'Heather Grey',NULL,'#cacbcd',NULL,'CELANAPANJANG1.png',2,0),
(13,'Cream',NULL,'#efe5d3',NULL,'CelanaPanjang3.jpg',3,0),
(16,'Black & White','Black / White Two-Tone','#202022','#ffffff','CELANAPENDEK1.png',1,1),
(16,'Solid Black',NULL,'#19191b',NULL,'CELANAPENDEK2.jpg',2,0),
(16,'Heather Grey','Heather Grey (Side Tape)','#8f8f92',NULL,'CELANAPENDEK3.jpg',3,0),
(16,'Navy','Navy (Black Waistband)','#22293f',NULL,'CELANAPENDEK4.jpg',4,0),
(16,'Grey',NULL,'#908f95',NULL,'CELANAPENDEK5.png',5,0),
(20,'Grey',NULL,'#9a9a9a',NULL,'catalog5.jpg',1,1);

-- Complete The Look
INSERT INTO product_relations (product_id, related_product_id, sort_order) VALUES
(3, 5, 1), (3, 1, 2), (3, 6, 3),
(5, 1, 1), (5, 3, 2), (5, 7, 3);

-- Bar ringkasan rating
INSERT INTO product_metrics (product_id, label, percent, note, sort_order) VALUES
(2, 'Tingkat Kepuasan', 98, '98% Pembeli Merekomendasikan', 1),
(4, '97% Pembeli Merekomendasikan', 97, 'Tingkat kepuasan sangat tinggi dari seluruh transaksi', 1),
(5, '98% Pembeli Merekomendasikan', 98, NULL, 1),
(6, 'Pembeli Merekomendasikan', 98, NULL, 1),
(6, 'Kesesuaian Ukuran & Bahan', 96, NULL, 2);

-- Ulasan (dari desain)
INSERT INTO product_reviews (product_id, reviewer_name, rating, comment, variant_info, is_verified, created_at) VALUES
(1, 'Bagas',  5, 'Bahannya bagus dan nyaman dipakai. Ukurannya juga sesuai dengan deskripsi.', NULL, 1, '2026-04-12 10:00:00'),
(1, 'Zoya',   5, 'Bajunya bagus, warnanya sesuai foto dan pengirimannya cepet.', NULL, 1, '2026-04-08 10:00:00'),
(1, 'Genta',  5, 'Suka banget sama bajunya! Jahitannya rapi dan ukurannya pas.', NULL, 1, '2026-03-26 10:00:00'),
(2, 'Fandi',  5, 'Bahannya ringan banget buat jogging sore! Nahan angin mantap dan ventilasi belakang bikin nggak gerah sama sekali.', NULL, 1, '2026-04-12 10:00:00'),
(2, 'Raka',   5, 'Garis reflektif 3M-nya sangat terang pas kena lampu motor malam hari. Desain minimalist streetwear tapi performanya beneran teknikal.', NULL, 1, '2026-04-08 10:00:00'),
(2, 'Dian',   5, 'Jahitannya sangat presisi, ritsleting halus dan waterproof. Pas di badan dengan potongan modern urban athletic.', NULL, 1, '2026-03-26 10:00:00'),
(3, 'Yoga',   5, 'Bahan katun 24s-nya beneran tebel dan halus banget, beda sama kaos harga 50 ribuan biasanya. Pas dipake jatuhnya boxy cakep banget, kerah lehernya juga padat nggak kendor abis dicuci.', 'Charcoal / L', 1, '2026-04-14 10:00:00'),
(3, 'Melani', 5, 'Warnanya sesuai foto katalog (ambil yang Olive Green), sablon grafisnya rapi presisi nggak lengket. Worth it parah buat daily wear streetwear!', 'Olive Green / M', 1, '2026-04-10 10:00:00'),
(3, 'Bayu',   5, 'Pengiriman kilat, jahitan rapi tanpa sisa benang. Cutting oversize-nya pas di badan TB 175 BB 70 pake L. Bakal repeat order warna maroon-nya.', 'Maroon / XL', 1, '2026-04-02 10:00:00'),
(4, 'Rizky',  5, 'Bahannya bagus dan nyaman dipakai. Jatuhnya loose pas banget di sepatu chunky, karet pinggang fleksibel bikin betah seharian dipakai ngantor mau pun nongkrong.', 'All Size', 1, '2026-04-12 10:00:00'),
(4, 'Fadhil', 5, 'Cuttingannya rapi, warna charcoal aslinya mewah. Pengiriman cepet banget 2 hari udah nyampe. Bakal repeat order warna olive green!', 'L', 1, '2026-04-08 10:00:00'),
(4, 'Dimas',  5, 'Suka banget sama celananya! Jahitannya solid rapi, lipatan depan tegas bikin postur keliatan lebih tinggi. Kualitas juara di harga 80rb.', 'M', 1, '2026-03-26 10:00:00'),
(5, 'Aris',   5, 'Bahannya bagus dan tebal 280gsm sesuai deskripsi. Fleece bagian dalam halus banget, nyaman dipakai seharian di ruangan ber-AC maupun buat hangout santai.', NULL, 1, '2026-04-14 10:00:00'),
(5, 'Nadia',  5, 'Celananya keren, potongan jatuh di pergelangan kakinya pas banget. Karet pinggangnya elastis kokoh nggak gampang kendur. Pengiriman cepet sampe!', NULL, 1, '2026-04-10 10:00:00'),
(5, 'Budi',   5, 'Suka banget sama potongannya! Jahitannya rapi, warna hitam pekatnya tidak gampang pudar sehabis dicuci. Worth it banget untuk harga Rp 80.000.', NULL, 1, '2026-03-28 10:00:00'),
(6, 'Kevin Pratama', 5, 'Bahannya beneran despo micro ripstop yang enteng dan adem banget. Buat harga Rp35.000 ini kualitasnya di luar ekspektasi, jahitannya rapi dan potongan bicolor-nya pas banget di atas lutut!', NULL, 1, '2026-04-12 10:00:00'),
(6, 'Siti Rahma',    5, 'Celananya bagus, warnanya persis foto katalog. Cepat kering pas dicuci dan packaging rapi banget. Pengiriman kilat sampai sebelum estimasi. Recommended seller!', NULL, 1, '2026-04-08 10:00:00'),
(6, 'Aldo Gunawan',  5, 'Suka banget sama potongannya! All Size pas banget buat lingkar pinggang 30-34 karena karetnya elastis dan ada talinya. Udah order warna solid black-nya juga.', NULL, 1, '2026-03-26 10:00:00');

INSERT INTO vouchers (code, type, value, min_purchase, max_discount, usage_limit, expires_at, is_active) VALUES
('AMBOKO10',  'percent', 10, 100000, 30000, NULL, '2026-12-31', 1),
('WELCOME15', 'fixed',   15000, 50000, NULL, 500,  '2026-12-31', 1),
('BLACKFRIDAY15', 'percent', 15, 150000, 50000, 200, '2026-11-30', 1);

-- Ledger stok awal
INSERT INTO stock_movements (product_id, type, qty_change, stock_after, note)
SELECT id, 'initial', stock, stock, 'Stok awal' FROM products;

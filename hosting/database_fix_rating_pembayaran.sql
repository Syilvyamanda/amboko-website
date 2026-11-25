-- =====================================================================
--  AMBOKO  -  Rating/ulasan sesuai pembelian nyata + pengaturan QRIS & VA
--  Import SEKALI lewat phpMyAdmin ke database `amboko_erp` (aman diulang).
--  Tidak menghapus data pesanan / pelanggan / ulasan.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
    k VARCHAR(40) PRIMARY KEY,
    v TEXT NULL
) ENGINE=InnoDB;

-- Kode awal Virtual Account & QRIS (ganti dari menu admin: Pembayaran)
INSERT IGNORE INTO site_settings (k, v) VALUES
('qris_image', ''),
('va_bca', '39358'), ('va_mandiri', '88908'), ('va_bni', '8277'), ('va_bri', '26215'), ('va_permata', '8528');

-- Hitung ulang ringkasan rating tiap produk: hanya ulasan dari pembeli
-- yang punya pesanan LUNAS berisi produk tersebut.
UPDATE products p SET
  review_total = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.user_id IS NOT NULL
      AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled')),
  rating_avg = COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM product_reviews r WHERE r.product_id = p.id AND r.user_id IS NOT NULL
      AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled')), 0),
  recommend_note = NULL;

UPDATE products p SET
  rating_5 = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.rating = 5 AND r.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled')),
  rating_4 = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.rating = 4 AND r.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled')),
  rating_3 = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.rating = 3 AND r.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled')),
  rating_2 = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.rating = 2 AND r.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled')),
  rating_1 = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.rating = 1 AND r.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = r.product_id AND o.user_id = r.user_id AND o.payment_status = 'paid' AND o.status <> 'cancelled'));

UPDATE products SET recommend_pct = IF(review_total = 0, 0, ROUND((rating_4 + rating_5) / review_total * 100));

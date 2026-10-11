-- ============================================================
-- Mini Project 3: Inventory Management System
-- Pertemuan 4: Relational Database + Advanced PHP
-- Database Schema & Initial Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS `inventory_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventory_management`;

-- Drop tables in reverse order of dependencies if recreating
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `product_suppliers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `user_profiles`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Table Users
CREATE TABLE `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table User Profiles (One-to-One with Users, FK + UNIQUE)
CREATE TABLE `user_profiles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table Categories (One-to-Many with Products)
CREATE TABLE `categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table Suppliers (Many-to-Many with Products)
CREATE TABLE `suppliers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `contact_name` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table Products (One-to-Many child from Categories)
CREATE TABLE `products` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `category_id` INT NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `minimum_stock` INT NOT NULL DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_products_name` (`name`),
    INDEX `idx_products_stock` (`stock`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table Product Suppliers (Junction table: Many-to-Many)
CREATE TABLE `product_suppliers` (
    `product_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `supplier_price` DECIMAL(12,2) NULL,
    PRIMARY KEY (`product_id`, `supplier_id`),
    CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_ps_supplier` FOREIGN KEY (`supplier_id`) 
        REFERENCES `suppliers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table Stock Movements (Transactions: Goods In / Out)
CREATE TABLE `stock_movements` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `type` ENUM('IN', 'OUT') NOT NULL,
    `quantity` INT NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mov_created_at` (`created_at`),
    INDEX `idx_mov_type` (`type`),
    CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users:
-- admin / admin123 ($2y$10$Ih.vAWv5UcgHOFVS7vjABeP.lpVKQaa1exSUDLQYEEhoSZajdriRK)
-- staff / staff123 ($2y$10$uy5s5hvZelVJlorsBThZsOrBvSzujqUGGwEhbz6zZ4gNnD/freL9K)
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`) VALUES
(1, 'admin', '$2y$10$Ih.vAWv5UcgHOFVS7vjABeP.lpVKQaa1exSUDLQYEEhoSZajdriRK', 'admin'),
(2, 'staff', '$2y$10$uy5s5hvZelVJlorsBThZsOrBvSzujqUGGwEhbz6zZ4gNnD/freL9K', 'staff');

-- User Profiles (1:1)
INSERT INTO `user_profiles` (`id`, `user_id`, `full_name`, `phone`, `address`) VALUES
(1, 1, 'Administrator Utama', '081234567890', 'Gedung IT Lantai 3, Jakarta'),
(2, 2, 'Ahmad Fauzi (Staff Gudang)', '089876543210', 'Komplek Pergudangan Blok B, Jakarta');

-- Categories (1:N)
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Perangkat Komputer & Laptop', 'Komputer desktop, laptop, monitor, dan periferal utama'),
(2, 'Aksesoris & Input Device', 'Keyboard, mouse, webcam, headset, dan aksesoris lainnya'),
(3, 'Perangkat Jaringan (Networking)', 'Router, switch hub, kabel LAN, dan access point'),
(4, 'Penyimpanan Data (Storage)', 'SSD, harddisk eksternal, flashdisk, dan micro SD'),
(5, 'Perlengkapan Kantor', 'Kertas HVS, tinta printer, binder, dan alat tulis kantor');

-- Suppliers (N:M)
INSERT INTO `suppliers` (`id`, `name`, `contact_name`, `phone`, `email`, `address`) VALUES
(1, 'PT Nusantara Komputindo', 'Budi Santoso', '021-5551234', 'budi@nusantaratelekom.co.id', 'Jl. Mangga Dua Raya No. 45, Jakarta Pusat'),
(2, 'CV Mega Tech Distribusi', 'Siti Aminah', '021-5555678', 'siti@megatechdist.com', 'Ruko Harco Mangga Dua Blok A/12, Jakarta Pusat'),
(3, 'PT Global Solusi Digital', 'Hendra Wijaya', '031-7788990', 'hendra@globalsolusi.co.id', 'Jl. Basuki Rahmat No. 88, Surabaya'),
(4, 'CV Sumber Logistik Mandiri', 'Rian Pratama', '022-4455667', 'rian@sumberlogistik.co.id', 'Kawasan Industri Cimahi No. 10, Bandung');

-- Products
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `price`, `stock`, `minimum_stock`) VALUES
(1, 1, 'PRD-LPT-001', 'Laptop Lenovo ThinkPad T14 Gen 3', 14500000.00, 8, 3),
(2, 1, 'PRD-MON-002', 'Monitor LG 24 Inch UltraGear FHD IPS', 2100000.00, 15, 5),
(3, 2, 'PRD-KBD-003', 'Keyboard Mechanical Keychron K2 Wireless', 1250000.00, 12, 4),
(4, 2, 'PRD-MOU-004', 'Mouse Logitech MX Master 3S Darkfield', 1450000.00, 4, 5), -- Low Stock
(5, 3, 'PRD-RTR-005', 'Router MikroTik RB750Gr3 hEX Gigabit', 875000.00, 20, 5),
(6, 3, 'PRD-SWT-006', 'Switch TP-Link Gigabit 16-Port SG1016D', 950000.00, 6, 3),
(7, 4, 'PRD-SSD-007', 'SSD Samsung 970 EVO Plus NVMe M.2 1TB', 1650000.00, 2, 5), -- Low Stock
(8, 4, 'PRD-SSD-008', 'SSD Kingston NV2 PCIe 4.0 NVMe 500GB', 620000.00, 25, 10),
(9, 2, 'PRD-WBC-009', 'Webcam Logitech C922 Pro Stream HD 1080P', 1350000.00, 0, 3), -- Out of Stock
(10, 5, 'PRD-INK-010', 'Tinta Printer Epson 003 Black 65ml', 85000.00, 50, 15);

-- Product Suppliers (Many-to-Many Junction with Supplier Price)
INSERT INTO `product_suppliers` (`product_id`, `supplier_id`, `supplier_price`) VALUES
(1, 1, 13000000.00),
(1, 3, 13200000.00),
(2, 1, 1850000.00),
(2, 2, 1800000.00),
(3, 2, 1050000.00),
(4, 2, 1200000.00),
(5, 3, 750000.00),
(6, 3, 820000.00),
(7, 1, 1400000.00),
(7, 2, 1380000.00),
(8, 2, 520000.00),
(9, 2, 1150000.00),
(10, 4, 68000.00);

-- Initial Stock Movements (Audit Trail for IN / OUT)
INSERT INTO `stock_movements` (`id`, `product_id`, `user_id`, `type`, `quantity`, `notes`, `created_at`) VALUES
(1, 1, 1, 'IN', 10, 'Pengadaan stok awal peresmian cabang', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(2, 1, 2, 'OUT', 2, 'Pengeluaran untuk divisi Operasional', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 2, 1, 'IN', 20, 'Pengadaan monitor stok baru', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 2, 2, 'OUT', 5, 'Pengiriman batch unit meja kerja tim frontend', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(5, 3, 1, 'IN', 15, 'Restock dari CV Mega Tech', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6, 3, 2, 'OUT', 3, 'Kebutuhan tim teknis', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7, 4, 1, 'IN', 10, 'Restock awal mouse', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(8, 4, 2, 'OUT', 6, 'Distribusi divisi kreatif & design', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(9, 5, 1, 'IN', 25, 'Pengadaan perangkat router MikroTik', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(10, 5, 2, 'OUT', 5, 'Instalasi jaringan server baru', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(11, 7, 1, 'IN', 10, 'Pembelian SSD Samsung dari PT Nusantara', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(12, 7, 2, 'OUT', 8, 'Upgrade SSD workstation kantor', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(13, 10, 1, 'IN', 60, 'Stok rutin tinta printer Epson', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(14, 10, 2, 'OUT', 10, 'Pengisian tinta printer lantai 1 & 2', DATE_SUB(NOW(), INTERVAL 1 DAY));

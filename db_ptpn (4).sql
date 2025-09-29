-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 24, 2025 at 11:55 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_ptpn`
--

-- --------------------------------------------------------

--
-- Table structure for table `alat_panen`
--

CREATE TABLE `alat_panen` (
  `id` int UNSIGNED NOT NULL,
  `kebun_id` int UNSIGNED DEFAULT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `unit_id` int NOT NULL,
  `jenis_alat` varchar(120) NOT NULL,
  `stok_awal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mutasi_masuk` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mutasi_keluar` decimal(12,2) NOT NULL DEFAULT '0.00',
  `dipakai` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stok_akhir` decimal(12,2) NOT NULL DEFAULT '0.00',
  `krani_afdeling` varchar(120) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `alat_panen`
--

INSERT INTO `alat_panen` (`id`, `kebun_id`, `bulan`, `tahun`, `unit_id`, `jenis_alat`, `stok_awal`, `mutasi_masuk`, `mutasi_keluar`, `dipakai`, `stok_akhir`, `krani_afdeling`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 2, 'Januari', 2025, 1, 'Dodos', 10000.00, 988.00, 1000.00, 1000.00, 8988.00, 'ddd', '', '2025-09-16 17:52:11', '2025-09-22 13:24:57'),
(2, 2, 'Januari', 2025, 1, 'Dodos', 1000.00, 100.00, 50.00, 80.00, 970.00, 'Afd I', 'Penggunaan rutin', '2025-09-17 17:33:16', '2025-09-22 13:25:05'),
(3, NULL, 'Februari', 2025, 2, 'Egrek', 1200.00, 150.00, 60.00, 90.00, 1200.00, 'Afd II', '', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(4, NULL, 'Maret', 2025, 3, 'Gancu', 800.00, 80.00, 40.00, 70.00, 770.00, 'Afd III', '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(5, NULL, 'April', 2025, 4, 'Dodos', 900.00, 100.00, 50.00, 60.00, 890.00, 'Afd IV', '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(6, NULL, 'Mei', 2025, 5, 'Egrek', 1100.00, 90.00, 40.00, 70.00, 1080.00, 'Afd V', '', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(7, NULL, 'Juni', 2025, 6, 'Gancu', 950.00, 70.00, 30.00, 50.00, 940.00, 'Afd VI', '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(8, NULL, 'Juli', 2025, 7, 'Dodos', 1000.00, 60.00, 20.00, 40.00, 1000.00, 'Afd VII', '', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(9, NULL, 'Agustus', 2025, 8, 'Egrek', 1050.00, 80.00, 30.00, 60.00, 1040.00, 'Afd VIII', '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(10, NULL, 'September', 2025, 9, 'Gancu', 1200.00, 120.00, 50.00, 100.00, 1170.00, 'Afd IX', '', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(11, NULL, 'Oktober', 2025, 10, 'Dodos', 900.00, 90.00, 40.00, 70.00, 880.00, 'Bibitan', '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(12, 3, 'Januari', 2025, 2, 'Dodos', 1000.00, 12.00, 10.00, 40.00, 962.00, 'tes', '', '2025-09-19 13:56:47', '2025-09-22 13:25:11');

-- --------------------------------------------------------

--
-- Table structure for table `angkutan_pupuk`
--

CREATE TABLE `angkutan_pupuk` (
  `id` int UNSIGNED NOT NULL,
  `kebun_kode` varchar(32) DEFAULT NULL,
  `gudang_asal` varchar(120) NOT NULL,
  `unit_tujuan_id` int DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jenis_pupuk` varchar(120) NOT NULL,
  `jumlah` decimal(14,2) NOT NULL DEFAULT '0.00',
  `nomor_do` varchar(60) DEFAULT NULL,
  `supir` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `angkutan_pupuk`
--

INSERT INTO `angkutan_pupuk` (`id`, `kebun_kode`, `gudang_asal`, `unit_tujuan_id`, `tanggal`, `jenis_pupuk`, `jumlah`, `nomor_do`, `supir`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Salteng', 2, '2025-09-18', 'Organik', 12.00, '111222', '12', '2025-09-18 13:25:43', '2025-09-18 13:25:43'),
(2, NULL, 'Tes', 1, '2025-09-21', 'Urea', 100.00, '0002', 'Rendy', '2025-09-21 09:15:30', '2025-09-22 12:51:05'),
(3, NULL, 'Salteng', 1, '2025-09-22', 'NPK 15.15.6.4', 12.00, '002', 'Rendy', '2025-09-22 12:51:31', '2025-09-22 12:57:17');

-- --------------------------------------------------------

--
-- Table structure for table `angkutan_pupuk_organik`
--

CREATE TABLE `angkutan_pupuk_organik` (
  `id` int UNSIGNED NOT NULL,
  `kebun_id` int UNSIGNED DEFAULT NULL,
  `gudang_asal` varchar(150) NOT NULL,
  `unit_tujuan_id` int DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jenis_pupuk` varchar(100) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL DEFAULT '0.00',
  `nomor_do` varchar(100) DEFAULT NULL,
  `supir` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `angkutan_pupuk_organik`
--

INSERT INTO `angkutan_pupuk_organik` (`id`, `kebun_id`, `gudang_asal`, `unit_tujuan_id`, `tanggal`, `jenis_pupuk`, `jumlah`, `nomor_do`, `supir`, `created_at`, `updated_at`) VALUES
(1, 1, 'Salteng', 3, '2025-09-14', 'Dolomite', 12.00, '111222', 'Rendy', '2025-09-14 16:03:15', '2025-09-22 13:14:05'),
(2, NULL, 'Gudang O', NULL, '2025-02-01', 'Organik', 30.00, 'DOO-001', 'Samsul', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(3, NULL, 'Gudang O', NULL, '2025-02-02', 'Organik', 40.00, 'DOO-002', 'Taufik', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(4, NULL, 'Gudang O', NULL, '2025-02-03', 'Organik', 35.00, 'DOO-003', 'Hari', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(5, 3, 'Gudang O', 3, '2025-02-04', 'NPK 15.15.6.4', 45.00, 'DOO-004', 'Udin', '2025-09-17 17:33:16', '2025-09-22 13:15:09'),
(6, 3, 'Gudang O', 1, '2025-02-05', 'NPK 15.15.6.4', 50.00, 'DOO-005', 'Imam', '2025-09-17 17:33:16', '2025-09-22 13:15:00'),
(7, 3, 'Gudang O', 3, '2025-02-06', 'Dolomite', 25.00, 'DOO-006', 'Rian', '2025-09-17 17:33:16', '2025-09-22 13:14:51'),
(8, 3, 'Gudang O', 4, '2025-02-07', 'NPK 15.15.6.4', 30.00, 'DOO-007', 'Yoga', '2025-09-17 17:33:16', '2025-09-22 13:14:42'),
(9, 2, 'Gudang O', 2, '2025-02-08', 'NPK 15.15.6.4', 40.00, 'DOO-008', 'Eko', '2025-09-17 17:33:16', '2025-09-22 13:14:33'),
(10, 2, 'Gudang O', 3, '2025-02-09', 'NPK 15.15.6.4', 38.00, 'DOO-009', 'Andre', '2025-09-17 17:33:16', '2025-09-22 13:14:24'),
(11, 2, 'Gudang O', 1, '2025-02-10', 'Dolomite', 42.00, 'DOO-010', 'Fajar', '2025-09-17 17:33:16', '2025-09-22 13:14:14');

-- --------------------------------------------------------

--
-- Table structure for table `lm76`
--

CREATE TABLE `lm76` (
  `id` int UNSIGNED NOT NULL,
  `kebun_id` int DEFAULT NULL,
  `unit_id` int NOT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `tt` varchar(20) DEFAULT NULL,
  `blok` varchar(40) DEFAULT NULL,
  `luas_ha` decimal(10,2) DEFAULT NULL,
  `jumlah_pohon` int DEFAULT NULL,
  `varietas` varchar(80) DEFAULT NULL,
  `prod_bi_realisasi` decimal(12,2) DEFAULT '0.00',
  `prod_bi_anggaran` decimal(12,2) DEFAULT '0.00',
  `prod_sd_realisasi` decimal(12,2) DEFAULT '0.00',
  `prod_sd_anggaran` decimal(12,2) DEFAULT '0.00',
  `jumlah_tandan_bi` int DEFAULT '0',
  `pstb_ton_ha_bi` decimal(12,2) DEFAULT '0.00',
  `pstb_ton_ha_tl` decimal(12,2) DEFAULT '0.00',
  `panen_hk_realisasi` decimal(12,2) DEFAULT '0.00',
  `panen_ha_bi` decimal(12,2) DEFAULT '0.00',
  `panen_ha_sd` decimal(12,2) DEFAULT '0.00',
  `frek_panen_bi` int DEFAULT '0',
  `frek_panen_sd` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lm76`
--

INSERT INTO `lm76` (`id`, `kebun_id`, `unit_id`, `bulan`, `tahun`, `tt`, `blok`, `luas_ha`, `jumlah_pohon`, `varietas`, `prod_bi_realisasi`, `prod_bi_anggaran`, `prod_sd_realisasi`, `prod_sd_anggaran`, `jumlah_tandan_bi`, `pstb_ton_ha_bi`, `pstb_ton_ha_tl`, `panen_hk_realisasi`, `panen_ha_bi`, `panen_ha_sd`, `frek_panen_bi`, `frek_panen_sd`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Januari', 2025, '01', 'D3', 12.00, 12, 'Organik', 12.00, 12.00, 12.00, 12.00, 12, 12.00, 12.00, 12.00, 12.00, 12.00, 12, 12, '2025-09-16 07:31:40', '2025-09-23 14:04:41'),
(2, 2, 1, 'Januari', 2025, '01', 'A1', 10.00, 100, 'Var1', 20.00, 22.00, 20.00, 22.00, 200, 2.00, 2.10, 50.00, 5.00, 5.00, 2, 2, '2025-09-17 17:35:59', '2025-09-23 14:05:59'),
(3, NULL, 2, 'Februari', 2025, '02', 'B1', 12.00, 110, 'Var2', 25.00, 26.00, 25.00, 26.00, 220, 2.10, 2.20, 55.00, 6.00, 6.00, 2, 2, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(4, NULL, 3, 'Maret', 2025, '03', 'C1', 11.00, 90, 'Var3', 18.00, 20.00, 18.00, 20.00, 180, 1.90, 2.00, 48.00, 5.00, 5.00, 2, 2, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(5, NULL, 4, 'April', 2025, '04', 'D1', 14.00, 130, 'Var4', 28.00, 30.00, 28.00, 30.00, 260, 2.20, 2.30, 60.00, 7.00, 7.00, 3, 3, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(6, NULL, 5, 'Mei', 2025, '05', 'E1', 13.00, 120, 'Var5', 26.00, 28.00, 26.00, 28.00, 240, 2.10, 2.20, 58.00, 6.00, 6.00, 2, 2, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(7, NULL, 6, 'Juni', 2025, '06', 'F1', 15.00, 140, 'Var6', 30.00, 32.00, 30.00, 32.00, 280, 2.30, 2.40, 62.00, 8.00, 8.00, 3, 3, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(8, NULL, 7, 'Juli', 2025, '07', 'G1', 9.00, 80, 'Var7', 16.00, 18.00, 16.00, 18.00, 160, 1.80, 1.90, 45.00, 4.00, 4.00, 2, 2, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(9, NULL, 8, 'Agustus', 2025, '08', 'H1', 17.00, 150, 'Var8', 32.00, 34.00, 32.00, 34.00, 300, 2.40, 2.50, 65.00, 9.00, 9.00, 3, 3, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(10, NULL, 9, 'September', 2025, '09', 'I1', 16.00, 135, 'Var9', 29.00, 31.00, 29.00, 31.00, 270, 2.30, 2.40, 63.00, 8.00, 8.00, 3, 3, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(11, NULL, 10, 'Oktober', 2025, '10', 'J1', 18.00, 160, 'Var10', 35.00, 36.00, 35.00, 36.00, 320, 2.50, 2.60, 70.00, 10.00, 10.00, 3, 3, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(13, NULL, 2, 'Februari', 2025, '02', 'B1', 12.00, 110, 'Var2', 25.00, 26.00, 25.00, 26.00, 220, 2.10, 2.20, 55.00, 6.00, 6.00, 2, 2, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(14, NULL, 3, 'Maret', 2025, '03', 'C1', 11.00, 90, 'Var3', 18.00, 20.00, 18.00, 20.00, 180, 1.90, 2.00, 48.00, 5.00, 5.00, 2, 2, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(15, NULL, 4, 'April', 2025, '04', 'D1', 14.00, 130, 'Var4', 28.00, 30.00, 28.00, 30.00, 260, 2.20, 2.30, 60.00, 7.00, 7.00, 3, 3, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(16, NULL, 5, 'Mei', 2025, '05', 'E1', 13.00, 120, 'Var5', 26.00, 28.00, 26.00, 28.00, 240, 2.10, 2.20, 58.00, 6.00, 6.00, 2, 2, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(17, NULL, 6, 'Juni', 2025, '06', 'F1', 15.00, 140, 'Var6', 30.00, 32.00, 30.00, 32.00, 280, 2.30, 2.40, 62.00, 8.00, 8.00, 3, 3, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(18, NULL, 7, 'Juli', 2025, '07', 'G1', 9.00, 80, 'Var7', 16.00, 18.00, 16.00, 18.00, 160, 1.80, 1.90, 45.00, 4.00, 4.00, 2, 2, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(19, NULL, 8, 'Agustus', 2025, '08', 'H1', 17.00, 150, 'Var8', 32.00, 34.00, 32.00, 34.00, 300, 2.40, 2.50, 65.00, 9.00, 9.00, 3, 3, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(20, NULL, 9, 'September', 2025, '09', 'I1', 16.00, 135, 'Var9', 29.00, 31.00, 29.00, 31.00, 270, 2.30, 2.40, 63.00, 8.00, 8.00, 3, 3, '2025-09-17 17:36:32', '2025-09-17 17:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `lm77`
--

CREATE TABLE `lm77` (
  `id` int UNSIGNED NOT NULL,
  `unit_id` int NOT NULL,
  `kebun_kode` varchar(50) DEFAULT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `tt` varchar(20) DEFAULT NULL,
  `blok` varchar(40) DEFAULT NULL,
  `luas_ha` decimal(10,2) DEFAULT NULL,
  `jumlah_pohon` int DEFAULT NULL,
  `pohon_ha` decimal(10,2) DEFAULT NULL,
  `var_prod_bi` decimal(8,2) DEFAULT '0.00',
  `var_prod_sd` decimal(8,2) DEFAULT '0.00',
  `jtandan_per_pohon_bi` decimal(10,4) DEFAULT '0.0000',
  `jtandan_per_pohon_sd` decimal(10,4) DEFAULT '0.0000',
  `prod_tonha_bi` decimal(12,2) DEFAULT '0.00',
  `prod_tonha_sd_thi` decimal(12,2) DEFAULT '0.00',
  `prod_tonha_sd_tl` decimal(12,2) DEFAULT '0.00',
  `btr_bi` decimal(10,2) DEFAULT '0.00',
  `btr_sd_thi` decimal(10,2) DEFAULT '0.00',
  `btr_sd_tl` decimal(10,2) DEFAULT '0.00',
  `basis_borong_kg_hk` decimal(10,2) DEFAULT '0.00',
  `prestasi_kg_hk_bi` decimal(12,2) DEFAULT '0.00',
  `prestasi_kg_hk_sd` decimal(12,2) DEFAULT '0.00',
  `prestasi_tandan_hk_bi` decimal(12,2) DEFAULT '0.00',
  `prestasi_tandan_hk_sd` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lm77`
--

INSERT INTO `lm77` (`id`, `unit_id`, `kebun_kode`, `bulan`, `tahun`, `tt`, `blok`, `luas_ha`, `jumlah_pohon`, `pohon_ha`, `var_prod_bi`, `var_prod_sd`, `jtandan_per_pohon_bi`, `jtandan_per_pohon_sd`, `prod_tonha_bi`, `prod_tonha_sd_thi`, `prod_tonha_sd_tl`, `btr_bi`, `btr_sd_thi`, `btr_sd_tl`, `basis_borong_kg_hk`, `prestasi_kg_hk_bi`, `prestasi_kg_hk_sd`, `prestasi_tandan_hk_bi`, `prestasi_tandan_hk_sd`, `created_at`, `updated_at`) VALUES
(1, 1, 'KB01', 'Januari', 2025, '2025', '0011', 2.00, 1, 1.00, 2.00, 2.00, 1.0000, 2.0000, 1.00, 2.00, 1.00, 2.00, 1.00, 2.00, 1.00, 2.00, 1.00, 2.00, 1.00, '2025-09-16 17:54:42', '2025-09-23 14:41:46'),
(2, 1, 'KB01', 'Januari', 2025, '2025', '0011', 10.00, 100, 10.00, 2.00, 2.10, 2.0000, 2.1000, 2.00, 2.10, 2.00, 1.00, 1.10, 1.00, 50.00, 55.00, 55.00, 10.00, 11.00, '2025-09-17 17:35:59', '2025-09-23 14:53:30'),
(3, 2, NULL, 'Februari', 2025, '02', 'B1', 12.00, 110, 9.20, 2.10, 2.20, 2.1000, 2.2000, 2.10, 2.20, 2.10, 1.10, 1.20, 1.10, 52.00, 56.00, 56.00, 11.00, 12.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(4, 3, NULL, 'Maret', 2025, '03', 'C1', 11.00, 90, 8.50, 1.90, 2.00, 1.9000, 2.0000, 1.90, 2.00, 1.90, 0.90, 1.00, 0.90, 48.00, 50.00, 50.00, 9.00, 10.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(5, 4, NULL, 'April', 2025, '04', 'D1', 14.00, 130, 9.30, 2.20, 2.30, 2.2000, 2.3000, 2.20, 2.30, 2.20, 1.20, 1.30, 1.20, 55.00, 60.00, 60.00, 12.00, 13.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(6, 5, NULL, 'Mei', 2025, '05', 'E1', 13.00, 120, 9.20, 2.00, 2.10, 2.0000, 2.1000, 2.00, 2.10, 2.00, 1.00, 1.10, 1.00, 53.00, 57.00, 57.00, 11.00, 12.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(7, 6, NULL, 'Juni', 2025, '06', 'F1', 15.00, 140, 9.30, 2.30, 2.40, 2.3000, 2.4000, 2.30, 2.40, 2.30, 1.30, 1.40, 1.30, 60.00, 65.00, 65.00, 13.00, 14.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(8, 7, NULL, 'Juli', 2025, '07', 'G1', 9.00, 80, 8.90, 1.80, 1.90, 1.8000, 1.9000, 1.80, 1.90, 1.80, 0.80, 0.90, 0.80, 45.00, 48.00, 48.00, 8.00, 9.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(9, 8, NULL, 'Agustus', 2025, '08', 'H1', 17.00, 150, 8.80, 2.40, 2.50, 2.4000, 2.5000, 2.40, 2.50, 2.40, 1.40, 1.50, 1.40, 65.00, 70.00, 70.00, 14.00, 15.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(10, 9, NULL, 'September', 2025, '09', 'I1', 16.00, 135, 8.40, 2.30, 2.40, 2.3000, 2.4000, 2.30, 2.40, 2.30, 1.20, 1.30, 1.20, 63.00, 68.00, 68.00, 13.00, 14.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(11, 10, NULL, 'Oktober', 2025, '10', 'J1', 18.00, 160, 8.90, 2.50, 2.60, 2.5000, 2.6000, 2.50, 2.60, 2.50, 1.50, 1.60, 1.50, 70.00, 75.00, 75.00, 15.00, 16.00, '2025-09-17 17:35:59', '2025-09-17 17:35:59'),
(12, 1, NULL, 'Januari', 2025, '01', 'A1', 10.00, 100, 10.00, 2.00, 2.10, 2.0000, 2.1000, 2.00, 2.10, 2.00, 1.00, 1.10, 1.00, 50.00, 55.00, 55.00, 10.00, 11.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(13, 2, NULL, 'Februari', 2025, '02', 'B1', 12.00, 110, 9.20, 2.10, 2.20, 2.1000, 2.2000, 2.10, 2.20, 2.10, 1.10, 1.20, 1.10, 52.00, 56.00, 56.00, 11.00, 12.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(14, 3, NULL, 'Maret', 2025, '03', 'C1', 11.00, 90, 8.50, 1.90, 2.00, 1.9000, 2.0000, 1.90, 2.00, 1.90, 0.90, 1.00, 0.90, 48.00, 50.00, 50.00, 9.00, 10.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(15, 4, NULL, 'April', 2025, '04', 'D1', 14.00, 130, 9.30, 2.20, 2.30, 2.2000, 2.3000, 2.20, 2.30, 2.20, 1.20, 1.30, 1.20, 55.00, 60.00, 60.00, 12.00, 13.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(16, 5, NULL, 'Mei', 2025, '05', 'E1', 13.00, 120, 9.20, 2.00, 2.10, 2.0000, 2.1000, 2.00, 2.10, 2.00, 1.00, 1.10, 1.00, 53.00, 57.00, 57.00, 11.00, 12.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(17, 6, NULL, 'Juni', 2025, '06', 'F1', 15.00, 140, 9.30, 2.30, 2.40, 2.3000, 2.4000, 2.30, 2.40, 2.30, 1.30, 1.40, 1.30, 60.00, 65.00, 65.00, 13.00, 14.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(18, 7, NULL, 'Juli', 2025, '07', 'G1', 9.00, 80, 8.90, 1.80, 1.90, 1.8000, 1.9000, 1.80, 1.90, 1.80, 0.80, 0.90, 0.80, 45.00, 48.00, 48.00, 8.00, 9.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(19, 8, NULL, 'Agustus', 2025, '08', 'H1', 17.00, 150, 8.80, 2.40, 2.50, 2.4000, 2.5000, 2.40, 2.50, 2.40, 1.40, 1.50, 1.40, 65.00, 70.00, 70.00, 14.00, 15.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(20, 9, NULL, 'September', 2025, '09', 'I1', 16.00, 135, 8.40, 2.30, 2.40, 2.3000, 2.4000, 2.30, 2.40, 2.30, 1.20, 1.30, 1.20, 63.00, 68.00, 68.00, 13.00, 14.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(21, 10, NULL, 'Oktober', 2025, '10', 'J1', 18.00, 160, 8.90, 2.50, 2.60, 2.5000, 2.6000, 2.50, 2.60, 2.50, 1.50, 1.60, 1.50, 70.00, 75.00, 75.00, 15.00, 16.00, '2025-09-17 17:36:32', '2025-09-17 17:36:32'),
(22, 3, 'KB02', 'Januari', 2025, '11', '11', 11.00, 11, 11.00, 11.00, 11.00, 1111.0000, 11.0000, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, '2025-09-23 14:20:34', '2025-09-23 14:20:34'),
(23, 1, 'KB01', 'Januari', 2025, '2025', '0011', 12.00, 12, 12.00, 1212.00, 12.00, 12.0000, 12.0000, 12.00, 12.00, 1212.00, 12.00, 12.00, 12.00, 12.00, 12.00, 12.00, 12.00, 12.00, '2025-09-23 14:53:11', '2025-09-23 14:53:11');

-- --------------------------------------------------------

--
-- Table structure for table `lm_biaya`
--

CREATE TABLE `lm_biaya` (
  `id` int UNSIGNED NOT NULL,
  `kode_aktivitas_id` int UNSIGNED NOT NULL,
  `jenis_pekerjaan_id` int UNSIGNED DEFAULT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `unit_id` int NOT NULL,
  `kebun_id` int DEFAULT NULL,
  `rencana_bi` decimal(14,2) NOT NULL DEFAULT '0.00',
  `realisasi_bi` decimal(14,2) NOT NULL DEFAULT '0.00',
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lm_biaya`
--

INSERT INTO `lm_biaya` (`id`, `kode_aktivitas_id`, `jenis_pekerjaan_id`, `bulan`, `tahun`, `unit_id`, `kebun_id`, `rencana_bi`, `realisasi_bi`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'April', 2025, 1, 2, 12.00, 12.00, 'tes', '2025-09-18 18:15:04', '2025-09-23 14:19:20'),
(2, 1, 1, 'Januari', 2025, 6, 1, 12.00, 12.00, 'tes', '2025-09-23 14:19:11', '2025-09-23 14:57:33');

-- --------------------------------------------------------

--
-- Table structure for table `md_anggaran`
--

CREATE TABLE `md_anggaran` (
  `id` int UNSIGNED NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `unit_id` int NOT NULL,
  `kode_aktivitas_id` int UNSIGNED NOT NULL,
  `pupuk_id` int UNSIGNED DEFAULT NULL,
  `anggaran_bulan_ini` decimal(14,2) NOT NULL DEFAULT '0.00',
  `anggaran_tahun` decimal(14,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `md_bahan_kimia`
--

CREATE TABLE `md_bahan_kimia` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(32) NOT NULL,
  `nama_bahan` varchar(120) NOT NULL,
  `satuan_id` int UNSIGNED NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_bahan_kimia`
--

INSERT INTO `md_bahan_kimia` (`id`, `kode`, `nama_bahan`, `satuan_id`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, '001', 'Textil 1', 5, 'Bahan 1', '2025-09-22 08:25:40', '2025-09-22 08:25:40');

-- --------------------------------------------------------

--
-- Table structure for table `md_blok`
--

CREATE TABLE `md_blok` (
  `id` int NOT NULL,
  `kode` varchar(40) NOT NULL,
  `unit_id` int DEFAULT NULL,
  `tahun_tanam` smallint UNSIGNED DEFAULT NULL,
  `luas_ha` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_blok`
--

INSERT INTO `md_blok` (`id`, `kode`, `unit_id`, `tahun_tanam`, `luas_ha`) VALUES
(1, '0011', 1, 2025, 12000.00),
(2, '002', 2, 2025, 2000.00);

-- --------------------------------------------------------

--
-- Table structure for table `md_fisik`
--

CREATE TABLE `md_fisik` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(60) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_fisik`
--

INSERT INTO `md_fisik` (`id`, `nama`, `created_at`, `updated_at`) VALUES
(1, 'Ha', '2025-09-17 07:00:37', '2025-09-17 07:00:37'),
(2, 'Pkk', '2025-09-17 07:00:37', '2025-09-17 07:00:37'),
(3, 'Unit', '2025-09-17 07:00:37', '2025-09-17 07:00:37'),
(4, 'Stand', '2025-09-17 07:00:37', '2025-09-17 07:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `md_jabatan`
--

CREATE TABLE `md_jabatan` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_jabatan`
--

INSERT INTO `md_jabatan` (`id`, `nama`) VALUES
(9, 'Administrasi'),
(2, 'Asisten'),
(4, 'Krani'),
(1, 'Manager'),
(3, 'Mandor'),
(6, 'Operator'),
(7, 'Pemanen'),
(8, 'Pengawas'),
(10, 'Security'),
(5, 'Staff');

-- --------------------------------------------------------

--
-- Table structure for table `md_jenis_alat_panen`
--

CREATE TABLE `md_jenis_alat_panen` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(120) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_jenis_alat_panen`
--

INSERT INTO `md_jenis_alat_panen` (`id`, `nama`, `keterangan`) VALUES
(1, 'Egrek', NULL),
(2, 'Dodos', NULL),
(3, 'Gancu', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `md_jenis_pekerjaan`
--

CREATE TABLE `md_jenis_pekerjaan` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(120) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_jenis_pekerjaan`
--

INSERT INTO `md_jenis_pekerjaan` (`id`, `nama`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Karyawan', 'tes', '2025-09-17 09:33:48', '2025-09-17 09:33:48');

-- --------------------------------------------------------

--
-- Table structure for table `md_kebun`
--

CREATE TABLE `md_kebun` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(16) NOT NULL,
  `nama_kebun` varchar(100) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_kebun`
--

INSERT INTO `md_kebun` (`id`, `kode`, `nama_kebun`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'KB01', 'Kebun Andalas', 'Wilayah Barat', '2025-09-22 08:22:30', '2025-09-22 08:22:30'),
(2, 'KB02', 'Kebun Bahari', 'Dekat pelabuhan', '2025-09-22 08:22:30', '2025-09-22 08:22:30'),
(3, 'KB03', 'Kebun Cendana', 'Dataran tinggi', '2025-09-22 08:22:30', '2025-09-22 08:22:30'),
(4, 'KB04', 'Kebun Damar', 'Wilayah selatan', '2025-09-22 08:22:30', '2025-09-22 08:22:30');

-- --------------------------------------------------------

--
-- Table structure for table `md_kode_aktivitas`
--

CREATE TABLE `md_kode_aktivitas` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(120) NOT NULL,
  `no_sap_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_kode_aktivitas`
--

INSERT INTO `md_kode_aktivitas` (`id`, `kode`, `nama`, `no_sap_id`) VALUES
(1, '0011', 'Muhammad Rendy Krisna', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `md_mobil`
--

CREATE TABLE `md_mobil` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_mobil`
--

INSERT INTO `md_mobil` (`id`, `kode`, `nama`) VALUES
(1, 'TS', 'Truck/Service'),
(2, 'TP', 'Transport Pupuk');

-- --------------------------------------------------------

--
-- Table structure for table `md_pupuk`
--

CREATE TABLE `md_pupuk` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(120) NOT NULL,
  `satuan_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_pupuk`
--

INSERT INTO `md_pupuk` (`id`, `nama`, `satuan_id`) VALUES
(1, 'Urea', NULL),
(2, 'NPK 15.15.6.4', NULL),
(3, 'Dolomite', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `md_sap`
--

CREATE TABLE `md_sap` (
  `id` int UNSIGNED NOT NULL,
  `no_sap` varchar(50) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `md_satuan`
--

CREATE TABLE `md_satuan` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(40) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_satuan`
--

INSERT INTO `md_satuan` (`id`, `nama`, `created_at`, `updated_at`) VALUES
(5, 'Kg', '2025-09-22 08:24:09', '2025-09-22 08:24:09'),
(6, 'Liter', '2025-09-22 08:24:14', '2025-09-22 08:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `md_tahun_tanam`
--

CREATE TABLE `md_tahun_tanam` (
  `id` int UNSIGNED NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `keterangan` varchar(120) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_tahun_tanam`
--

INSERT INTO `md_tahun_tanam` (`id`, `tahun`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 2025, 'tahun tanam pertama', '2025-09-22 07:53:14', '2025-09-22 07:53:14');

-- --------------------------------------------------------

--
-- Table structure for table `md_tenaga`
--

CREATE TABLE `md_tenaga` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `md_tenaga`
--

INSERT INTO `md_tenaga` (`id`, `kode`, `nama`) VALUES
(1, 'TS', 'Tetap Staff'),
(2, 'KNG', 'KHL/Kontrak Harian'),
(3, 'PKWT', 'PKWT'),
(4, 'TP', 'Tetap Pemanen');

-- --------------------------------------------------------

--
-- Table structure for table `menabur_pupuk`
--

CREATE TABLE `menabur_pupuk` (
  `id` int UNSIGNED NOT NULL,
  `kebun_kode` varchar(32) DEFAULT NULL,
  `unit_id` int UNSIGNED DEFAULT NULL,
  `afdeling` varchar(120) NOT NULL,
  `blok` varchar(120) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_pupuk` varchar(120) NOT NULL,
  `jumlah` decimal(14,2) NOT NULL DEFAULT '0.00',
  `dosis` decimal(10,2) DEFAULT NULL,
  `luas` decimal(10,2) NOT NULL DEFAULT '0.00',
  `invt_pokok` int UNSIGNED NOT NULL DEFAULT '0',
  `catatan` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `menabur_pupuk`
--

INSERT INTO `menabur_pupuk` (`id`, `kebun_kode`, `unit_id`, `afdeling`, `blok`, `tanggal`, `jenis_pupuk`, `jumlah`, `dosis`, `luas`, `invt_pokok`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 'KB04', 1, 'Afdeling-I', '0011', '2025-09-18', 'Dolomite', 12.00, 10.00, 500.00, 200, 'jdiowjdiw', '2025-09-18 13:25:21', '2025-09-22 13:08:14'),
(2, 'KB03', 1, 'Afdeling-I', '0011', '2025-09-21', 'Dolomite', 12.00, 12.00, 300.00, 200, 'tes dlu', '2025-09-21 07:24:07', '2025-09-22 13:08:04'),
(3, 'KB02', 1, 'Afdeling-I', '0011', '2025-09-21', 'Dolomite', 100.00, 12.00, 2.00, 20, 'tes aja', '2025-09-21 07:26:35', '2025-09-22 13:07:53'),
(4, 'KB01', 2, 'Afdeling-II', '002', '2025-09-21', 'Dolomite', 1000.00, 300.00, 2000.00, 20, 'tes aja', '2025-09-21 09:53:18', '2025-09-22 13:07:22'),
(5, 'KB01', 2, 'Afdeling-II', '002', '2025-09-22', 'Dolomite', 12.00, 12.00, 12.00, 12, 'diwoiwd', '2025-09-22 13:08:59', '2025-09-22 13:08:59');

-- --------------------------------------------------------

--
-- Table structure for table `menabur_pupuk_organik`
--

CREATE TABLE `menabur_pupuk_organik` (
  `id` int UNSIGNED NOT NULL,
  `kebun_id` int UNSIGNED DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `blok` varchar(100) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_pupuk` varchar(100) NOT NULL,
  `dosis` decimal(10,2) DEFAULT NULL,
  `jumlah` decimal(12,2) NOT NULL DEFAULT '0.00',
  `luas` decimal(12,2) NOT NULL DEFAULT '0.00',
  `invt_pokok` int DEFAULT '0',
  `catatan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `menabur_pupuk_organik`
--

INSERT INTO `menabur_pupuk_organik` (`id`, `kebun_id`, `unit_id`, `blok`, `tanggal`, `jenis_pupuk`, `dosis`, `jumlah`, `luas`, `invt_pokok`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 4, 1, '0011', '2025-09-14', 'Dolomite', 12.00, 12.00, 12.00, 12, 'tes', '2025-09-14 16:02:50', '2025-09-22 13:13:51'),
(2, 3, 1, '0011', '2025-09-21', 'Dolomite', 12.00, 1200.00, 200.00, 200, 'hallo', '2025-09-21 09:44:55', '2025-09-22 13:13:39'),
(3, 1, 1, '0011', '2025-09-22', 'NPK 15.15.6.4', 12.00, 200.00, 10000.00, 200, 'tes', '2025-09-22 13:13:05', '2025-09-22 13:13:31');

-- --------------------------------------------------------

--
-- Table structure for table `pemakaian_bahan_kimia`
--

CREATE TABLE `pemakaian_bahan_kimia` (
  `id` int UNSIGNED NOT NULL,
  `no_dokumen` varchar(80) NOT NULL,
  `unit_id` int DEFAULT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `nama_bahan` varchar(255) NOT NULL,
  `jenis_pekerjaan` varchar(120) NOT NULL,
  `jlh_diminta` decimal(18,2) NOT NULL DEFAULT '0.00',
  `jlh_fisik` decimal(18,2) NOT NULL DEFAULT '0.00',
  `dokumen_path` varchar(255) DEFAULT NULL,
  `dokumen_name` varchar(255) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pemakaian_bahan_kimia`
--

INSERT INTO `pemakaian_bahan_kimia` (`id`, `no_dokumen`, `unit_id`, `bulan`, `tahun`, `nama_bahan`, `jenis_pekerjaan`, `jlh_diminta`, `jlh_fisik`, `dokumen_path`, `dokumen_name`, `keterangan`, `created_at`, `updated_at`) VALUES
(20, '001', 2, 'Januari', 2025, 'Textil 1', 'Karyawan', 10.00, 10.00, '../uploads/pemakaian/PK_20250922_092545_d1d382.pdf', 'StokGudang_20250918_182826.pdf', '[Fisik: Pkk] [Kebun: Kebun Bahari] tess', '2025-09-22 09:25:45', '2025-09-22 09:25:45');

-- --------------------------------------------------------

--
-- Table structure for table `pemeliharaan`
--

CREATE TABLE `pemeliharaan` (
  `id` int UNSIGNED NOT NULL,
  `kategori` enum('TU','TBM','TM','BIBIT_PN','BIBIT_MN') NOT NULL,
  `jenis_pekerjaan` varchar(255) NOT NULL,
  `tenaga` varchar(100) DEFAULT NULL,
  `unit_id` int UNSIGNED DEFAULT NULL,
  `afdeling` varchar(100) DEFAULT NULL,
  `rayon` varchar(100) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') DEFAULT NULL,
  `tahun` smallint UNSIGNED DEFAULT NULL,
  `rencana` decimal(10,2) DEFAULT '0.00',
  `realisasi` decimal(10,2) DEFAULT '0.00',
  `status` enum('Berjalan','Selesai','Tertunda') NOT NULL DEFAULT 'Berjalan',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pemeliharaan`
--

INSERT INTO `pemeliharaan` (`id`, `kategori`, `jenis_pekerjaan`, `tenaga`, `unit_id`, `afdeling`, `rayon`, `tanggal`, `bulan`, `tahun`, `rencana`, `realisasi`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TU', 'Babat', NULL, NULL, 'Afreading 1', '2', '2025-09-14', 'Januari', 2026, 2.00, 3.00, 'Berjalan', '2025-09-14 15:30:37', '2025-09-14 15:30:37'),
(2, 'TU', 'Babat', NULL, NULL, 'Afdeling-I', '1', '2025-04-01', 'April', 2025, 10.00, 9.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(3, 'TBM', 'Semprot', NULL, NULL, 'Afdeling-II', '2', '2025-04-02', 'April', 2025, 12.00, 11.00, 'Selesai', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(4, 'TM', 'Pruning', NULL, NULL, 'Afdeling-III', '3', '2025-04-03', 'April', 2025, 8.00, 7.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(5, 'TU', 'Pemupukan', NULL, NULL, 'Afdeling-IV', '4', '2025-04-04', 'April', 2025, 15.00, 14.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(6, 'TBM', 'Babat', NULL, NULL, 'Afdeling-V', '5', '2025-04-05', 'April', 2025, 9.00, 8.00, 'Selesai', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(7, 'TM', 'Semprot', NULL, NULL, 'Afdeling-VI', '6', '2025-04-06', 'April', 2025, 11.00, 10.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(8, 'TU', 'Pruning', NULL, NULL, 'Afdeling-VII', '7', '2025-04-07', 'April', 2025, 7.00, 6.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(9, 'TBM', 'Pemupukan', NULL, NULL, 'Afdeling-VIII', '8', '2025-04-08', 'April', 2025, 13.00, 12.00, 'Selesai', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(10, 'TM', 'Babat', NULL, NULL, 'Afdeling-IX', '9', '2025-04-09', 'April', 2025, 14.00, 13.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(11, 'TU', 'Semprot', NULL, NULL, 'Bibitan', '10', '2025-04-10', 'April', 2025, 10.00, 9.00, 'Berjalan', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(12, 'TU', 'tes', NULL, 2, NULL, '2', '2025-09-18', 'Januari', 2025, 2.00, 2.00, 'Berjalan', '2025-09-18 09:27:50', '2025-09-18 09:27:50'),
(13, 'TU', 'Karyawan', 'KHL/Kontrak Harian', 1, NULL, '2', '2025-09-21', 'Januari', 2025, 2.00, 12.00, 'Berjalan', '2025-09-21 06:17:04', '2025-09-21 06:17:04'),
(14, 'TU', 'Karyawan', 'Tetap Staff', 1, NULL, '22', '2025-09-21', 'Januari', 2025, 12.00, 7.00, 'Berjalan', '2025-09-21 09:12:04', '2025-09-21 09:12:04'),
(15, 'BIBIT_MN', 'Karyawan', 'KHL/Kontrak Harian', 1, NULL, '22', '2025-09-22', 'Januari', 2025, 10.00, 12.00, 'Berjalan', '2025-09-22 09:02:58', '2025-09-22 09:02:58'),
(16, 'TU', 'Karyawan', 'KHL/Kontrak Harian', 1, NULL, 'Kebun Andalas', '2025-09-22', 'Januari', 2025, 10.00, 12.00, 'Berjalan', '2025-09-22 09:10:27', '2025-09-22 09:10:27'),
(17, 'TBM', 'Karyawan', 'PKWT', 2, NULL, 'Kebun Cendana', '2025-09-22', 'Januari', 2025, 9.00, 12.00, 'Berjalan', '2025-09-22 09:11:13', '2025-09-22 09:11:26');

-- --------------------------------------------------------

--
-- Table structure for table `permintaan_bahan`
--

CREATE TABLE `permintaan_bahan` (
  `id` int NOT NULL,
  `kebun_id` int UNSIGNED DEFAULT NULL,
  `no_dokumen` varchar(100) NOT NULL,
  `unit_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `blok` text,
  `pokok` int DEFAULT NULL,
  `dosis_norma` varchar(100) DEFAULT NULL,
  `jumlah_diminta` decimal(12,2) NOT NULL DEFAULT '0.00',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `permintaan_bahan`
--

INSERT INTO `permintaan_bahan` (`id`, `kebun_id`, `no_dokumen`, `unit_id`, `tanggal`, `blok`, `pokok`, `dosis_norma`, `jumlah_diminta`, `keterangan`, `created_at`, `updated_at`) VALUES
(2, NULL, 'DOC-001', 1, '2025-01-05', 'A1', 120, '2.5', 300.00, 'Penggunaan pupuk urea', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(3, NULL, 'DOC-002', 2, '2025-01-08', 'B2', 90, '1.8', 160.00, 'Pemeliharaan blok', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(4, NULL, 'DOC-003', 3, '2025-01-12', 'C3', 75, '2.0', 150.00, 'Kebutuhan awal tahun', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(5, NULL, 'DOC-004', 1, '2025-01-20', 'A2', 110, '2.2', 250.00, 'Penambahan stok', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(6, NULL, 'DOC-005', 4, '2025-01-25', 'D1', 130, '2.7', 320.00, 'Pengajuan rutin', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(7, NULL, 'DOC-006', 2, '2025-02-02', 'B3', 85, '1.9', 170.00, 'Pemupukan', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(8, NULL, 'DOC-007', 3, '2025-02-06', 'C1', 95, '2.1', 200.00, 'Tambahan stok', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(9, NULL, 'DOC-008', 5, '2025-02-10', 'E2', 100, '2.3', 210.00, 'Keperluan harian', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(10, NULL, 'DOC-009', 1, '2025-02-14', 'A3', 140, '2.6', 360.00, 'Pupuk lanjutan', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(11, NULL, 'DOC-010', 4, '2025-02-20', 'D2', 120, '2.4', 280.00, 'Stok gudang habis', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(12, NULL, 'DOC-011', 2, '2025-03-01', 'B1', 105, '2.0', 220.00, 'Perawatan bulanan', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(13, NULL, 'DOC-012', 3, '2025-03-05', 'C4', 90, '1.7', 160.00, 'Pengajuan tambahan', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(14, NULL, 'DOC-013', 5, '2025-03-10', 'E1', 125, '2.5', 300.00, 'Kegiatan panen', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(15, NULL, 'DOC-014', 1, '2025-03-15', 'A4', 135, '2.8', 340.00, 'Pupuk tambahan blok', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(16, NULL, 'DOC-015', 2, '2025-03-20', 'B4', 80, '1.9', 150.00, 'Pemeliharaan pokok', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(17, NULL, 'DOC-016', 4, '2025-03-25', 'D3', 100, '2.2', 220.00, 'Pengajuan standar', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(18, NULL, 'DOC-017', 3, '2025-04-02', 'C2', 115, '2.4', 260.00, 'Pengajuan harian', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(19, NULL, 'DOC-018', 5, '2025-04-07', 'E3', 95, '2.0', 180.00, 'Penggunaan bibitan', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(20, NULL, 'DOC-019', 1, '2025-04-12', 'A5', 145, '2.9', 370.00, 'Stok cadangan', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(21, NULL, 'DOC-020', 2, '2025-04-18', 'B5', 110, '2.1', 230.00, 'Keperluan mendesak', '2025-09-15 16:31:01', '2025-09-15 16:31:01'),
(22, NULL, 'PB-001', 1, '2025-05-01', 'A1', 100, '2.0', 200.00, 'Pengajuan rutin', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(23, NULL, 'PB-002', 2, '2025-05-02', 'B1', 120, '2.5', 300.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(24, NULL, 'PB-003', 3, '2025-05-03', 'C1', 90, '1.8', 180.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(25, NULL, 'PB-004', 4, '2025-05-04', 'D1', 110, '2.2', 240.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(26, NULL, 'PB-005', 5, '2025-05-05', 'E1', 130, '2.7', 350.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(27, NULL, 'PB-006', 6, '2025-05-06', 'F1', 80, '1.9', 150.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(28, NULL, 'PB-007', 7, '2025-05-07', 'G1', 95, '2.1', 200.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(29, NULL, 'PB-008', 8, '2025-05-08', 'H1', 105, '2.3', 250.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(30, NULL, 'PB-009', 9, '2025-05-09', 'I1', 140, '2.6', 360.00, '-', '2025-09-17 17:33:16', '2025-09-17 17:33:16'),
(31, 3, 'PB-010', 10, '2025-05-10', 'J1', 115, '2.4', 280.00, '-', '2025-09-17 17:33:16', '2025-09-22 13:38:09'),
(32, 1, '002', 3, '2025-09-22', 'D13', 12, '12', 12.00, 'yesyesyes', '2025-09-22 13:38:33', '2025-09-22 13:38:33');

-- --------------------------------------------------------

--
-- Table structure for table `stok_gudang`
--

CREATE TABLE `stok_gudang` (
  `id` int UNSIGNED NOT NULL,
  `kebun_id` int UNSIGNED NOT NULL,
  `bahan_id` int UNSIGNED NOT NULL,
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` smallint UNSIGNED NOT NULL,
  `stok_awal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mutasi_masuk` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mutasi_keluar` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pasokan` decimal(12,2) NOT NULL DEFAULT '0.00',
  `dipakai` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stok_gudang`
--

INSERT INTO `stok_gudang` (`id`, `kebun_id`, `bahan_id`, `bulan`, `tahun`, `stok_awal`, `mutasi_masuk`, `mutasi_keluar`, `pasokan`, `dipakai`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Februari', 2025, 100.00, 20.00, 10.00, 20.00, 10.00, '2025-09-22 08:31:48', '2025-09-22 08:32:00');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int NOT NULL,
  `nama_unit` varchar(100) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `nama_unit`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Afdeling-I', 'Afdeling I', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(2, 'Afdeling-II', 'Afdeling II', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(3, 'Afdeling-III', 'Afdeling III', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(4, 'Afdeling-IV', 'Afdeling IV', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(5, 'Afdeling-V', 'Afdeling V', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(6, 'Afdeling-VI', 'Afdeling VI', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(7, 'Afdeling-VII', 'Afdeling VII', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(8, 'Afdeling-VIII', 'Afdeling VIII', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(9, 'Afdeling-IX', 'Afdeling IX', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(10, 'Bibitan', 'Unit Bibitan', '2025-09-15 16:01:37', '2025-09-15 16:01:37'),
(11, 'Sei Rokan 1', 'Kebun Utama', '2025-09-17 09:35:45', '2025-09-17 09:35:45'),
(12, 'Sei Rokan 2', 'Kebun Cadangan', '2025-09-17 09:35:45', '2025-09-17 09:35:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `nama_lengkap` varchar(50) DEFAULT NULL,
  `nik` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `nama_lengkap`, `nik`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Rendy', 'Muhammad rendy krisna', '1837128947', '$2y$10$Hcp5uCDPNtntiyZdoDYaT.i1nO5EBU365lCgWoP/IvL2yYFZzWfMa', 'admin', '2025-09-13 15:09:06', '2025-09-19 16:00:37'),
(2, 'Rendy', 'Rendy Krisna', '37812372891731', '$2y$10$FL6B/2Z7BKoHvcGRdbwOzur7xcfPzkbKQz54rqLQ2pH3cl9XXtsz6', 'admin', '2025-09-17 10:28:32', '2025-09-19 16:00:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alat_panen`
--
ALTER TABLE `alat_panen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_periode` (`tahun`,`bulan`),
  ADD KEY `idx_unit` (`unit_id`),
  ADD KEY `idx_alatpanen_kebun` (`kebun_id`);

--
-- Indexes for table `angkutan_pupuk`
--
ALTER TABLE `angkutan_pupuk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_angkut_unit_tujuan` (`unit_tujuan_id`),
  ADD KEY `idx_angkut_kebun_kode` (`kebun_kode`);

--
-- Indexes for table `angkutan_pupuk_organik`
--
ALTER TABLE `angkutan_pupuk_organik`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `fk_aporganik_unit` (`unit_tujuan_id`),
  ADD KEY `idx_aporganik_kebun` (`kebun_id`);

--
-- Indexes for table `lm76`
--
ALTER TABLE `lm76`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_periode` (`bulan`,`tahun`),
  ADD KEY `fk_lm76_unit` (`unit_id`);

--
-- Indexes for table `lm77`
--
ALTER TABLE `lm77`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_periode` (`bulan`,`tahun`),
  ADD KEY `fk_lm77_unit` (`unit_id`),
  ADD KEY `idx_lm77_kebun_kode` (`kebun_kode`);

--
-- Indexes for table `lm_biaya`
--
ALTER TABLE `lm_biaya`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_periode_unit` (`tahun`,`bulan`,`unit_id`),
  ADD KEY `fk_lm_biaya_aktiv` (`kode_aktivitas_id`),
  ADD KEY `fk_lm_biaya_jenis` (`jenis_pekerjaan_id`),
  ADD KEY `fk_lm_biaya_unit` (`unit_id`);

--
-- Indexes for table `md_anggaran`
--
ALTER TABLE `md_anggaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_anggaran` (`tahun`,`bulan`,`unit_id`,`kode_aktivitas_id`,`pupuk_id`),
  ADD KEY `fk_anggaran_unit` (`unit_id`),
  ADD KEY `fk_anggaran_aktiv` (`kode_aktivitas_id`),
  ADD KEY `fk_anggaran_pupuk` (`pupuk_id`);

--
-- Indexes for table `md_bahan_kimia`
--
ALTER TABLE `md_bahan_kimia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bahan_kode` (`kode`),
  ADD UNIQUE KEY `uq_bahan_nama` (`nama_bahan`),
  ADD KEY `idx_bahan_satuan` (`satuan_id`);

--
-- Indexes for table `md_blok`
--
ALTER TABLE `md_blok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_unit_kode` (`unit_id`,`kode`);

--
-- Indexes for table `md_fisik`
--
ALTER TABLE `md_fisik`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `md_jabatan`
--
ALTER TABLE `md_jabatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `md_jenis_alat_panen`
--
ALTER TABLE `md_jenis_alat_panen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `md_jenis_pekerjaan`
--
ALTER TABLE `md_jenis_pekerjaan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `md_kebun`
--
ALTER TABLE `md_kebun`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kebun_kode` (`kode`),
  ADD UNIQUE KEY `uq_kebun_nama` (`nama_kebun`);

--
-- Indexes for table `md_kode_aktivitas`
--
ALTER TABLE `md_kode_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`),
  ADD KEY `fk_kodeact_sap` (`no_sap_id`);

--
-- Indexes for table `md_mobil`
--
ALTER TABLE `md_mobil`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `md_pupuk`
--
ALTER TABLE `md_pupuk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`),
  ADD KEY `fk_pupuk_satuan` (`satuan_id`);

--
-- Indexes for table `md_sap`
--
ALTER TABLE `md_sap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_sap` (`no_sap`);

--
-- Indexes for table `md_satuan`
--
ALTER TABLE `md_satuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `md_tahun_tanam`
--
ALTER TABLE `md_tahun_tanam`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tahun` (`tahun`);

--
-- Indexes for table `md_tenaga`
--
ALTER TABLE `md_tenaga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `menabur_pupuk`
--
ALTER TABLE `menabur_pupuk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_menabur_unit` (`unit_id`),
  ADD KEY `idx_menabur_unit_id` (`unit_id`),
  ADD KEY `idx_menabur_kebun_kode` (`kebun_kode`);

--
-- Indexes for table `menabur_pupuk_organik`
--
ALTER TABLE `menabur_pupuk_organik`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `fk_mporganik_unit` (`unit_id`),
  ADD KEY `idx_mporganik_kebun` (`kebun_id`);

--
-- Indexes for table `pemakaian_bahan_kimia`
--
ALTER TABLE `pemakaian_bahan_kimia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_no_dokumen` (`no_dokumen`),
  ADD KEY `idx_periode` (`bulan`,`tahun`),
  ADD KEY `idx_cari` (`no_dokumen`,`nama_bahan`,`jenis_pekerjaan`),
  ADD KEY `idx_pemakaian_unit_id` (`unit_id`);

--
-- Indexes for table `pemeliharaan`
--
ALTER TABLE `pemeliharaan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permintaan_bahan`
--
ALTER TABLE `permintaan_bahan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_dokumen` (`no_dokumen`),
  ADD KEY `fk_unit` (`unit_id`),
  ADD KEY `idx_pb_kebun` (`kebun_id`);

--
-- Indexes for table `stok_gudang`
--
ALTER TABLE `stok_gudang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sg_periode` (`kebun_id`,`bahan_id`,`bulan`,`tahun`),
  ADD KEY `idx_sg_kebun` (`kebun_id`),
  ADD KEY `idx_sg_bahan` (`bahan_id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_unit` (`nama_unit`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`nik`),
  ADD UNIQUE KEY `nik` (`nik`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alat_panen`
--
ALTER TABLE `alat_panen`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `angkutan_pupuk`
--
ALTER TABLE `angkutan_pupuk`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `angkutan_pupuk_organik`
--
ALTER TABLE `angkutan_pupuk_organik`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `lm76`
--
ALTER TABLE `lm76`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `lm77`
--
ALTER TABLE `lm77`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lm_biaya`
--
ALTER TABLE `lm_biaya`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `md_anggaran`
--
ALTER TABLE `md_anggaran`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `md_bahan_kimia`
--
ALTER TABLE `md_bahan_kimia`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `md_blok`
--
ALTER TABLE `md_blok`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `md_fisik`
--
ALTER TABLE `md_fisik`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `md_jabatan`
--
ALTER TABLE `md_jabatan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `md_jenis_alat_panen`
--
ALTER TABLE `md_jenis_alat_panen`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `md_jenis_pekerjaan`
--
ALTER TABLE `md_jenis_pekerjaan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `md_kebun`
--
ALTER TABLE `md_kebun`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `md_kode_aktivitas`
--
ALTER TABLE `md_kode_aktivitas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `md_mobil`
--
ALTER TABLE `md_mobil`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `md_pupuk`
--
ALTER TABLE `md_pupuk`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `md_sap`
--
ALTER TABLE `md_sap`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `md_satuan`
--
ALTER TABLE `md_satuan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `md_tahun_tanam`
--
ALTER TABLE `md_tahun_tanam`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `md_tenaga`
--
ALTER TABLE `md_tenaga`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `menabur_pupuk`
--
ALTER TABLE `menabur_pupuk`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `menabur_pupuk_organik`
--
ALTER TABLE `menabur_pupuk_organik`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pemakaian_bahan_kimia`
--
ALTER TABLE `pemakaian_bahan_kimia`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `pemeliharaan`
--
ALTER TABLE `pemeliharaan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `permintaan_bahan`
--
ALTER TABLE `permintaan_bahan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `stok_gudang`
--
ALTER TABLE `stok_gudang`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alat_panen`
--
ALTER TABLE `alat_panen`
  ADD CONSTRAINT `fk_alatpanen_kebun` FOREIGN KEY (`kebun_id`) REFERENCES `md_kebun` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alatpanen_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `angkutan_pupuk`
--
ALTER TABLE `angkutan_pupuk`
  ADD CONSTRAINT `fk_angkut_kebun_kode` FOREIGN KEY (`kebun_kode`) REFERENCES `md_kebun` (`kode`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_angkut_unit_tujuan` FOREIGN KEY (`unit_tujuan_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `angkutan_pupuk_organik`
--
ALTER TABLE `angkutan_pupuk_organik`
  ADD CONSTRAINT `fk_aporganik_kebun` FOREIGN KEY (`kebun_id`) REFERENCES `md_kebun` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_aporganik_unit` FOREIGN KEY (`unit_tujuan_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `lm76`
--
ALTER TABLE `lm76`
  ADD CONSTRAINT `fk_lm76_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lm77`
--
ALTER TABLE `lm77`
  ADD CONSTRAINT `fk_lm77_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lm_biaya`
--
ALTER TABLE `lm_biaya`
  ADD CONSTRAINT `fk_lm_biaya_aktiv` FOREIGN KEY (`kode_aktivitas_id`) REFERENCES `md_kode_aktivitas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lm_biaya_jenis` FOREIGN KEY (`jenis_pekerjaan_id`) REFERENCES `md_jenis_pekerjaan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lm_biaya_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `md_anggaran`
--
ALTER TABLE `md_anggaran`
  ADD CONSTRAINT `fk_anggaran_aktiv` FOREIGN KEY (`kode_aktivitas_id`) REFERENCES `md_kode_aktivitas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_anggaran_pupuk` FOREIGN KEY (`pupuk_id`) REFERENCES `md_pupuk` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_anggaran_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `md_bahan_kimia`
--
ALTER TABLE `md_bahan_kimia`
  ADD CONSTRAINT `fk_bahan_satuan` FOREIGN KEY (`satuan_id`) REFERENCES `md_satuan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `md_blok`
--
ALTER TABLE `md_blok`
  ADD CONSTRAINT `fk_blok_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `md_kode_aktivitas`
--
ALTER TABLE `md_kode_aktivitas`
  ADD CONSTRAINT `fk_kodeact_sap` FOREIGN KEY (`no_sap_id`) REFERENCES `md_sap` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `md_pupuk`
--
ALTER TABLE `md_pupuk`
  ADD CONSTRAINT `fk_pupuk_satuan` FOREIGN KEY (`satuan_id`) REFERENCES `md_satuan` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `menabur_pupuk`
--
ALTER TABLE `menabur_pupuk`
  ADD CONSTRAINT `fk_menabur_kebun_kode` FOREIGN KEY (`kebun_kode`) REFERENCES `md_kebun` (`kode`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `menabur_pupuk_organik`
--
ALTER TABLE `menabur_pupuk_organik`
  ADD CONSTRAINT `fk_mporganik_kebun` FOREIGN KEY (`kebun_id`) REFERENCES `md_kebun` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mporganik_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `pemakaian_bahan_kimia`
--
ALTER TABLE `pemakaian_bahan_kimia`
  ADD CONSTRAINT `fk_pemakaian_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `permintaan_bahan`
--
ALTER TABLE `permintaan_bahan`
  ADD CONSTRAINT `fk_pb_kebun` FOREIGN KEY (`kebun_id`) REFERENCES `md_kebun` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stok_gudang`
--
ALTER TABLE `stok_gudang`
  ADD CONSTRAINT `fk_sg_bahan` FOREIGN KEY (`bahan_id`) REFERENCES `md_bahan_kimia` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sg_kebun` FOREIGN KEY (`kebun_id`) REFERENCES `md_kebun` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

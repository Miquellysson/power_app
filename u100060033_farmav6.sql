-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 30/10/2025 às 07:01
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u100060033_farmav6`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_path`, `active`, `sort_order`, `created_at`) VALUES
(1, 'Analgésicos', 'analgesicos', 'Medicamentos para alívio de dores e febres', NULL, 1, 1, '2025-08-26 08:23:23'),
(2, 'Antibióticos', 'antibioticos', 'Medicamentos para combate a infecções', NULL, 1, 0, '2025-08-26 08:23:23'),
(3, 'Anti-inflamatórios', 'anti-inflamatorios', 'Medicamentos para redução de inflamações', NULL, 1, 0, '2025-08-26 08:23:23'),
(4, 'Suplementos', 'suplementos', 'Vitaminas e suplementos alimentares', NULL, 1, 0, '2025-08-26 08:23:23'),
(5, 'Digestivos', 'digestivos', 'Medicamentos para problemas digestivos', NULL, 1, 0, '2025-08-26 08:23:23'),
(6, 'Cardiovasculares', 'cardiovasculares', 'Medicamentos para coração e pressão', NULL, 1, 0, '2025-08-26 08:23:23'),
(7, 'Respiratórios', 'respiratorios', 'Medicamentos para problemas respiratórios', NULL, 1, 0, '2025-08-26 08:23:23'),
(8, 'ANTICONCEPCIONAL', 'dermatologicos', 'Medicamentos para pele', NULL, 1, 0, '2025-08-26 08:23:23'),
(49, 'CONTROLADOS', '', NULL, NULL, 1, 0, '2025-09-19 17:35:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'BR',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `city`, `state`, `zipcode`, `country`, `created_at`) VALUES
(1, 'Mike', 'mike@mmlins.com.br', '82996669740', 'ssads', 'sdasd', 'asda', '57100000', 'BR', '2025-08-26 18:16:32'),
(2, 'Miquellysson Meira lins', 'mike@mmlins.com.br', '82996669740', '03 Travessa intendente Julio Calheiros', 'Rio Largo', 'AL', '57100-000', 'BR', '2025-08-27 19:04:11'),
(3, 'Tainon', 'tainon@arkaleads.com', '5584728r7483', 'Hdjshdsjjf', 'Dhejjcjej', 'Jcjehdh', '847483748', 'BR', '2025-08-29 11:47:49'),
(4, 'Mike', 'admin@farmafacil.com', '82996669740', 'travessa intedente julio calheiros', 'maceio', 'al', '57100000', 'BR', '2025-08-31 07:32:52'),
(5, 'Marcelo Souza', 'supervisormarcelohbs@gamil.com', '8568794719', '408 laurel st', 'Beverly', 'Nj', '08010', 'BR', '2025-09-13 01:44:57'),
(6, 'TALES MOREIRA DE SOUZA', 'souza_tales@hotmail.com', '7812994408', '74 Rush Street', 'Somerville', 'MA', '02145', 'BR', '2025-09-16 03:17:44'),
(7, 'Valquiria costa', 'valquiriacostare12@icloud.com', '5045285391', '2330 edenborn av  apart 102', 'Metairie', 'Louisiana', '70001', 'BR', '2025-09-16 03:19:31'),
(8, 'Valquiria Costa', 'valquiriacostare12@icloud.com', '5045285391', '2330 edenborn av aprt 102', 'Metairie', 'Louisiana', '70001', 'BR', '2025-09-16 03:30:58'),
(9, 'Gisele', 'velhogisele@gmail.com', '8572667367', '15146 Windflower Aly', 'Winter Garden', 'FL', '34787', 'BR', '2025-09-16 21:07:20'),
(10, 'Anita', 'anitasantosescobar@gmail.com', '3863599304', '12 st andrews ct', 'Palm coast', 'Fl', '32137', 'BR', '2025-09-17 03:28:52'),
(11, 'Patricia de Almeida', 'isa052430@outlook.com', '7244190101', '2401 Woodbine Rd', 'Aliquippa', 'PA', '15001', 'BR', '2025-09-17 22:04:54'),
(12, 'Marcos Geller', 'mmgellerusa@gmail.com', '5619291290', '6662 Boca Del Mar Dr apt111', 'Boca raton', 'FL', '33433', 'BR', '2025-09-18 17:25:07'),
(15, 'Miquellysson Meira lins', 'mike@mmlins.com.br', '82996669740', '03 Travessa intendente Julio Calheiros', 'Rio Largo', 'AL', '57100-000', 'BR', '2025-09-18 21:10:48'),
(16, 'teste', 'mike@mmlins.com.br', '829949392', 'teste testes teste', '', '', '6734599', 'BR', '2025-10-03 06:06:13'),
(17, 'Hyorana Finelli', 'hyolima@hotmail.com', '7146048354', '4323 Wild Ginger Circle, Yorba Linda. California', '', '', '92886', 'BR', '2025-10-05 17:40:21');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `data`, `is_read`, `created_at`) VALUES
(1, 'cart_add', 'Produto adicionado ao carrinho', 'Protetor Solar FPS 60', '{\"product_id\":71}', 0, '2025-08-26 11:51:11'),
(2, 'cart_add', 'Produto adicionado ao carrinho', 'Azitromicina 500mg', '{\"product_id\":70}', 0, '2025-08-26 18:15:32'),
(3, 'new_order', 'Novo Pedido Recebido', 'Pedido #1 de Mike', '{\"order_id\":1,\"customer_name\":\"Mike\",\"total\":39.9,\"payment_method\":\"pix\"}', 0, '2025-08-26 18:16:32'),
(4, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-27 16:56:26'),
(5, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-27 18:33:46'),
(6, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-27 18:34:34'),
(7, 'cart_add', 'Produto ao carrinho', 'Amoxicilina 500mg', '{\"product_id\":63}', 0, '2025-08-27 18:38:16'),
(8, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-27 18:44:41'),
(9, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-27 18:44:55'),
(10, 'cart_add', 'Produto ao carrinho', 'Azitromicina 500mg', '{\"product_id\":70}', 0, '2025-08-27 18:53:49'),
(11, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-27 18:54:06'),
(12, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-27 18:54:09'),
(13, 'cart_add', 'Produto ao carrinho', 'Omeprazol 20mg', '{\"product_id\":64}', 0, '2025-08-27 19:03:57'),
(14, 'new_order', 'Novo Pedido', 'Pedido #2 de Miquellysson Meira lins', '{\"order_id\":2,\"total\":22,\"payment_method\":\"zelle\"}', 0, '2025-08-27 19:04:11'),
(15, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-28 01:53:28'),
(16, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-28 01:53:32'),
(17, 'cart_add', 'Produto ao carrinho', 'Amoxicilina 500mg', '{\"product_id\":63}', 0, '2025-08-28 01:53:35'),
(18, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-28 19:50:03'),
(19, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-28 19:50:12'),
(20, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-28 21:35:17'),
(21, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-28 22:27:30'),
(22, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-28 22:28:32'),
(23, 'cart_add', 'Produto ao carrinho', 'Omeprazol 20mg', '{\"product_id\":4}', 0, '2025-08-28 22:42:35'),
(24, 'cart_add', 'Produto ao carrinho', 'Azitromicina 500mg', '{\"product_id\":10}', 0, '2025-08-28 22:43:10'),
(25, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-28 22:51:06'),
(26, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-29 11:47:01'),
(27, 'new_order', 'Novo Pedido', 'Pedido #3 de Tainon', '{\"order_id\":3,\"total\":12.9,\"payment_method\":\"pix\"}', 0, '2025-08-29 11:47:49'),
(28, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-29 13:07:26'),
(29, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":62}', 0, '2025-08-29 13:07:27'),
(30, 'cart_add', 'Produto ao carrinho', 'Amoxicilina 500mg', '{\"product_id\":63}', 0, '2025-08-31 05:07:34'),
(31, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-08-31 05:15:35'),
(32, 'new_order', 'Novo Pedido', 'Pedido #4 de Mike', '{\"order_id\":4,\"total\":66.3,\"payment_method\":\"paypal\"}', 0, '2025-08-31 07:32:52'),
(33, 'cart_add', 'Produto ao carrinho', 'Protetor Solar FPS 60', '{\"product_id\":71}', 0, '2025-09-01 18:41:23'),
(34, 'cart_add', 'Produto ao carrinho', 'Ibuprofeno 400mg', '{\"product_id\":2}', 0, '2025-09-09 21:30:55'),
(35, 'cart_add', 'Produto ao carrinho', 'Paracetamol 750mg', '{\"product_id\":61}', 0, '2025-09-10 22:05:28'),
(36, 'cart_add', 'Produto ao carrinho', 'Amoxicilina 500mg', '{\"product_id\":63}', 0, '2025-09-10 23:33:51'),
(37, 'cart_add', 'Produto ao carrinho', 'Deposteron', '{\"product_id\":73}', 0, '2025-09-11 17:01:53'),
(38, 'cart_add', 'Produto ao carrinho', 'CETOBETA', '{\"product_id\":119}', 0, '2025-09-13 01:43:32'),
(39, 'cart_add', 'Produto ao carrinho', 'CETOBETA', '{\"product_id\":119}', 0, '2025-09-13 01:43:33'),
(40, 'cart_add', 'Produto ao carrinho', 'CEFALEXINA INFANTIL', '{\"product_id\":118}', 0, '2025-09-13 01:43:37'),
(41, 'cart_add', 'Produto ao carrinho', 'CAPTOPRIL', '{\"product_id\":116}', 0, '2025-09-13 01:43:41'),
(42, 'new_order', 'Novo Pedido', 'Pedido #5 de Marcelo Souza', '{\"order_id\":5,\"total\":115,\"payment_method\":\"venmo\"}', 0, '2025-09-13 01:44:57'),
(43, 'cart_add', 'Produto ao carrinho', 'AMBROXOLMEL', '{\"product_id\":88}', 0, '2025-09-13 20:03:06'),
(44, 'cart_add', 'Produto ao carrinho', 'NISTATINA POMADA VAGINAL', '{\"product_id\":180}', 0, '2025-09-14 18:01:16'),
(45, 'cart_add', 'Produto ao carrinho', 'NISTATINA LIQUIDA', '{\"product_id\":179}', 0, '2025-09-14 18:01:18'),
(46, 'cart_add', 'Produto ao carrinho', 'DRAMIN GEL', '{\"product_id\":150}', 0, '2025-09-15 21:57:41'),
(47, 'cart_add', 'Produto ao carrinho', 'AAS', '{\"product_id\":79}', 0, '2025-09-16 02:53:37'),
(48, 'cart_add', 'Produto ao carrinho', 'AAS', '{\"product_id\":79}', 0, '2025-09-16 02:53:38'),
(49, 'cart_add', 'Produto ao carrinho', 'AAS', '{\"product_id\":79}', 0, '2025-09-16 02:53:39'),
(50, 'cart_add', 'Produto ao carrinho', 'TADALAFILA  5 MG', '{\"product_id\":212}', 0, '2025-09-16 03:00:49'),
(51, 'cart_add', 'Produto ao carrinho', 'NEOSADINA', '{\"product_id\":174}', 0, '2025-09-16 03:05:48'),
(52, 'cart_add', 'Produto ao carrinho', 'TADALAFILA  5 MG', '{\"product_id\":212}', 0, '2025-09-16 03:16:06'),
(53, 'cart_add', 'Produto ao carrinho', 'NEOSADINA', '{\"product_id\":174}', 0, '2025-09-16 03:16:37'),
(54, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-09-16 03:16:38'),
(55, 'cart_add', 'Produto ao carrinho', 'FLOUXETINA', '{\"product_id\":156}', 0, '2025-09-16 03:17:35'),
(56, 'new_order', 'Novo Pedido', 'Pedido #6 de TALES MOREIRA DE SOUZA', '{\"order_id\":6,\"total\":55,\"payment_method\":\"pix\"}', 0, '2025-09-16 03:17:44'),
(57, 'new_order', 'Novo Pedido', 'Pedido #7 de Valquiria costa', '{\"order_id\":7,\"total\":40,\"payment_method\":\"paypal\"}', 0, '2025-09-16 03:19:31'),
(58, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-16 03:21:46'),
(59, 'cart_add', 'Produto ao carrinho', 'FLOUXETINA', '{\"product_id\":156}', 0, '2025-09-16 03:29:19'),
(60, 'new_order', 'Novo Pedido', 'Pedido #8 de Valquiria Costa', '{\"order_id\":8,\"total\":40,\"payment_method\":\"zelle\"}', 0, '2025-09-16 03:30:58'),
(61, 'cart_add', 'Produto ao carrinho', 'AMOXICILINA INFANTIL', '{\"product_id\":3}', 0, '2025-09-16 13:37:23'),
(62, 'cart_add', 'Produto ao carrinho', 'BACTRIM ADULTO', '{\"product_id\":103}', 0, '2025-09-16 20:39:01'),
(63, 'cart_add', 'Produto ao carrinho', 'NISTATINA POMADA VAGINAL', '{\"product_id\":180}', 0, '2025-09-16 20:50:49'),
(64, 'cart_add', 'Produto ao carrinho', 'NISTATINA + AXIDO DE ZINCO', '{\"product_id\":178}', 0, '2025-09-16 20:51:00'),
(65, 'cart_add', 'Produto ao carrinho', 'ACICLOVIR POMADA', '{\"product_id\":81}', 0, '2025-09-16 20:53:49'),
(66, 'new_order', 'Novo Pedido', 'Pedido #9 de Gisele', '{\"order_id\":9,\"total\":30,\"payment_method\":\"zelle\"}', 0, '2025-09-16 21:07:20'),
(67, 'cart_add', 'Produto ao carrinho', 'NEOSADINA', '{\"product_id\":174}', 0, '2025-09-17 03:09:50'),
(68, 'cart_add', 'Produto ao carrinho', 'DIAZEPAM', '{\"product_id\":142}', 0, '2025-09-17 03:24:39'),
(69, 'cart_add', 'Produto ao carrinho', 'CLANAZEPAM', '{\"product_id\":129}', 0, '2025-09-17 03:25:13'),
(70, 'new_order', 'Novo Pedido', 'Pedido #10 de Anita', '{\"order_id\":10,\"total\":40,\"payment_method\":\"paypal\"}', 0, '2025-09-17 03:28:52'),
(71, 'cart_add', 'Produto ao carrinho', 'ANNITA INFANTIL', '{\"product_id\":92}', 0, '2025-09-17 21:54:29'),
(72, 'cart_add', 'Produto ao carrinho', 'FLUCONAZOL', '{\"product_id\":155}', 0, '2025-09-17 21:55:21'),
(73, 'new_order', 'Novo Pedido', 'Pedido #11 de Patricia de Almeida', '{\"order_id\":11,\"total\":50,\"payment_method\":\"zelle\"}', 0, '2025-09-17 22:04:54'),
(74, 'cart_add', 'Produto ao carrinho', 'DEXAMETASONA', '{\"product_id\":139}', 0, '2025-09-18 11:52:27'),
(75, 'cart_add', 'Produto ao carrinho', 'DEXAMETASONA', '{\"product_id\":139}', 0, '2025-09-18 11:52:30'),
(76, 'cart_add', 'Produto ao carrinho', 'VIAGRA', '{\"product_id\":219}', 0, '2025-09-18 15:49:28'),
(77, 'cart_add', 'Produto ao carrinho', 'VIAGRA', '{\"product_id\":219}', 0, '2025-09-18 17:12:33'),
(78, 'new_order', 'Novo Pedido', 'Pedido #12 de Marcos Geller', '{\"order_id\":12,\"total\":60,\"payment_method\":\"zelle\"}', 0, '2025-09-18 17:25:07'),
(79, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-18 21:03:55'),
(80, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-18 21:10:37'),
(81, 'new_order', 'Novo Pedido', 'Pedido # de Miquellysson Meira lins', '{\"order_id\":null,\"total\":87,\"payment_method\":\"zelle\"}', 0, '2025-09-18 21:10:48'),
(82, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-09-18 21:11:06'),
(83, 'cart_add', 'Produto ao carrinho', 'VITACID 0,5mg', '{\"product_id\":220}', 0, '2025-09-18 21:17:16'),
(84, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-18 21:18:32'),
(85, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-19 12:45:20'),
(86, 'cart_add', 'Produto ao carrinho', 'METRONIDAZOL POMADA', '{\"product_id\":169}', 0, '2025-09-20 03:45:21'),
(87, 'cart_add', 'Produto ao carrinho', 'METRONIDAZOL COMPRIMIDO', '{\"product_id\":168}', 0, '2025-09-20 03:45:29'),
(88, 'cart_add', 'Produto ao carrinho', 'VOMISTOP', '{\"product_id\":222}', 0, '2025-09-23 23:24:11'),
(89, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-09-23 23:35:55'),
(90, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-24 01:53:42'),
(91, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-09-24 01:53:46'),
(92, 'cart_add', 'Produto ao carrinho', 'VOMISTOP', '{\"product_id\":222}', 0, '2025-09-24 01:53:57'),
(93, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-24 01:55:30'),
(94, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-09-30 02:19:54'),
(95, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-09-30 02:20:01'),
(96, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-10-01 20:22:27'),
(97, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-10-01 20:22:42'),
(98, 'cart_add', 'Produto ao carrinho', 'YASMIN', '{\"product_id\":223}', 0, '2025-10-03 06:05:46'),
(99, 'new_order', 'Novo Pedido', 'Pedido #14 de teste', '{\"order_id\":14,\"total\":27,\"payment_method\":\"pix\"}', 0, '2025-10-03 06:06:13'),
(100, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-10-04 17:22:02'),
(101, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-10-04 17:22:03'),
(102, 'cart_add', 'Produto ao carrinho', 'ALPRAZOLAM', '{\"product_id\":87}', 0, '2025-10-04 17:23:01'),
(103, 'cart_add', 'Produto ao carrinho', 'ZOLPIDEM', '{\"product_id\":224}', 0, '2025-10-05 17:37:21'),
(104, 'new_order', 'Novo Pedido', 'Pedido #15 de Hyorana Finelli', '{\"order_id\":15,\"total\":47,\"payment_method\":\"zelle\"}', 0, '2025-10-05 17:40:21');

-- --------------------------------------------------------

--
-- Estrutura para tabela `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `items_json` longtext NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(40) NOT NULL,
  `payment_ref` text DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT 'pending',
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `zelle_receipt` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_viewed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `items_json`, `subtotal`, `shipping_cost`, `total`, `payment_method`, `payment_ref`, `payment_status`, `status`, `zelle_receipt`, `notes`, `admin_viewed`, `created_at`, `updated_at`) VALUES
(1, 1, '[{\"id\":70,\"name\":\"Azitromicina 500mg\",\"price\":39.9,\"qty\":1,\"sku\":\"FXD-1010\"}]', 39.90, 0.00, 39.90, 'pix', '0002010102122642br.gov.bcb.pix0124chave-pix@farmafacil.com520400005303986540539.905802BR5911Farma Facil6006Maceio63046116', 'pending', 'pending', NULL, NULL, 0, '2025-08-26 18:16:32', '2025-08-26 18:16:32'),
(2, 2, '[{\"id\":64,\"name\":\"Omeprazol 20mg\",\"price\":22,\"qty\":1,\"sku\":\"FXD-1004\"}]', 22.00, 0.00, 22.00, 'zelle', 'pay@farmafacil.com', 'pending', 'pending', 'storage/zelle_receipts/zelle_1756321451_724ac8b0.png', NULL, 0, '2025-08-27 19:04:11', '2025-08-27 19:04:11'),
(3, 3, '[{\"id\":61,\"name\":\"Paracetamol 750mg\",\"price\":12.9,\"qty\":1,\"sku\":\"FXD-1001\"}]', 12.90, 0.00, 12.90, 'pix', '00020101021226460014br.gov.bcb.pix0124chave-pix@farmafacil.com520400005303986540512.905802BR5911Farma Facil6006Maceio6304F2E7', 'pending', 'pending', NULL, NULL, 0, '2025-08-29 11:47:49', '2025-08-29 11:47:49'),
(4, 4, '[{\"id\":61,\"name\":\"Paracetamol 750mg\",\"price\":12.9,\"qty\":1,\"sku\":\"FXD-1001\"},{\"id\":62,\"name\":\"Ibuprofeno 400mg\",\"price\":18.5,\"qty\":1,\"sku\":\"FXD-1002\"},{\"id\":63,\"name\":\"Amoxicilina 500mg\",\"price\":34.9,\"qty\":1,\"sku\":\"FXD-1003\"}]', 66.30, 0.00, 66.30, 'paypal', 'https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal@farmafacil.com&currency_code=BRL&amount=66.3&item_name=Pedido%20Farma%20Facil&return=https://arkaleads.com/farmafixed/index.php?route=checkout_complete&cancel_return=https://arkaleads.com/farmafixed/index.php?route=checkout_cancel', 'pending', 'pending', NULL, NULL, 0, '2025-08-31 07:32:52', '2025-08-31 07:32:52'),
(5, 5, '[{\"id\":116,\"name\":\"CAPTOPRIL\",\"price\":25,\"qty\":1,\"sku\":\"FXD - 1048\"},{\"id\":118,\"name\":\"CEFALEXINA INFANTIL\",\"price\":30,\"qty\":1,\"sku\":\"FXD - 1050\"},{\"id\":119,\"name\":\"CETOBETA\",\"price\":30,\"qty\":2,\"sku\":\"FXD - 1051\"}]', 115.00, 0.00, 115.00, 'venmo', 'https://venmo.com/u/farmafacil', 'pending', 'paid', NULL, NULL, 0, '2025-09-13 01:44:57', '2025-09-13 01:47:41'),
(6, 6, '[{\"id\":174,\"name\":\"NEOSADINA\",\"price\":20,\"qty\":1,\"sku\":\"FXD - 1106\"},{\"id\":212,\"name\":\"TADALAFILA  5 MG\",\"price\":35,\"qty\":1,\"sku\":\"FXD - 1144\"}]', 55.00, 0.00, 55.00, 'pix', '00020101021226460014br.gov.bcb.pix0124chave-pix@farmafacil.com520400005303986540555.005802BR5911Farma Facil6006Maceio6304AFB6', 'pending', 'paid', NULL, NULL, 0, '2025-09-16 03:17:44', '2025-09-16 13:11:34'),
(7, 7, '[{\"id\":156,\"name\":\"FLOUXETINA\",\"price\":40,\"qty\":1,\"sku\":\"FXD - 1088\"}]', 40.00, 0.00, 40.00, 'paypal', 'https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal@farmafacil.com&currency_code=BRL&amount=40&item_name=Pedido%20Farma%20Facil&return=https://victorfarmafacil.com/index.php?route=checkout_complete&cancel_return=https://victorfarmafacil.com/index.php?route=checkout_cancel', 'pending', 'paid', NULL, NULL, 0, '2025-09-16 03:19:31', '2025-09-16 03:29:04'),
(8, 8, '[{\"id\":156,\"name\":\"FLOUXETINA\",\"price\":40,\"qty\":1,\"sku\":\"FXD - 1088\"}]', 40.00, 0.00, 40.00, 'zelle', 'pay@farmafacil.com', 'pending', 'shipped', 'storage/zelle_receipts/zelle_1757993458_0dac5c25.png', NULL, 0, '2025-09-16 03:30:58', '2025-09-17 17:35:27'),
(9, 9, '[{\"id\":103,\"name\":\"BACTRIM ADULTO\",\"price\":30,\"qty\":1,\"sku\":\"FXD - 1035\"}]', 30.00, 0.00, 30.00, 'zelle', 'pay@farmafacil.com', 'pending', 'shipped', 'storage/zelle_receipts/zelle_1758056840_a1e19ce8.png', NULL, 0, '2025-09-16 21:07:20', '2025-09-18 17:51:06'),
(10, 10, '[{\"id\":129,\"name\":\"CLANAZEPAM\",\"price\":40,\"qty\":1,\"sku\":\"FXD - 1061\"}]', 40.00, 0.00, 40.00, 'paypal', 'https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal@farmafacil.com&currency_code=BRL&amount=40&item_name=Pedido%20Farma%20Facil&return=https://victorfarmafacil.com/index.php?route=checkout_complete&cancel_return=https://victorfarmafacil.com/index.php?route=checkout_cancel', 'pending', 'shipped', NULL, NULL, 0, '2025-09-17 03:28:52', '2025-09-18 17:51:28'),
(11, 11, '[{\"id\":92,\"name\":\"ANNITA INFANTIL\",\"price\":30,\"qty\":1,\"sku\":\"FXD -1031\"},{\"id\":155,\"name\":\"FLUCONAZOL\",\"price\":20,\"qty\":1,\"sku\":\"FXD - 1087\"}]', 50.00, 0.00, 50.00, 'zelle', 'pay@farmafacil.com', 'pending', 'shipped', 'storage/zelle_receipts/zelle_1758146694_5f52f19e.png', NULL, 0, '2025-09-17 22:04:54', '2025-09-18 19:39:15'),
(12, 12, '[{\"id\":219,\"name\":\"VIAGRA\",\"price\":20,\"qty\":3,\"sku\":\"FXD - 1151\"}]', 60.00, 0.00, 60.00, 'zelle', 'pay@farmafacil.com', 'pending', 'paid', 'storage/zelle_receipts/zelle_1758216307_352fb2d9.png', NULL, 0, '2025-09-18 17:25:07', '2025-09-18 17:53:07'),
(13, 15, '[{\"id\":224,\"name\":\"ZOLPIDEM\",\"price\":40,\"qty\":2,\"sku\":\"FXD - 1156\"}]', 80.00, 7.00, 87.00, 'zelle', '8568794719', 'pending', 'canceled', NULL, NULL, 0, '2025-09-18 21:10:48', '2025-09-18 21:10:58'),
(14, 16, '[{\"id\":223,\"name\":\"YASMIN\",\"price\":20,\"qty\":1,\"sku\":\"FXD - 1155\"}]', 20.00, 7.00, 27.00, 'pix', '00020101021226400014br.gov.bcb.pix011835.816.920/0001-67520400005303986540527.005802BR5920MH Baltazar de Souza6006Maceio63040AB4', 'pending', 'canceled', NULL, NULL, 0, '2025-10-03 06:06:13', '2025-10-13 18:07:03'),
(15, 17, '[{\"id\":224,\"name\":\"ZOLPIDEM\",\"price\":40,\"qty\":1,\"sku\":\"FXD - 1156\"}]', 40.00, 7.00, 47.00, 'zelle', '8568794719', 'pending', 'pending', NULL, NULL, 0, '2025-10-05 17:40:21', '2025-10-05 17:40:21');

-- --------------------------------------------------------

--
-- Estrutura para tabela `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 100,
  `image_path` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `stock`, `image_path`, `active`, `featured`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, 1, 'FF-0001', 'PARACETAMOL COMPRIMIDO 750mg', 'Analgésico e antitérmico', 20.00, 100, 'storage/products/p_1757610057_a82fa7.jpg', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:56:33'),
(2, 3, 'FF-0002', 'IBUPROFENO COMPRIMIDO  400mg', 'Anti-inflamatório não esteroidal', 20.00, 100, 'storage/products/p_1757610361_c34e80.jpg', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:59:04'),
(3, 2, 'FF-0003', 'AMOXICILINA 250mg infantil', 'Antibiótico (venda controlada)', 30.00, 100, 'storage/products/p_1757610525_1e0d8f.webp', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-19 17:32:43'),
(4, 5, 'FF-0004', 'OMEPRAZOL 20mg', 'Indicado para tratar certas condições em que ocorra muita produção de ácido no estômago.', 30.00, 100, 'storage/products/p_1757610845_d2ee97.webp', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:18:35'),
(5, 7, 'FF-0005', 'LORATADINA COMPRIMIDO 10MG', 'Antialérgico', 20.00, 100, 'storage/products/p_1757611033_310786.png', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:17:13'),
(6, 1, 'FF-0006', 'DIPIRONA 1g', 'Analgésico/antitérmico', 20.00, 100, 'storage/products/p_1757611624_d786e8.jpg', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:27:29'),
(7, 6, 'FF-0007', 'LOSARTANA 50mg', 'Anti-hipertensivo', 20.00, 100, 'storage/products/p_1757612234_5d9be4.webp', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:37:14'),
(8, 6, 'FF-0008', 'METFORMINA 850mg', 'Antidiabético', 30.00, 100, 'storage/products/p_1757612381_54cc65.webp', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:39:41'),
(9, 4, 'FF-0009', 'VITAMINA C ADULTO 500mg', 'Suplemento vitamínico', 20.00, 100, 'storage/products/p_1757612828_0374f4.jpg', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:47:08'),
(10, 2, 'FF-0010', 'AZITROMICINA INFANTIL', 'Antibiótico (venda controlada)', 30.00, 100, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 17:53:43'),
(11, 8, 'FF-0011', 'Protetor Solar FPS 60', 'Proteção solar dermatológica', 45.90, 100, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-08-27 18:41:52'),
(12, 7, 'FF-0012', 'XAROPE AMBROXOL ADULTO', 'Medicamento expectorante', 30.00, 100, 'storage/products/p_1757614458_a58137.png', 1, 0, NULL, NULL, '2025-08-26 08:23:23', '2025-09-11 18:14:18'),
(61, 1, 'FXD- 13', 'PARACETAMOL GOTAS', 'Analgésico e antitérmico', 20.00, 100, 'storage/products/p_1757613499_fb058c.png', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-17 18:18:18'),
(62, 3, 'FXD-1002', 'IBUPROFENO LIQUIDA  20ml', 'Anti-inflamatório não esteroidal', 20.00, 100, 'storage/products/p_1757613647_e427b9.jpg', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:00:47'),
(63, 2, 'FXD-1003', 'Amoxicilina 500mg', 'Antibiótico (venda controlada)', 34.90, 99, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 0, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:02:39'),
(64, 5, 'FXD-1004', 'Omeprazol 20mg', 'Inibidor de bomba de prótons', 22.00, 99, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 0, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:04:09'),
(65, 7, 'FXD-1005', 'Loratadina 10mg', 'Antialérgico', 15.75, 100, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 0, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:04:57'),
(66, 1, 'FXD-1006', 'DIPIRONA 500 mg', 'Analgésico/antitérmico', 15.00, 100, 'storage/products/p_1757611723_1b38f5.jpg', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 17:28:43'),
(67, 6, 'FXD-1007', 'Losartana 50mg', 'Anti-hipertensivo', 20.00, 100, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 0, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:07:52'),
(68, 6, 'FXD-1008', 'METFORMINA  500mg', 'Antidiabéticos', 30.00, 100, 'storage/products/p_1757612516_bbb81b.webp', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 17:41:56'),
(69, 2, 'FXD-1009', 'AMOXICILINA 500mg COMPRIMIDOS', 'Antibióticos', 30.00, 100, 'storage/products/p_1757606351_ea118d.jpeg', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:10:14'),
(70, 2, 'FXD-1010', 'Azitromicina 500mg', 'Antibiótico (venda controlada)', 30.00, 99, 'https://base.rhemacriativa.com/wp-content/uploads/2025/08/AZITROMICINA.png', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 15:56:47'),
(71, 7, 'FXD-1011', 'XAROPE AMBROXOL INFANTIL', 'Xarope expectorante', 30.00, 100, 'storage/products/p_1757606142_dbf945.jpeg', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 18:12:22'),
(72, 7, 'FXD-1012', 'Xarope Expec', 'Medicamento expectorante', 30.00, 100, 'storage/products/p_1757606031_52036d.jpeg', 1, 0, NULL, NULL, '2025-08-26 09:02:07', '2025-09-11 15:53:51'),
(73, 3, 'FXD-1013', 'Deposteron', 'Hormônio', 120.00, 100, 'storage/products/p_1757608532_01ea41.jpg', 1, 0, NULL, NULL, '2025-09-11 16:19:25', '2025-09-11 17:50:01'),
(74, 2, 'FXD -1014', 'AMOXICILINA COM CLAVULANATO', 'Antibiótico', 50.00, 100, 'storage/products/p_1757609811_9fb1dd.webp', 1, 0, NULL, NULL, '2025-09-11 16:56:51', '2025-09-11 16:57:25'),
(75, 7, 'FXD -1015', 'LORATADINA XAROPE', 'Antialérgico', 30.00, 100, 'storage/products/p_1757611360_d2daa1.webp', 1, 0, NULL, NULL, '2025-09-11 17:22:40', '2025-09-11 17:22:40'),
(76, 1, 'FXD - 1016', 'DIPIRONA LIQUIDA', '', 15.00, 0, 'storage/products/p_1757611969_7cb999.jpg', 1, 0, NULL, NULL, '2025-09-11 17:32:49', '2025-09-11 17:33:18'),
(77, 1, 'FXD - 1017', 'DIPIRONA XAROPE', 'Dipirona infantil', 30.00, 100, 'storage/products/p_1757612073_11b8d2.jpg', 1, 0, NULL, NULL, '2025-09-11 17:34:33', '2025-09-11 17:34:33'),
(78, 4, 'FXD - 1018', 'VITAMINA C INFANTIL', 'Vitamina', 20.00, 100, 'storage/products/p_1757612964_2b2ad4.webp', 1, 0, NULL, NULL, '2025-09-11 17:49:24', '2025-09-11 17:49:24'),
(79, 1, 'FXD-1019', 'AAS', 'Antitérmico e alivia dor', 5.00, 100, 'storage/products/p_1757614785_64a02b.png', 1, 0, NULL, NULL, '2025-09-11 18:19:45', '2025-09-11 18:29:09'),
(80, 3, 'FXD - 1020', 'ACICLOVIR COMPRIMIDO', 'Um medicamento para tratar e prevenir infeções causadas por vírus, principalmente o herpes simplex (causador de herpes labial e genital) e o varicela-zoster (causador da catapora e herpes zóster).', 30.00, 100, 'storage/products/p_1757614969_d3e7d5.webp', 1, 0, NULL, NULL, '2025-09-11 18:22:49', '2025-09-11 18:28:57'),
(81, 8, 'FXD - 1021', 'ACICLOVIR POMADA', 'Eé um medicamento antiviral para o tratamento de infecções na pele e mucosas causadas pelo vírus Herpes simplex, como herpes labial e herpes genital', 30.00, 100, 'storage/products/p_1757615298_32944a.webp', 1, 0, NULL, NULL, '2025-09-11 18:28:18', '2025-09-11 18:28:40'),
(82, 4, 'FXD - 1022', 'ÁCIDO FÓLICO', 'uma vitamina hidrossolúvel essencial para a produção de DNA, células sanguíneas (hemácias), e no desenvolvimento do sistema nervoso. nervoso. Ele previne doenças do tubo neural em bebés durante a gravidez, mas também tem benefícios como o reforço da imunidade e a saúde cardiovascular em adultos', 20.00, 100, 'storage/products/p_1757615582_f2b6d0.webp', 1, 0, NULL, NULL, '2025-09-11 18:33:02', '2025-09-11 18:33:02'),
(83, 4, 'FXD-1022', 'ADEFORTE', 'Reposição eficaz das vitaminas A, D e E\r\nAuxilia na saúde ocular, óssea e imunológica\r\nAção antioxidante que combate os radicais livres', 20.00, 100, 'storage/products/p_1757615765_b36f65.webp', 1, 0, NULL, NULL, '2025-09-11 18:36:05', '2025-09-11 18:36:05'),
(84, 7, 'FXD-1023', 'AEROLIN SPRAY', 'alivia o aperto no peito, o chiado e a tosse, permitindo que você respire com mais facilidade ( asma)', 25.00, 100, 'storage/products/p_1757616061_1531e2.png', 1, 0, NULL, NULL, '2025-09-11 18:41:01', '2025-09-11 18:41:01'),
(85, 5, 'FXD -1024', 'ALBEL ADULTO', 'Albel com 2 comprimidos (Antiparasitário)', 30.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-11 22:17:16', '2025-09-11 22:17:27'),
(86, 3, 'FXD - 1025', 'ALBEL LÍQUIDO', 'Antiparasitário', 30.00, 100, 'storage/products/p_1757629295_ccdfd3.jpg', 1, 0, NULL, NULL, '2025-09-11 22:21:35', '2025-09-11 22:21:35'),
(87, 6, 'FXD - 1026', 'ALPRAZOLAM', 'Venda controlada', 40.00, 100, 'storage/products/p_1757629634_d2cc93.jpg', 1, 0, NULL, NULL, '2025-09-11 22:27:14', '2025-09-11 22:27:14'),
(88, 7, 'FXD-1027', 'AMBROXMEL', 'Xarope expectorante', 30.00, 100, 'storage/products/p_1757629991_ceadfd.png', 1, 0, NULL, NULL, '2025-09-11 22:33:11', '2025-09-17 18:16:56'),
(89, 7, 'FXD-1028', 'AMBROXOL ADULTO', 'Xarope expectorante', 30.00, 100, 'storage/products/p_1757630255_1f8798.jpg', 1, 0, NULL, NULL, '2025-09-11 22:37:35', '2025-09-11 22:37:35'),
(90, 7, 'FXD - 1029', 'AMBROXOL INFANTIL', 'Xarope expectorante', 30.00, 100, 'storage/products/p_1757631324_61a3a9.webp', 1, 0, NULL, NULL, '2025-09-11 22:55:24', '2025-09-11 22:55:24'),
(91, 6, 'FXD - 1030', 'ANLODIPINO', 'A anlodipina é um medicamento bloqueador dos canais de cálcio, utilizado para tratar a pressão arterial elevada.', 20.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-11 23:09:02', '2025-09-11 23:09:02'),
(92, 6, 'FXD -1031', 'ANNITA INFANTIL', 'Tratamento contra vermes', 30.00, 99, 'storage/products/p_1757632659_346cf2.jpg', 1, 0, NULL, NULL, '2025-09-11 23:17:39', '2025-09-17 22:04:54'),
(93, 6, 'FXD - 1032', 'ANNITA ADULTO', 'Tratamentos para vermes', 25.00, 100, 'storage/products/p_1757632727_0a6955.jpg', 1, 0, NULL, NULL, '2025-09-11 23:18:47', '2025-09-11 23:18:47'),
(94, 4, 'FXD - 1033', 'APEVITIN BC', 'Estimulador de apetite', 35.00, 100, 'storage/products/p_1757632995_43dbdb.webp', 1, 0, NULL, NULL, '2025-09-11 23:23:15', '2025-09-11 23:23:15'),
(102, 6, 'FXD - 1034', 'ATENOLOL', 'Pressão alta', 20.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-12 19:16:37', '2025-09-12 19:37:14'),
(103, 2, 'FXD - 1035', 'BACTRIM ADULTO', 'Antibiótico para infeções', 30.00, 99, 'storage/products/p_1757706011_ff0319.jpg', 1, 0, NULL, NULL, '2025-09-12 19:40:11', '2025-09-16 21:07:20'),
(104, 2, 'FXD - 1036', 'BACTRIM INFANTIL', 'Antibiótico para infecções', 30.00, 100, 'storage/products/p_1757706522_914566.jpg', 1, 0, NULL, NULL, '2025-09-12 19:48:42', '2025-09-12 19:48:42'),
(105, 1, 'FXD -  1037', 'BENEGRIPE', 'Antialérgico, analgésico e estimulante', 20.00, 100, 'storage/products/p_1757706742_d23e8e.webp', 1, 0, NULL, NULL, '2025-09-12 19:52:22', '2025-09-12 19:52:22'),
(106, 8, 'FXD - 1038', 'BAPANTOL BABY', 'Assaduras', 25.00, 100, 'storage/products/p_1757706967_162344.jpg', 1, 0, NULL, NULL, '2025-09-12 19:56:07', '2025-09-12 19:56:07'),
(107, 8, 'FXD-1039', 'BAPANTOL DERMA', 'Hidratante, restaurador protege a pele e cabelo', 25.00, 100, 'storage/products/p_1757707149_d6dfd7.webp', 1, 0, NULL, NULL, '2025-09-12 19:59:09', '2025-09-12 19:59:09'),
(108, 8, 'FXD - 1040', 'BEPANTOL TATTOO', 'Hidrante e cicatrizante para pele tatuada', 20.00, 100, 'storage/products/p_1757707816_44dae3.webp', 1, 0, NULL, NULL, '2025-09-12 20:10:16', '2025-09-12 20:10:16'),
(109, 8, 'FXD- 1041', 'BEPANTRIZ', 'Pomada para prevenção de assaduras', 20.00, 100, 'storage/products/p_1757708047_00a494.webp', 1, 0, NULL, NULL, '2025-09-12 20:14:07', '2025-09-12 20:14:07'),
(110, 6, 'FXD - 1042', 'BETATRINTA INJETAVEL', 'Para articulações como: osteoartrite, bursite, espondilite anquilosante, espondilite radiculite, dor no cóccix, ciática, dor nas costas, torcicolo, exostose, inflamação na planta dos pés (fascite).', 40.00, 100, 'storage/products/p_1757708665_351696.jpg', 1, 0, NULL, NULL, '2025-09-12 20:24:25', '2025-09-12 20:25:03'),
(111, 8, 'FXD-1043', 'BETACORTAZOL POMADA', 'um medicamento dermatológico que combina ações anti-inflamatória, antibacteriana e antimicótica, indicado para o tratamento de diversas doenças de pele, como dermatites (de contato, atópica, seborreica), intertrigo, disidrose e neurodermatite, causadas por germes.', 30.00, 100, 'storage/products/p_1757708983_92d032.png', 1, 0, NULL, NULL, '2025-09-12 20:29:43', '2025-09-12 20:29:43'),
(112, 5, 'FXD - 1044', 'BUCLINA', 'ESTIMULADOR DE APETITE', 30.00, 100, 'storage/products/p_1757709202_0a607b.webp', 1, 0, NULL, NULL, '2025-09-12 20:33:22', '2025-09-12 20:33:22'),
(113, 1, 'FXD - 1045', 'BUSCOPAN COMPOSTO', 'Analgésico', 20.00, 100, 'storage/products/p_1757709418_a492c4.webp', 1, 0, NULL, NULL, '2025-09-12 20:36:58', '2025-09-12 20:36:58'),
(114, 1, 'FXD - 1046', 'BUSCOPAN DUO', 'Dores abdominais ( cólicas )', 20.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-12 20:41:38', '2025-09-12 20:41:38'),
(115, 1, 'FXD - 1047', 'BUSCOPAN LIQUIDO BABY', 'cólicas gastrintestinais (estômago e intestinos), cólicas e movimentos involuntários anormais.', 20.00, 100, 'storage/products/p_1757710082_e4fed2.webp', 1, 0, NULL, NULL, '2025-09-12 20:48:02', '2025-09-12 20:48:02'),
(116, 6, 'FXD - 1048', 'CAPTOPRIL', 'O captopril diminui a pressão arterial.', 25.00, 99, 'storage/products/p_1757710247_7ce3b4.jpg', 1, 0, NULL, NULL, '2025-09-12 20:50:47', '2025-09-13 01:44:57'),
(117, 2, 'FXD - 1049', 'CEFALEXINA COMPRIMIDO', 'Antibiótico', 30.00, 100, 'storage/products/p_1757710670_a18ecb.jpg', 1, 0, NULL, NULL, '2025-09-12 20:57:50', '2025-09-12 20:57:50'),
(118, 2, 'FXD - 1050', 'CEFALEXINA INFANTIL', 'ANTIBIOTICO', 30.00, 99, 'storage/products/p_1757710793_b6a61a.webp', 1, 0, NULL, NULL, '2025-09-12 20:59:53', '2025-09-13 01:44:57'),
(119, 8, 'FXD - 1051', 'CETOBETA', 'Tratar infecções e inflamações da pele e', 30.00, 98, 'storage/products/p_1757711065_7d98bb.jpg', 1, 0, NULL, NULL, '2025-09-12 21:04:25', '2025-09-13 01:44:57'),
(120, 8, 'FXD - 1052', 'CETACONAZOL COMPRIMIDO', 'tratamento de  infecções da pele, do couro cabeludo e das unhas, sendo geralmente indicado para tratar infecções fúngicas como as micoses, a candidíase e o “pano branco”.', 25.00, 100, 'storage/products/p_1757711450_289928.jpg', 1, 0, NULL, NULL, '2025-09-12 21:10:50', '2025-09-12 21:10:50'),
(121, 8, 'FXD -  1053', 'CETACONAZOL POMADA', 'Tratamento de micose na pele.', 25.00, 100, 'storage/products/p_1757711595_e9f41b.webp', 1, 0, NULL, NULL, '2025-09-12 21:13:15', '2025-09-12 21:13:15'),
(122, 3, 'FXD - 1054', 'CETOPROFENO', 'Antiflamatorio, analgésico e antitérmico.', 20.00, 0, 'storage/products/p_1757711822_fa3fab.png', 1, 0, NULL, NULL, '2025-09-12 21:17:02', '2025-09-12 21:17:02'),
(123, 4, 'FXD - 1055', 'CICLO 21', 'ANTICONCEPCIONAL', 20.00, 100, 'storage/products/p_1757712020_4456cb.webp', 1, 0, NULL, NULL, '2025-09-12 21:20:20', '2025-09-12 21:20:58'),
(124, 8, 'FXD - 1056', 'CICLOBENZAPRINA', 'RELAXANTE', 20.00, 100, 'storage/products/p_1757712224_58196d.jpg', 1, 0, NULL, NULL, '2025-09-12 21:23:44', '2025-09-12 21:23:44'),
(125, 4, 'FXD - 1057', 'CIMEGRIPE GOTAS', 'GRIPES E RESFRIADOS', 20.00, 100, 'storage/products/p_1757712452_fbd4fe.webp', 1, 0, NULL, NULL, '2025-09-12 21:27:32', '2025-09-12 21:27:32'),
(126, 4, 'FXD - 1058', 'CIMEGRIPE COMPRIMIDOS', 'RESFRIADOS E GRIPES', 20.00, 100, 'storage/products/p_1757712532_cc5af8.jpg', 1, 0, NULL, NULL, '2025-09-12 21:28:52', '2025-09-12 21:28:52'),
(127, 2, 'FXD - 1059', 'CIPROFLOXACINO', 'está indicado nas otites externas localizadas ou difusas acompanhadas de reação inflamatória severa causada por bactérias sensíveis ao Cloridrato de Ciprofloxacino.', 30.00, 100, 'storage/products/p_1757713034_896210.webp', 1, 0, NULL, NULL, '2025-09-12 21:37:14', '2025-09-12 21:37:14'),
(128, 6, 'FXD - 1060', 'CITONEURIN INJETAVEL C/ 3 DOSES', 'Para dor e inflamação dos nervos', 80.00, 100, 'storage/products/p_1757713821_2d02e0.webp', 1, 0, NULL, NULL, '2025-09-12 21:50:21', '2025-09-12 21:50:21'),
(129, 6, 'FXD - 1061', 'CL0NAZEPAM COMPRIMIDOS', 'venda controlada', 40.00, 99, 'storage/products/p_1757714460_ad7497.jpg', 1, 0, NULL, NULL, '2025-09-12 22:01:00', '2025-09-17 18:15:44'),
(130, 6, 'FXD - 1062', 'CLONAZEPAM GOTAS', 'VENDA CONTROLADA', 40.00, 100, 'storage/products/p_1757714581_ed2eaf.webp', 1, 0, NULL, NULL, '2025-09-12 22:03:01', '2025-09-17 18:16:07'),
(131, 3, 'FXD - 1063', 'COLIRIO GEOLAB', 'tratamento das irritações nos olhos.', 20.00, 100, 'storage/products/p_1757765536_89ba44.webp', 1, 0, NULL, NULL, '2025-09-13 12:12:16', '2025-09-13 12:12:16'),
(132, 3, 'FXD - 1064', 'COLIRIO MOOURA BRASIL', 'Para irritações nos olhos.', 20.00, 100, 'storage/products/p_1757765741_7dbe99.webp', 1, 0, NULL, NULL, '2025-09-13 12:15:41', '2025-09-13 12:15:41'),
(133, 3, 'FXD - 1065', 'CONTRACEP INJETAVEL TRIMESTRAL', 'um anticoncepcional injetável de ação prolongada.', 40.00, 100, 'storage/products/p_1757774615_9ac8d0.jpg', 1, 0, NULL, NULL, '2025-09-13 14:43:35', '2025-09-13 14:43:35'),
(134, 7, 'FXD - 1066', 'DESCONGEX GOTAS PLUS', 'Descongestionante nasal', 25.00, 0, 'storage/products/p_1757774827_439f2b.jpg', 1, 0, NULL, NULL, '2025-09-13 14:47:07', '2025-09-13 14:52:27'),
(135, 7, 'FXD - 1067', 'DECONGEX XAROPE', 'descongestionante nasal', 30.00, 100, 'storage/products/p_1757775377_27a4e6.jpeg', 1, 0, NULL, NULL, '2025-09-13 14:56:17', '2025-09-17 18:15:12'),
(136, 6, 'FXD - 1068', 'DEPOPROVERA INJETAVEL', 'anticoncepcional injetável trimestral', 45.00, 100, 'storage/products/p_1757775729_9f5449.webp', 1, 0, NULL, NULL, '2025-09-13 15:02:09', '2025-09-13 15:02:09'),
(137, 6, 'FXD - 1069', 'DESOGESTREL', 'é um tipo de anticoncepcional baseado em progesterona que inibe o processo da ovulação.', 25.00, 100, 'storage/products/p_1757775987_846352.webp', 1, 0, NULL, NULL, '2025-09-13 15:06:27', '2025-09-13 15:06:27'),
(138, 6, 'FXD - 1070', 'DEXA CITONEURIN 3 DOSES', 'é usado para combater quadros dolorosos e inflamatórios.', 80.00, 100, 'storage/products/p_1757776154_aec5d3.png', 1, 0, NULL, NULL, '2025-09-13 15:09:14', '2025-09-13 15:09:14'),
(139, 3, 'FXD - 1071', 'DEXAMETASONA', 'um tipo de glicocorticoide', 25.00, 100, 'storage/products/p_1757776534_9a440d.webp', 1, 0, NULL, NULL, '2025-09-13 15:15:34', '2025-09-13 15:15:49'),
(140, 3, 'FXD - 1072', 'DEXAMETASONA POMADA', '(anti-coceira)', 25.00, 100, 'storage/products/p_1757776850_be36be.png', 1, 0, NULL, NULL, '2025-09-13 15:20:50', '2025-09-13 15:20:50'),
(141, 3, 'FXD - 1073', 'DIANE 35', 'Anticoncepcional', 20.00, 100, 'storage/products/p_1757776968_a518fe.webp', 1, 0, NULL, NULL, '2025-09-13 15:22:48', '2025-09-13 15:22:48'),
(142, 6, 'FXD - 1074', 'DIAZEPAM', 'VENDA CONTROLADA', 40.00, 100, 'storage/products/p_1757777240_d4b77f.jpg', 1, 0, NULL, NULL, '2025-09-13 15:27:20', '2025-09-13 15:27:20'),
(143, 3, 'FXD - 1075', 'DICLOFENACO INJETAVEL', 'anti-inflamatória e antipirética', 30.00, 100, 'storage/products/p_1757777508_12febc.webp', 1, 0, NULL, NULL, '2025-09-13 15:31:48', '2025-09-13 15:31:48'),
(144, 3, 'FXD - 1076', 'DICLOFENACO POTASSICO', 'é um anti-inflamatório para o tratamento de curto prazo nos distúrbios relacionados a entorses, distensões e lesões.', 20.00, 100, 'storage/products/p_1757777859_e8c53b.webp', 1, 0, NULL, NULL, '2025-09-13 15:37:39', '2025-09-13 15:37:39'),
(145, 3, 'FXD - 1077', 'DICLOFENACO SODICO', 'é um anti-inflamatório não esteroide.', 20.00, 100, 'storage/products/p_1757778161_e23317.jpg', 1, 0, NULL, NULL, '2025-09-13 15:42:41', '2025-09-13 15:42:41'),
(146, 1, 'FXD - 1078', 'DOMPERIDONA', 'antidopaminérgico', 25.00, 100, 'storage/products/p_1757778331_9b5f0b.png', 1, 0, NULL, NULL, '2025-09-13 15:45:31', '2025-09-13 15:45:31'),
(147, 1, 'FXD - 1079', 'DORALGINA', 'analgésica (diminui a dor) e antiespasmódica (diminui contração involuntária).', 15.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-13 15:59:26', '2025-09-13 15:59:26'),
(148, 1, 'FXD - 1080', 'DORFLEX', 'relaxante muscular e analgésico.', 15.00, 100, 'storage/products/p_1757792699_3e4875.webp', 1, 0, NULL, NULL, '2025-09-13 19:44:59', '2025-09-13 19:44:59'),
(149, 5, 'FXD - 1081', 'DRAMIN B6', 'enjoo, tontura e vômitos em geral.', 20.00, 100, 'storage/products/p_1757792903_9f32df.webp', 1, 0, NULL, NULL, '2025-09-13 19:48:23', '2025-09-13 19:48:23'),
(150, 3, 'FXD - 1082', 'DRAMIN GEL', 'enjoo, tontura e vômitos em geral.', 20.00, 100, 'storage/products/p_1757793342_47982a.webp', 1, 0, NULL, NULL, '2025-09-13 19:55:42', '2025-09-13 19:55:42'),
(151, 4, 'FXD - 1083', 'DURATESTON', 'Testosterona', 35.00, 100, 'storage/products/p_1757793460_a3f87e.webp', 1, 0, NULL, NULL, '2025-09-13 19:57:40', '2025-09-17 18:13:49'),
(152, 1, 'FXD - 1084', 'ENXAK', 'é específica para o alívio da dor de cabeça, gerada pela enxaqueca.', 20.00, 100, 'storage/products/p_1757793614_e48088.jpg', 1, 0, NULL, NULL, '2025-09-13 20:00:14', '2025-09-13 20:00:14'),
(153, 6, 'FXD- 1085', 'ESCITALOPRAM', 'Antidepressivo', 40.00, 100, 'storage/products/p_1757793975_127abb.jpg', 1, 0, NULL, NULL, '2025-09-13 20:06:15', '2025-09-13 20:06:15'),
(154, 4, 'FXD - 1086', 'FEMINA', 'Anticoncepcional hormonal', 20.00, 100, 'storage/products/p_1757794710_63f820.jpg', 1, 0, NULL, NULL, '2025-09-13 20:18:30', '2025-09-13 20:18:55'),
(155, 8, 'FXD - 1087', 'FLUCONAZOL', 'antifúngicos.', 20.00, 99, 'storage/products/p_1757794945_28c950.jpg', 1, 0, NULL, NULL, '2025-09-13 20:22:25', '2025-09-17 22:04:54'),
(156, 6, 'FXD - 1088', 'FLUOXETINA', 'antidepressivo', 40.00, 98, 'storage/products/p_1757795132_b8bd24.jpg', 1, 0, NULL, NULL, '2025-09-13 20:25:32', '2025-09-17 18:13:19'),
(157, 6, 'FXD - 1089', 'FUROSEMIDA', 'potente diurético, ou seja, estimula a produção de urina pelo organismo.', 25.00, 100, 'storage/products/p_1757795770_021542.png', 1, 0, NULL, NULL, '2025-09-13 20:36:10', '2025-09-13 20:36:10'),
(158, 6, 'FXD-1090', 'HIDROCLORATIAZIDA', 'é um medicamento que ajuda o corpo a eliminar o excesso de sódio (sal) e água através dos rins.', 20.00, 100, 'storage/products/p_1757796229_0b069c.png', 1, 0, NULL, NULL, '2025-09-13 20:43:49', '2025-09-13 20:43:49'),
(159, 8, 'FXD-1091', 'HIPOGLOS', 'para prevenir e tratar assaduras.', 25.00, 100, 'storage/products/p_1757796432_bde01d.png', 1, 0, NULL, NULL, '2025-09-13 20:47:12', '2025-09-13 20:47:12'),
(160, 7, 'FXD-1092', 'HISTAMIN COMPRIMIDO', 'Antialérgico', 20.00, 100, 'storage/products/p_1757796611_f40664.jpg', 1, 0, NULL, NULL, '2025-09-13 20:50:11', '2025-09-13 20:50:11'),
(161, 8, 'FXD-1093', 'HISTAMIN POMADA', 'para alergias provocadas por agentes externos', 20.00, 100, 'storage/products/p_1757796957_0bbe3a.jpg', 1, 0, NULL, NULL, '2025-09-13 20:55:57', '2025-09-13 20:55:57'),
(162, 4, 'FXD - 1094', 'HISTAMIN XAROPE', 'ANTIALERGICO', 25.00, 100, 'storage/products/p_1757797262_202dcd.jpg', 1, 0, NULL, NULL, '2025-09-13 21:01:02', '2025-09-13 21:01:02'),
(163, 1, 'FXD - 1095', 'IVERMECTINA', 'antiparasitário para tratar sarna, piolho, lombriga e outras infecções.', 20.00, 100, 'storage/products/p_1757797918_2513a0.png', 1, 0, NULL, NULL, '2025-09-13 21:11:58', '2025-09-13 21:11:58'),
(164, 5, 'FXD-1096', 'LACTO PURGA', 'prisão de ventre', 15.00, 100, 'storage/products/p_1757798233_cfac3a.jpg', 1, 0, NULL, NULL, '2025-09-13 21:17:13', '2025-09-13 21:17:13'),
(165, 4, 'FXD - 1097', 'LAVITAM A Z MULHER', 'VITAMINA', 25.00, 100, 'storage/products/p_1757798472_bc93ae.png', 1, 0, NULL, NULL, '2025-09-13 21:21:12', '2025-09-17 18:14:20'),
(166, 5, 'FXD - 1098', 'LUFTAL', 'desconforto abdominal ( gases).', 20.00, 100, 'storage/products/p_1757799551_7738e6.jpg', 1, 0, NULL, NULL, '2025-09-13 21:39:11', '2025-09-13 21:39:11'),
(167, 6, 'FXD - 1099', 'MISIGYNA INJETAVEL', 'contraceptivo mensal', 40.00, 100, 'storage/products/p_1757799795_476440.jpg', 1, 0, NULL, NULL, '2025-09-13 21:43:15', '2025-09-13 21:43:15'),
(168, 2, 'FXD - 1100', 'METRONIDAZOL COMPRIMIDO', 'infecções parasitárias', 30.00, 100, 'storage/products/p_1757800018_a45e08.jpg', 1, 0, NULL, NULL, '2025-09-13 21:46:58', '2025-09-13 21:46:58'),
(169, 2, 'FXD - 1101', 'METRONIDAZOL POMADA', 'é indicado para o tratamento de tricomoníase (infecções produzidas por várias espécies de Tricomonas).', 30.00, 100, 'storage/products/p_1757800681_a249d2.jpg', 1, 0, NULL, NULL, '2025-09-13 21:58:01', '2025-09-17 18:12:36'),
(170, 6, 'FXD - 1102', 'MICROVLAR', 'contraceptivo oral', 20.00, 100, 'storage/products/p_1757800941_2aadb4.jpg', 1, 0, NULL, NULL, '2025-09-13 22:02:21', '2025-09-13 22:02:21'),
(171, 3, 'FXD - 1103', 'NAPROXENO 500 MG', 'condições inflamatórias e dolorosas', 30.00, 100, 'storage/products/p_1757801170_887aca.jpg', 1, 0, NULL, NULL, '2025-09-13 22:06:10', '2025-09-13 22:12:15'),
(172, 3, 'FXD - 1104', 'NAPROXENO 850 MG', 'ANTI INFLAMATORIO', 30.00, 100, 'storage/products/p_1757801673_fc08a8.webp', 1, 0, NULL, NULL, '2025-09-13 22:14:33', '2025-09-17 18:11:25'),
(173, 3, 'FXD - 1105', 'NENE DENT', 'Anestésico tópico que alivia a dor e a coceira comuns no surgimento da primeira dentição.', 20.00, 100, 'storage/products/p_1757801963_71df98.jpg', 1, 0, NULL, NULL, '2025-09-13 22:19:23', '2025-09-13 22:19:23'),
(174, 1, 'FXD - 1106', 'NEOSADINA', 'analgésica (diminui a dor) e antiespasmódica (diminui contração involuntária) .', 20.00, 99, 'storage/products/p_1757802180_0beb2d.jpg', 1, 0, NULL, NULL, '2025-09-13 22:23:00', '2025-09-16 03:17:44'),
(175, 7, 'FXD - 1107', 'NEOSORO ADULTO', 'congestão nasal', 15.00, 100, 'storage/products/p_1757802323_378320.jpg', 1, 0, NULL, NULL, '2025-09-13 22:25:23', '2025-09-13 22:25:23'),
(176, 7, 'FXD - 1108', 'NEOSORO INFANTIL', 'congestão nasal', 15.00, 100, 'storage/products/p_1757802433_f8e343.jpg', 1, 0, NULL, NULL, '2025-09-13 22:27:13', '2025-09-13 22:27:13'),
(177, 3, 'FXD - 1109', 'NIMESULIDA COMPRIMIDO', 'é um anti-inflamatório não esteroide com ação anti-inflamatória, analgésica e antipirética (contra a febre).', 15.00, 100, 'storage/products/p_1757802733_61bf32.jpg', 1, 0, NULL, NULL, '2025-09-13 22:32:13', '2025-09-13 22:32:13'),
(178, 8, 'FXD - 1110', 'NISTATINA + AXIDO DE ZINCO', 'indicada para assaduras de bebês.', 25.00, 100, 'storage/products/p_1757803043_e310e9.jpg', 1, 0, NULL, NULL, '2025-09-13 22:37:23', '2025-09-13 22:37:23'),
(179, 8, 'FXD - 1111', 'NISTATINA LIQUIDA', 'antifúngico', 25.00, 100, 'storage/products/p_1757803449_00b827.jpg', 1, 0, NULL, NULL, '2025-09-13 22:44:09', '2025-09-13 22:44:09'),
(180, 8, 'FXD - 1112', 'NISTATINA POMADA VAGINAL', 'prescrita para tratar candidíase vaginal.', 30.00, 100, 'storage/products/p_1757803673_fc6644.jpg', 1, 0, NULL, NULL, '2025-09-13 22:47:53', '2025-09-13 22:47:53'),
(181, 6, 'FXD - 1113', 'NORDETTE', 'anticoncepcional hormonal oral', 20.00, 100, 'storage/products/p_1757803769_f8fecd.jpg', 1, 0, NULL, NULL, '2025-09-13 22:49:29', '2025-09-13 22:49:29'),
(182, 2, 'FXD - 1114', 'NORFLOXACINO', 'tratamento de alguns tipos de infecção bacteriana.', 30.00, 100, 'storage/products/p_1757804005_5f55c0.jpg', 1, 0, NULL, NULL, '2025-09-13 22:53:25', '2025-09-13 22:53:25'),
(183, 1, 'FXD - 1115', 'NOVALGINA LIQUIDA', 'controle da febre', 20.00, 100, 'storage/products/p_1757804270_2df16e.jpg', 1, 0, NULL, NULL, '2025-09-13 22:57:50', '2025-09-13 22:57:50'),
(184, 6, 'FXD - 1116', 'ORLISTATE', 'emagrecimento', 50.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-14 13:19:33', '2025-09-14 13:19:33'),
(185, 3, 'FXD - 1117', 'OTOSYLASE', 'tratamento de otite externa', 25.00, 100, 'storage/products/p_1757857092_919221.jpg', 1, 0, NULL, NULL, '2025-09-14 13:38:12', '2025-09-14 13:38:12'),
(186, 5, 'FXD -1118', 'PANTOPRAZOL', 'indicado para reduzir a acidez estomacal e os sintomas em casos de gastrite.', 25.00, 100, 'storage/products/p_1757857447_7705c8.jpg', 1, 0, NULL, NULL, '2025-09-14 13:44:07', '2025-09-14 13:44:07'),
(187, 4, 'FXD - 1119', 'PERLUTAM INJETAVEL', 'Contraceptivo', 40.00, 100, 'storage/products/p_1757858001_aa4257.jpg', 1, 0, NULL, NULL, '2025-09-14 13:53:21', '2025-09-14 13:53:21'),
(188, 4, 'FXD - 1120', 'DIAD ( PIPULA DO DIA SEGUINTE)', 'usada em até 24 horas após a relação sexual.', 25.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-14 13:57:08', '2025-09-14 13:57:08'),
(189, 1, 'FXD - 1121', 'PREGABALINA', 'um potente analgésico, usado para tratar doenças cujas dores são intensas, como neuropatia diabética e fibromialgia.', 35.00, 100, 'storage/products/p_1757858496_761c82.jpg', 1, 0, NULL, NULL, '2025-09-14 14:01:36', '2025-09-14 14:01:36'),
(190, 3, 'FXD - 1122', 'PREDNIS0LONA 20 MG', 'tratamento de doenças inflamatórias e autoimunes em geral — como artrite reumatoide, asma, dermatites alérgicas e lúpus eritematoso — além de poder integrar esquemas terapêuticos em algumas neoplasias.', 20.00, 100, 'storage/products/p_1757864056_2bb92d.jpg', 1, 0, NULL, NULL, '2025-09-14 15:34:16', '2025-09-17 18:11:02'),
(191, 7, 'FXD - 1123', 'PREDNISONA', 'Para reações alérgicas, inflamações e até condições crônicas e após transplantes', 20.00, 100, 'storage/products/p_1757864214_11dc33.jpg', 1, 0, NULL, NULL, '2025-09-14 15:36:54', '2025-09-17 18:09:43'),
(192, 4, 'FXD 1124', 'PREVIANE', 'contraceptivo oral combinado.', 20.00, 100, 'storage/products/p_1757864944_157642.jpg', 1, 0, NULL, NULL, '2025-09-14 15:49:04', '2025-09-14 15:49:04'),
(193, 4, 'FXD - 1125', 'PRIMERA 20', 'anticoncepcional oral', 20.00, 100, 'storage/products/p_1757865070_f01033.jpg', 1, 0, NULL, NULL, '2025-09-14 15:51:10', '2025-09-14 15:52:14'),
(194, 3, 'FXD - 1126', 'PRIMOSISTON', 'CORTA FLUXO SANGUINEO', 25.00, 100, 'storage/products/p_1757865192_ab75e6.webp', 1, 0, NULL, NULL, '2025-09-14 15:53:12', '2025-09-14 15:53:12'),
(195, 4, 'FXD - 1127', 'PROPOLIS', 'fortalecer o sistema imunológico', 20.00, 100, 'storage/products/p_1757865724_eddda3.jpg', 1, 0, NULL, NULL, '2025-09-14 16:02:04', '2025-09-14 16:03:14'),
(196, 4, 'FXD - 1128', 'PURAN', 'indicado para a terapia de reposição hormonal.', 30.00, 100, 'storage/products/p_1757866104_1d6d68.jpg', 1, 0, NULL, NULL, '2025-09-14 16:08:24', '2025-09-14 16:08:24'),
(197, 4, 'FXD - 1129', 'REPOPIL', 'erve para o tratamento de doenças andrógeno-dependentes na mulher, como acne pronunciada, seborreia, hirsutismo (excesso de pelos) e síndrome dos ovários policísticos (SOP). Além disso, atua como contraceptivo oral.', 20.00, 100, 'storage/products/p_1757866317_816a3f.jpg', 1, 0, NULL, NULL, '2025-09-14 16:11:57', '2025-09-14 16:11:57'),
(198, 3, 'FXD - 1130', 'RIFOCINA', 'um medicamento antibiótico em spray para uso tópico, contendo rifamicina sódica como princípio ativo, é usado para tratar infecções de superfície, como feridas, queimaduras.', 15.00, 5, 'storage/products/p_1757866495_dceedd.jpg', 1, 0, NULL, NULL, '2025-09-14 16:14:55', '2025-09-19 17:33:45'),
(199, 6, 'FXD - 1131', 'RITALINA 10 MG', 'VENDA CONTROLADA', 50.00, 100, 'storage/products/p_1757866659_7c4f46.jpg', 1, 0, NULL, NULL, '2025-09-14 16:17:39', '2025-09-14 16:17:39'),
(200, 4, 'FXD - 1132', 'SAUDE DA MULHER', '', 30.00, 100, 'storage/products/p_1757866758_7be2f1.jpg', 1, 0, NULL, NULL, '2025-09-14 16:19:18', '2025-09-14 16:19:36'),
(201, 6, 'FXD - 1133', 'SICNIDAZOL', 'é um medicamento parasiticida (que elimina parasita), utilizado no tratamento de giardíase, amebíase intestinal sob todas as formas, amebíase no fígado e tricomoníase.', 20.00, 100, 'storage/products/p_1757868265_09217f.jpg', 1, 0, NULL, NULL, '2025-09-14 16:44:25', '2025-09-14 16:44:25'),
(202, 4, 'FXD - 1134', 'SELENE', 'Anticoncepcional', 20.00, 100, 'storage/products/p_1757868412_e534a1.jpg', 1, 0, NULL, NULL, '2025-09-14 16:46:52', '2025-09-14 16:46:52'),
(203, 6, 'FXD - 1135', 'SERTRALINA', 'antidepressivo', 40.00, 100, 'storage/products/p_1757868630_9af2ec.jpg', 1, 0, NULL, NULL, '2025-09-14 16:50:30', '2025-09-14 16:50:30'),
(204, 6, 'FXD - 1136', 'SIBUTRAMINA', 'VENDA CONTROLADA', 50.00, 100, 'storage/products/p_1757868833_a43692.jpg', 1, 0, NULL, NULL, '2025-09-14 16:53:53', '2025-09-14 16:53:53'),
(205, 3, 'FXD - 1137', 'SILDENAFILA', 'um princípio ativo usado no tratamento da disfunção erétil.', 20.00, 100, 'storage/products/p_1757869085_9369f5.jpg', 1, 0, NULL, NULL, '2025-09-14 16:58:05', '2025-09-14 16:58:05'),
(206, 5, 'FXD - 1138', 'SIMETICONA GOTAS', 'excesso de gases no aparelho digestivo', 15.00, 100, 'storage/products/p_1757869330_4d121c.jpg', 1, 0, NULL, NULL, '2025-09-14 17:02:10', '2025-09-14 17:05:33'),
(207, 5, 'FXD - 1139', 'SIMETICONA COMPRIMIDO', 'excesso de gases no aparelho digestivo', 20.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-14 17:05:17', '2025-09-14 17:05:17'),
(208, 8, 'FXD - 1140', 'SUAVICID', 'TRATAMENTO PARA MELASMA', 50.00, 100, 'storage/products/p_1757869684_4f003e.jpg', 1, 0, NULL, NULL, '2025-09-14 17:08:04', '2025-09-14 17:08:04'),
(209, 4, 'FXD - 1141', 'SULFATO FERROSO ADULTO', 'prevenção de anemia', 30.00, 100, 'storage/products/p_1757870144_cd42ba.jpg', 1, 0, NULL, NULL, '2025-09-14 17:15:44', '2025-09-14 17:15:44'),
(210, 4, 'FXD - 1142', 'SULFATO FERROSO INFANTIL', 'prevenção de anemia', 30.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-14 17:18:29', '2025-09-14 17:18:29'),
(211, 4, 'FXD - 1143', 'TADALAFILA 20 MG', 'disfunção erétil.', 25.00, 100, 'storage/products/p_1757870518_3f8eb5.jpg', 1, 0, NULL, NULL, '2025-09-14 17:21:58', '2025-09-14 17:24:47'),
(212, 4, 'FXD - 1144', 'TADALAFILA  5 MG', 'disfunção erétil.', 35.00, 99, NULL, 1, 0, NULL, NULL, '2025-09-14 17:24:27', '2025-09-16 03:17:44'),
(213, 4, 'FXD - 1145', 'TAMISA 20', 'Anticoncepcional', 20.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-14 18:03:58', '2025-09-14 18:03:58'),
(214, 4, 'FXD - 1146', 'TAMISA 30', 'Anticoncepcional', 20.00, 100, NULL, 1, 0, NULL, NULL, '2025-09-14 18:06:11', '2025-09-14 18:06:11'),
(215, 1, 'FXD - 1147', 'TANDENE', 'tratamento de reumatismo', 20.00, 100, 'storage/products/p_1757873331_84768d.jpg', 1, 0, NULL, NULL, '2025-09-14 18:08:51', '2025-09-17 18:06:02'),
(216, 1, 'FXD - 1148', 'TANDRILAX', 'relaxante muscular, antiinflamatório e analgésico.', 20.00, 100, 'storage/products/p_1757873438_12bc00.jpg', 1, 0, NULL, NULL, '2025-09-14 18:10:38', '2025-09-17 18:05:47'),
(217, 1, 'FXD - 1149', 'TORCILAX 10 COMPRIMIDOS', 'relaxante muscular, anti-inflamatória e analgésica.', 15.00, 100, 'storage/products/p_1757873579_61d50b.jpg', 1, 0, NULL, NULL, '2025-09-14 18:12:59', '2025-09-17 18:05:27'),
(218, 1, 'FXD - 1150', 'TRAMADOL', 'analgésico', 30.00, 100, 'storage/products/p_1757873702_c75dd2.jpg', 1, 0, NULL, NULL, '2025-09-14 18:15:02', '2025-09-14 18:15:15'),
(219, 4, 'FXD - 1151', 'VIAGRA', 'estimulador sexual', 20.00, 97, 'storage/products/p_1757873958_c6cd83.jpg', 1, 0, NULL, NULL, '2025-09-14 18:19:18', '2025-09-18 17:25:07'),
(220, 8, 'FXD - 1152', 'VITACID 0,5mg', 'Combate a acne', 50.00, 100, 'storage/products/p_1757878863_04d6a3.jpg', 1, 0, NULL, NULL, '2025-09-14 19:41:03', '2025-09-17 18:05:04'),
(221, 4, 'FXD - 1153', 'VITAMINA D', 'para o sistema imunológico, digestivo, circulatório e nervoso.', 20.00, 100, 'storage/products/p_1757879398_500066.jpg', 1, 0, NULL, NULL, '2025-09-14 19:49:58', '2025-09-14 19:49:58'),
(222, 5, 'FXD -1154', 'VOMISTOP', 'alívio de náuseas e vômitos.', 15.00, 100, 'storage/products/p_1757879605_a76a9a.jpg', 1, 0, NULL, NULL, '2025-09-14 19:53:25', '2025-09-14 19:53:25'),
(223, 8, 'FXD - 1155', 'YASMIN', 'Anticoncepcional', 20.00, 100, 'storage/products/p_1757879790_bf2748.jpg', 1, 0, NULL, NULL, '2025-09-14 19:56:30', '2025-09-19 17:35:40'),
(224, 49, 'FXD - 1156', 'ZOLPIDEM', 'é um medicamento hipnótico usado no tratamento de distúrbios do sono.', 40.00, 100, 'storage/products/p_1757879934_be3bab.jpg', 1, 0, NULL, NULL, '2025-09-14 19:58:54', '2025-09-19 17:35:30');

-- --------------------------------------------------------

--
-- Estrutura para tabela `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `skey` varchar(191) NOT NULL,
  `svalue` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `settings`
--

INSERT INTO `settings` (`id`, `skey`, `svalue`, `updated_at`) VALUES
(1, 'store.name', 'Farma Fácill', '2025-08-26 22:45:04'),
(2, 'store.support_email', 'contato@farmafacil.com', '2025-08-26 22:45:04'),
(3, 'store.phone', '(82) 99999-9999', '2025-08-26 22:45:04'),
(4, 'store.address', 'Maceió, Alagoas, Brasil', '2025-08-26 22:45:04'),
(5, 'store.currency', 'U$', '2025-08-26 22:45:04'),
(6, 'store.logo', 'storage/logo/logo.png', '2025-08-26 22:32:55'),
(68, 'store_name', 'Victor Farma Fácil', '2025-08-27 19:10:45'),
(69, 'store_email', 'contato@farmafacil.com', '2025-08-27 19:10:45'),
(70, 'store_phone', '(82) 99999-9999', '2025-08-27 19:10:45'),
(71, 'store_address', 'Maceió, Alagoas, Brasil', '2025-08-27 19:10:45'),
(72, 'store_logo', 'storage/logo/logo.png', '2025-08-26 23:07:45'),
(89, 'store_logo_url', 'storage/logo/logo_1756301340.png', '2025-08-27 13:29:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `display_name` varchar(150) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `email`, `pass`, `role`, `created_at`, `display_name`, `active`) VALUES
(1, 'admin@farmafacil.com', '$2y$10$6uCU/mzV6mPKV6YfchTLmepjUe8P7Yg.qLo4zqTx9jArUl3syX5qG\n', 'admin', '2025-08-26 08:23:23', NULL, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Índices de tabela `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Índices de tabela `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_settings_skey` (`skey`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de tabela `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT de tabela `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=225;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

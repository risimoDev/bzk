-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:Please specify the port the MySQL database on the remote host is running on. To use the default port, leave this field blank.
-- Время создания: Сен 30 2025 г., 18:52
-- Версия сервера: 8.0.42-0ubuntu0.20.04.1
-- Версия PHP: 7.4.3-4ubuntu2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `shop`
--

-- --------------------------------------------------------

--
-- Структура таблицы `account_deletion_requests`
--

CREATE TABLE `account_deletion_requests` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `account_deletion_requests`
--

INSERT INTO `account_deletion_requests` (`id`, `user_id`, `reason`, `status`, `created_at`, `processed_at`, `processed_by`, `rejection_reason`) VALUES
(1, 10, ' Тест', 'pending', '2025-09-30 15:37:11', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `attribute_values`
--

CREATE TABLE `attribute_values` (
  `id` int NOT NULL,
  `attribute_id` int NOT NULL,
  `value` varchar(255) NOT NULL,
  `price_modifier` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `attribute_values`
--

INSERT INTO `attribute_values` (`id`, `attribute_id`, `value`, `price_modifier`) VALUES
(1, 1, 'Полуглянец', '0.00'),
(2, 1, 'Матовая', '1.20'),
(3, 1, 'Дизайнерская', '1.50'),
(8, 3, '80г/м2', '0.00'),
(9, 3, '120г/м2', '8.00'),
(10, 3, '150г/м2', '10.00'),
(11, 3, '300г/м2', '20.00'),
(12, 4, 'Без заливки', '0.00'),
(13, 4, 'Полная заливка', '0.00');

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Полиграфия'),
(2, 'Услуги дизайна'),
(3, 'Широкоформатная печать'),
(4, 'Сувенирная продукция');

-- --------------------------------------------------------

--
-- Структура таблицы `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int NOT NULL,
  `chat_id` int NOT NULL,
  `user_id` int NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Сообщения в чатах заказов';

-- --------------------------------------------------------

--
-- Структура таблицы `chat_message_reads`
--

CREATE TABLE `chat_message_reads` (
  `id` int NOT NULL,
  `message_id` int NOT NULL,
  `user_id` int NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Отметки о прочтении сообщений';

-- --------------------------------------------------------

--
-- Структура таблицы `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `preferred_contact` enum('phone','telegram','whatsapp','email') COLLATE utf8mb4_general_ci DEFAULT 'email',
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `agreement` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('new','read') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'new'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `preferred_contact`, `message`, `agreement`, `created_at`, `status`) VALUES
(1, 'Lev', 'lol.chel@hastle.com', '+7 (950) 458-42-63', 'telegram', 'Есть предложение по работе', 0, '2025-09-20 23:10:04', 'read'),
(2, 'Владислав', 'mr.bessarab2002@mail.ru', '+7 (919) 704-43-98', 'email', 'АБОБА', 0, '2025-09-22 11:14:43', 'read');

-- --------------------------------------------------------

--
-- Структура таблицы `corporate_account_requests`
--

CREATE TABLE `corporate_account_requests` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `inn` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `kpp` varchar(9) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legal_address` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `corporate_account_requests`
--

INSERT INTO `corporate_account_requests` (`id`, `user_id`, `company_name`, `inn`, `kpp`, `legal_address`, `status`, `created_at`, `processed_at`, `processed_by`, `rejection_reason`) VALUES
(1, 10, 'ООО \"Тестировка\"', '5900000000', '53448889', 'Ул. Тестирование 78 г.Пермь', 'pending', '2025-09-30 15:44:23', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `discounts`
--

CREATE TABLE `discounts` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `discount_value` decimal(5,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Структура таблицы `expenses_categories`
--

CREATE TABLE `expenses_categories` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `expenses_categories`
--

INSERT INTO `expenses_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Материалы', 'Расходные материалы для производства', '2025-08-21 23:40:50'),
(2, 'Зарплата', 'Заработная плата сотрудников', '2025-08-21 23:40:50'),
(3, 'Аренда', 'Аренда помещения', '2025-08-21 23:40:50'),
(4, 'Коммунальные услуги', 'Электричество, вода, интернет и т.д.', '2025-08-21 23:40:50'),
(5, 'Реклама', 'Расходы на рекламу и маркетинг', '2025-08-21 23:40:50'),
(6, 'Оборудование', 'Покупка и ремонт оборудования', '2025-08-21 23:40:50'),
(7, 'Доставка', 'Расходы на доставку товаров', '2025-08-21 23:40:50'),
(8, 'Налоги', 'Налоговые платежи', '2025-08-21 23:40:50'),
(9, 'Прочее', 'Прочие расходы', '2025-08-21 23:40:50'),
(10, 'Автоматические расходы', NULL, '2025-08-22 00:59:31');

-- --------------------------------------------------------

--
-- Структура таблицы `external_orders`
--

CREATE TABLE `external_orders` (
  `id` int NOT NULL,
  `client_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `description` text COLLATE utf8mb4_general_ci,
  `status` enum('unpaid','partial','paid') COLLATE utf8mb4_general_ci DEFAULT 'unpaid',
  `total_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `external_orders`
--

INSERT INTO `external_orders` (`id`, `client_name`, `email`, `phone`, `address`, `description`, `status`, `total_price`, `created_at`) VALUES
(1, 'Клиент 233', '', '', '', '', 'unpaid', '2850.00', '2025-09-17 21:53:32');

-- --------------------------------------------------------

--
-- Структура таблицы `external_order_items`
--

CREATE TABLE `external_order_items` (
  `id` int NOT NULL,
  `external_order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT '0',
  `item_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_description` text COLLATE utf8mb4_general_ci,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expense_amount` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `external_order_items`
--

INSERT INTO `external_order_items` (`id`, `external_order_id`, `product_id`, `is_custom`, `item_name`, `item_description`, `quantity`, `price`, `expense_amount`) VALUES
(1, 1, NULL, 1, 'Медали', '', 30, '2850.00', '2340.00');

-- --------------------------------------------------------

--
-- Структура таблицы `general_expenses`
--

CREATE TABLE `general_expenses` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expense_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mass_messages`
--

CREATE TABLE `mass_messages` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `message_type` enum('email','telegram','both') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'both',
  `target_audience` enum('all','customers','admins','managers','specific') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all',
  `specific_user_ids` json DEFAULT NULL COMMENT 'For specific audience targeting',
  `status` enum('draft','scheduled','sending','sent','failed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  `total_recipients` int DEFAULT '0',
  `emails_sent` int DEFAULT '0',
  `telegrams_sent` int DEFAULT '0',
  `emails_failed` int DEFAULT '0',
  `telegrams_failed` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mass_message_recipients`
--

CREATE TABLE `mass_message_recipients` (
  `id` int NOT NULL,
  `mass_message_id` int NOT NULL,
  `user_id` int NOT NULL,
  `email_status` enum('pending','sent','failed','skipped') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `telegram_status` enum('pending','sent','failed','skipped') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `telegram_sent_at` timestamp NULL DEFAULT NULL,
  `email_error` text COLLATE utf8mb4_general_ci,
  `telegram_error` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `materials`
--

CREATE TABLE `materials` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `materials`
--

INSERT INTO `materials` (`id`, `name`, `unit`, `cost_per_unit`, `description`, `created_at`) VALUES
(4, 'Визитки', 'шт', '2.00', '', '2025-09-08 19:36:43'),
(5, 'Флаг', 'Шт', '800.00', NULL, '2025-09-20 23:18:58');

-- --------------------------------------------------------

--
-- Структура таблицы `materials_movements`
--

CREATE TABLE `materials_movements` (
  `id` int NOT NULL,
  `material_id` int NOT NULL,
  `type` enum('in','out') COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference_type` enum('order','manual') COLLATE utf8mb4_general_ci NOT NULL,
  `reference_id` int DEFAULT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `materials_movements`
--

INSERT INTO `materials_movements` (`id`, `material_id`, `type`, `quantity`, `reference_type`, `reference_id`, `comment`, `created_at`) VALUES
(9, 4, 'in', '1000.00', 'manual', NULL, '', '2025-09-08 19:36:52');

-- --------------------------------------------------------

--
-- Структура таблицы `materials_stock`
--

CREATE TABLE `materials_stock` (
  `id` int NOT NULL,
  `material_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `materials_stock`
--

INSERT INTO `materials_stock` (`id`, `material_id`, `quantity`) VALUES
(4, 4, '1000.00'),
(5, 5, '0.00');

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `active` tinyint(1) DEFAULT '1',
  `target_audience` enum('all','clients','admins','managers') DEFAULT 'all',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `active`, `target_audience`, `start_date`, `end_date`, `created_at`) VALUES
(1, 'ВНИМАНИЕ!', 'Сайт находится в тестировочном режиме, вся информация внесенная вами до 20.10.2025 будет удалена', 'warning', 1, 'all', NULL, NULL, '2025-09-15 22:46:11'),
(2, 'Бля надо протестировать', 'Бля надо протестировать то что я понаписал здесь, на работоспособность и отображение', 'info', 0, 'admins', NULL, NULL, '2025-09-20 23:41:33');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `is_urgent` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','processing','shipped','delivered','cancelled','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_address` text NOT NULL,
  `contact_info` text NOT NULL,
  `is_new` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_price`, `is_urgent`, `status`, `created_at`, `shipping_address`, `contact_info`, `is_new`) VALUES
(13, 2, '8850.00', 1, 'pending', '2025-09-30 15:29:16', 'улица Энергетиков, 9, Пермь', '{\"name\":\"\\u041b\\u0435\\u0432\",\"email\":\"risimo2014@yandex.ru\",\"phone\":\"+7 (950) 458-42-63\",\"comment\":\"\",\"is_urgent\":true,\"original_total_price\":7500,\"urgent_fee\":3750,\"promo_data\":{\"code\":\"bzkprint\",\"discount\":2400,\"discount_type\":\"percentage\",\"discount_value\":\"20.00\"}}', 1),
(14, 10, '3599900.00', 1, 'pending', '2025-09-30 18:13:01', 'Testtesttest', '{\"name\":\"Test\",\"email\":\"test220022@test.ru\",\"phone\":\"+7 (912) 483-03-99\",\"comment\":\"Test\",\"is_urgent\":true,\"original_total_price\":2400000,\"urgent_fee\":1200000,\"promo_data\":{\"code\":\"bzk\",\"discount\":\"100.00\",\"discount_type\":\"fixed\",\"discount_value\":\"100.00\"}}', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `orders_accounting`
--

CREATE TABLE `orders_accounting` (
  `id` int NOT NULL,
  `source` enum('site','external') NOT NULL DEFAULT 'site',
  `order_id` int DEFAULT NULL,
  `external_order_id` int DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `income` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_expense` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estimated_expense` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Бухгалтерия заказов';

--
-- Дамп данных таблицы `orders_accounting`
--

INSERT INTO `orders_accounting` (`id`, `source`, `order_id`, `external_order_id`, `client_name`, `income`, `total_expense`, `estimated_expense`, `tax_amount`, `status`, `created_at`) VALUES
(9, 'external', NULL, 1, 'Клиент 233', '2850.00', '2340.00', '2340.00', '171.00', 'unpaid', '2025-09-17 21:53:32'),
(15, 'site', 13, NULL, 'Лев', '8850.00', '4000.00', '4000.00', '531.00', 'unpaid', '2025-09-30 15:29:16'),
(16, 'site', 14, NULL, 'Test', '3599900.00', '960000.00', '960000.00', '215994.00', 'unpaid', '2025-09-30 18:13:01');

-- --------------------------------------------------------

--
-- Структура таблицы `orders_accounting_old`
--

CREATE TABLE `orders_accounting_old` (
  `id` int NOT NULL,
  `source` enum('site','external') NOT NULL,
  `order_id` int DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `description` text,
  `income` decimal(10,2) DEFAULT '0.00',
  `total_expense` decimal(10,2) DEFAULT '0.00',
  `estimated_expense` decimal(10,2) DEFAULT '0.00',
  `status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Структура таблицы `order_chats`
--

CREATE TABLE `order_chats` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `assigned_user_id` int DEFAULT NULL COMMENT 'ID назначенного менеджера/админа',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Чаты по заказам';

-- --------------------------------------------------------

--
-- Структура таблицы `order_expenses`
--

CREATE TABLE `order_expenses` (
  `id` int NOT NULL,
  `order_accounting_id` int NOT NULL,
  `order_item_id` int DEFAULT NULL,
  `material_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,4) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expense_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `order_expenses`
--

INSERT INTO `order_expenses` (`id`, `order_accounting_id`, `order_item_id`, `material_name`, `quantity`, `unit`, `cost_per_unit`, `total_cost`, `category_id`, `amount`, `description`, `created_at`, `expense_date`) VALUES
(13, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2340.00', 'Автоматический расчет себестоимости материалов', '2025-09-17 21:53:32', '2025-09-18 02:53:32'),
(19, 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '4000.00', 'Автоматический расчет себестоимости материалов', '2025-09-30 15:29:16', '2025-09-30 15:29:16'),
(20, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '960000.00', 'Автоматический расчет себестоимости материалов', '2025-09-30 18:13:01', '2025-09-30 18:13:01');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT '0',
  `item_name` varchar(255) DEFAULT NULL,
  `item_note` text,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `attributes` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `is_custom`, `item_name`, `item_note`, `quantity`, `unit_price`, `price`, `attributes`) VALUES
(18, 13, 7, 0, NULL, NULL, 5, NULL, '7500.00', '{\"4\": \"12\"}'),
(19, 14, 4, 0, NULL, NULL, 480000, NULL, '2400000.00', '{\"1\": \"3\"}');

-- --------------------------------------------------------

--
-- Структура таблицы `order_payments`
--

CREATE TABLE `order_payments` (
  `id` int NOT NULL,
  `order_accounting_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `note` text,
  `payment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Структура таблицы `order_promocodes`
--

CREATE TABLE `order_promocodes` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `promo_code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `applied_discount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `order_promocodes`
--

INSERT INTO `order_promocodes` (`id`, `order_id`, `promo_code`, `discount_type`, `discount_value`, `applied_discount`, `created_at`) VALUES
(7, 13, 'bzkprint', 'percentage', '20.00', '2400.00', '2025-09-30 15:29:16'),
(8, 14, 'bzk', 'fixed', '100.00', '100.00', '2025-09-30 18:13:01');

-- --------------------------------------------------------

--
-- Структура таблицы `partners`
--

CREATE TABLE `partners` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `partners`
--

INSERT INTO `partners` (`id`, `name`, `logo_url`) VALUES
(2, 'РФСОО \"ФКПК\"', '/uploads/partner_68927b60a3642_1754430304.png');

-- --------------------------------------------------------

--
-- Структура таблицы `password_reset_attempts`
--

CREATE TABLE `password_reset_attempts` (
  `id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `password_reset_attempts`
--

INSERT INTO `password_reset_attempts` (`id`, `ip_address`, `email`, `created_at`) VALUES
(1, '193.233.126.132', 'risimo2014@yandex.ru', '2025-09-22 03:43:23'),
(2, '193.233.126.132', 'bzk@risimolev.ru', '2025-09-22 03:46:53'),
(3, '193.233.126.132', 'bzk@risimolev.ru', '2025-09-22 03:48:00'),
(4, '217.19.4.130', 'nik-nekrasov@list.ru', '2025-09-30 12:07:27'),
(5, '217.19.4.130', 'nik-nekrasov@list.ru', '2025-09-30 12:07:28'),
(6, '217.19.4.130', 'nik-nekrasov@list.ru', '2025-09-30 13:09:37');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `base_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `discount` decimal(5,2) DEFAULT '0.00',
  `is_popular` tinyint(1) DEFAULT '0',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `category_id` int DEFAULT NULL,
  `type` enum('product','service') DEFAULT 'product',
  `multiplicity` int NOT NULL DEFAULT '1' COMMENT 'Кратность заказа (например, 10 штук)',
  `min_quantity` int NOT NULL DEFAULT '1' COMMENT 'Минимальное количество для заказа',
  `unit` varchar(50) DEFAULT 'шт.' COMMENT 'Единица измерения'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `base_price`, `created_at`, `discount`, `is_popular`, `is_hidden`, `category_id`, `type`, `multiplicity`, `min_quantity`, `unit`) VALUES
(4, 'Визитки', 'Визитки', '3.50', '2025-08-04 23:12:48', '0.00', 1, 1, 1, 'product', 24, 48, 'шт.'),
(6, 'Листовки', 'листовки', '15.00', '2025-08-27 03:35:15', '0.00', 0, 1, 1, 'product', 10, 10, 'шт.'),
(7, 'Флаги', 'Флаги на флажной ткани', '1600.00', '2025-09-20 23:12:51', '0.00', 1, 0, 4, 'product', 1, 1, 'шт.');

-- --------------------------------------------------------

--
-- Структура таблицы `product_attributes`
--

CREATE TABLE `product_attributes` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('radio','select','text') DEFAULT 'radio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `product_attributes`
--

INSERT INTO `product_attributes` (`id`, `product_id`, `name`, `type`) VALUES
(1, 4, 'Бумага', 'radio'),
(3, 6, 'Плотность', 'radio'),
(4, 7, 'Заливка', 'radio');

-- --------------------------------------------------------

--
-- Структура таблицы `product_expenses`
--

CREATE TABLE `product_expenses` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `quantity_per_unit` decimal(10,4) NOT NULL DEFAULT '1.0000',
  `unit` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Структура таблицы `product_images`
--

CREATE TABLE `product_images` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_main` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `is_main`) VALUES
(1, 4, '/uploads/68a6221dce679_1755718173.jpg', 1),
(2, 4, '/uploads/68a62227c4936_1755718183.jpg', 0),
(3, 7, '/uploads/68cf37066a963_1758410502.jpg', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `product_materials`
--

CREATE TABLE `product_materials` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `material_id` int NOT NULL,
  `quantity_per_unit` decimal(10,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `product_materials`
--

INSERT INTO `product_materials` (`id`, `product_id`, `material_id`, `quantity_per_unit`, `created_at`) VALUES
(2, 4, 4, '1.0000', '2025-09-09 17:08:40'),
(3, 7, 5, '1.0000', '2025-09-20 23:20:08');

-- --------------------------------------------------------

--
-- Структура таблицы `product_quantity_prices`
--

CREATE TABLE `product_quantity_prices` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `min_qty` int NOT NULL,
  `max_qty` int DEFAULT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `product_quantity_prices`
--

INSERT INTO `product_quantity_prices` (`id`, `product_id`, `min_qty`, `max_qty`, `price`) VALUES
(1, 4, 48, 120, '9.00'),
(2, 4, 121, 240, '6.00'),
(3, 7, 2, 5, '1500.00'),
(4, 7, 6, 10, '1470.00'),
(5, 7, 11, 30, '1400.00'),
(6, 7, 31, 100, '1250.00');

-- --------------------------------------------------------

--
-- Структура таблицы `promocodes`
--

CREATE TABLE `promocodes` (
  `id` int NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `usage_limit` int DEFAULT NULL,
  `used_count` int DEFAULT '0',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `promocodes`
--

INSERT INTO `promocodes` (`id`, `code`, `discount_type`, `discount_value`, `usage_limit`, `used_count`, `start_date`, `end_date`, `is_active`, `created_at`) VALUES
(4, 'bzkprint', 'percentage', '20.00', NULL, 9, NULL, NULL, 1, '2025-08-26 23:14:51'),
(5, 'bzk', 'fixed', '100.00', NULL, 8, NULL, NULL, 1, '2025-08-26 23:15:11');

-- --------------------------------------------------------

--
-- Структура таблицы `seo_settings`
--

CREATE TABLE `seo_settings` (
  `id` int NOT NULL,
  `page` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `keywords` text COLLATE utf8mb4_general_ci,
  `og_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `og_description` text COLLATE utf8mb4_general_ci,
  `og_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `settings`
--

INSERT INTO `settings` (`id`, `name`, `value`) VALUES
(1, 'tax_rate', '6');

-- --------------------------------------------------------

--
-- Структура таблицы `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `service_cost` decimal(10,2) DEFAULT NULL,
  `payment_terms` text COLLATE utf8mb4_general_ci,
  `delivery_terms` text COLLATE utf8mb4_general_ci,
  `notes` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `website`, `address`, `service_cost`, `payment_terms`, `delivery_terms`, `notes`, `is_active`, `created_at`) VALUES
(1, 'Зенон', '', '', '', '', '', NULL, '', '', '', 1, '2025-09-26 01:16:14');

-- --------------------------------------------------------

--
-- Структура таблицы `tasks`
--

CREATE TABLE `tasks` (
  `id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `task_items` json DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `due_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `task_items`, `assigned_to`, `created_by`, `status`, `priority`, `due_date`, `created_at`) VALUES
(6, 'Забрать баннер РЕЗОН', '01.10 забрать баннер резон и отнести в КИТ до 15.00', NULL, NULL, 8, 'pending', 'medium', '2025-10-02 15:00:00', '2025-09-30 09:53:03');

-- --------------------------------------------------------

--
-- Структура таблицы `task_comments`
--

CREATE TABLE `task_comments` (
  `id` int NOT NULL,
  `task_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telegram_chat_id` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `email_notifications` tinyint(1) DEFAULT '1',
  `sms_notifications` tinyint(1) DEFAULT '1',
  `newsletter` tinyint(1) DEFAULT '1',
  `telegram_notifications` tinyint(1) DEFAULT '1',
  `is_corporate` tinyint(1) DEFAULT '0',
  `company_name` varchar(255) DEFAULT NULL,
  `inn` varchar(12) DEFAULT NULL,
  `kpp` varchar(9) DEFAULT NULL,
  `legal_address` text,
  `role` enum('user','manager','admin') DEFAULT 'user',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `shipping_address` text,
  `remember_token` varchar(64) DEFAULT NULL COMMENT 'Хеш токена для функции "Запомнить меня"',
  `remember_token_expires_at` datetime DEFAULT NULL COMMENT 'Дата и время истечения токена',
  `is_online` tinyint(1) DEFAULT '0' COMMENT '1 - онлайн, 0 - офлайн',
  `last_activity` timestamp NULL DEFAULT NULL COMMENT 'Время последней активности'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `telegram_chat_id`, `phone`, `birthday`, `email_notifications`, `sms_notifications`, `newsletter`, `telegram_notifications`, `is_corporate`, `company_name`, `inn`, `kpp`, `legal_address`, `role`, `is_blocked`, `created_at`, `reset_token`, `reset_token_expires`, `shipping_address`, `remember_token`, `remember_token_expires_at`, `is_online`, `last_activity`) VALUES
(2, 'Лев', 'risimo2014@yandex.ru', '$2y$10$a8h6K.upsjHum3eq733vQ.T2XMhBGzn1Mp/Vjzh6xeiCyaOPH6vNu', '757479170', '+7 (950) 458-42-63', '2002-05-17', 1, 1, 1, 1, 0, NULL, NULL, NULL, NULL, 'admin', 0, '2025-04-07 09:09:12', '996e1730c5e5b4dae862d13393eddd610af05047259c4846ac2154e5cf31990f', '2025-09-22 05:43:23', 'улица Энергетиков, 9, Пермь', NULL, NULL, 1, '2025-08-16 01:43:46'),
(8, 'Никита Заикин', 'nikita.bzk.perm@gmail.com', '$2y$10$1kpyvvIdrkPjSA/x4sYmm.grWu0G64PKB8B/goXd6/kV3oVtNI1Bu', '1018874343', '+7 (952) 646-48-68', '2002-05-28', 1, 1, 1, 1, 0, NULL, NULL, NULL, NULL, 'admin', 0, '2025-09-30 09:30:44', NULL, NULL, '', NULL, NULL, 0, NULL),
(9, 'Николай', 'nik-nekrasov@list.ru', '$2y$10$1I50TjzZcKYQ4H.UdjExtO1W7y.BpUrqjlzHtpI0Jm3V6wauJI0bq', '1125074144', '+7 (950) 477-40-62', NULL, 1, 1, 1, 1, 0, NULL, NULL, NULL, NULL, 'admin', 0, '2025-09-30 10:41:45', 'fc2cc6b9391a1deaa6a4ae70cc1c13e909bf777f2d25e86cad996fad3d23b680', '2025-09-30 15:09:37', NULL, '613ad27a0ac4e8f3e2aa80e7357d4980a0a56aff09ae0bcb337252079156ae08', '2025-10-30 12:09:29', 0, NULL),
(10, 'Test', 'test220022@test.ru', '$2y$10$eaPCwwq8rQ5kGFPZseoBROfxGbIESKUqTjnYDZbhLf16zbK0fXD7.', NULL, '+7 (912) 483-03-99', NULL, 1, 1, 1, 1, 0, NULL, NULL, NULL, NULL, 'user', 0, '2025-09-30 15:32:45', NULL, NULL, NULL, '30c8e5a17f4241325001970f62d7987e2ef7142754d19e5998784bba2649d01e', '2025-10-30 18:10:09', 0, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `user_tags`
--

CREATE TABLE `user_tags` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#000000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_tag_mappings`
--

CREATE TABLE `user_tag_mappings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `tag_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Индексы таблицы `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attribute_id` (`attribute_id`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_message_chat` (`chat_id`),
  ADD KEY `fk_message_user` (`user_id`);

--
-- Индексы таблицы `chat_message_reads`
--
ALTER TABLE `chat_message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_message_read` (`message_id`,`user_id`),
  ADD KEY `fk_read_message` (`message_id`),
  ADD KEY `fk_read_user` (`user_id`);

--
-- Индексы таблицы `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `corporate_account_requests`
--
ALTER TABLE `corporate_account_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Индексы таблицы `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `expenses_categories`
--
ALTER TABLE `expenses_categories`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `external_orders`
--
ALTER TABLE `external_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_external_orders_status` (`status`),
  ADD KEY `idx_external_orders_created_at` (`created_at`);

--
-- Индексы таблицы `external_order_items`
--
ALTER TABLE `external_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `external_order_id` (`external_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `general_expenses`
--
ALTER TABLE `general_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `mass_messages`
--
ALTER TABLE `mass_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_scheduled_at` (`scheduled_at`);

--
-- Индексы таблицы `mass_message_recipients`
--
ALTER TABLE `mass_message_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_message_user` (`mass_message_id`,`user_id`),
  ADD KEY `idx_mass_message_id` (`mass_message_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Индексы таблицы `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `materials_movements`
--
ALTER TABLE `materials_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`);

--
-- Индексы таблицы `materials_stock`
--
ALTER TABLE `materials_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `material_id` (`material_id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_dates` (`start_date`,`end_date`),
  ADD KEY `idx_notifications_audience` (`target_audience`),
  ADD KEY `idx_notifications_active_dates` (`active`,`start_date`,`end_date`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_is_urgent` (`is_urgent`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Индексы таблицы `orders_accounting`
--
ALTER TABLE `orders_accounting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Индексы таблицы `orders_accounting_old`
--
ALTER TABLE `orders_accounting_old`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Индексы таблицы `order_chats`
--
ALTER TABLE `order_chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order` (`order_id`),
  ADD KEY `fk_chat_order` (`order_id`),
  ADD KEY `fk_chat_assigned_user` (`assigned_user_id`);

--
-- Индексы таблицы `order_expenses`
--
ALTER TABLE `order_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_expenses_ibfk_2` (`category_id`),
  ADD KEY `fk_order_expenses_accounting` (`order_accounting_id`),
  ADD KEY `idx_order_item_id` (`order_item_id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `order_payments`
--
ALTER TABLE `order_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_accounting_id` (`order_accounting_id`);

--
-- Индексы таблицы `order_promocodes`
--
ALTER TABLE `order_promocodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Индексы таблицы `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_created` (`ip_address`,`created_at`),
  ADD KEY `idx_email_created` (`email`,`created_at`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `product_expenses`
--
ALTER TABLE `product_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `product_materials`
--
ALTER TABLE `product_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Индексы таблицы `product_quantity_prices`
--
ALTER TABLE `product_quantity_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `promocodes`
--
ALTER TABLE `promocodes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Индексы таблицы `seo_settings`
--
ALTER TABLE `seo_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page` (`page`);

--
-- Индексы таблицы `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`priority`);

--
-- Индексы таблицы `task_comments`
--
ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_is_blocked` (`is_blocked`),
  ADD KEY `idx_users_is_corporate` (`is_corporate`),
  ADD KEY `idx_users_created_at` (`created_at`);

--
-- Индексы таблицы `user_tags`
--
ALTER TABLE `user_tags`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `user_tag_mappings`
--
ALTER TABLE `user_tag_mappings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_tag` (`user_id`,`tag_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `attribute_values`
--
ALTER TABLE `attribute_values`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `chat_message_reads`
--
ALTER TABLE `chat_message_reads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `corporate_account_requests`
--
ALTER TABLE `corporate_account_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `expenses_categories`
--
ALTER TABLE `expenses_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `external_orders`
--
ALTER TABLE `external_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `external_order_items`
--
ALTER TABLE `external_order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `general_expenses`
--
ALTER TABLE `general_expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mass_messages`
--
ALTER TABLE `mass_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `mass_message_recipients`
--
ALTER TABLE `mass_message_recipients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `materials_movements`
--
ALTER TABLE `materials_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `materials_stock`
--
ALTER TABLE `materials_stock`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `orders_accounting`
--
ALTER TABLE `orders_accounting`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `orders_accounting_old`
--
ALTER TABLE `orders_accounting_old`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_chats`
--
ALTER TABLE `order_chats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `order_expenses`
--
ALTER TABLE `order_expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT для таблицы `order_payments`
--
ALTER TABLE `order_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_promocodes`
--
ALTER TABLE `order_promocodes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `product_attributes`
--
ALTER TABLE `product_attributes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `product_expenses`
--
ALTER TABLE `product_expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `product_materials`
--
ALTER TABLE `product_materials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `product_quantity_prices`
--
ALTER TABLE `product_quantity_prices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `promocodes`
--
ALTER TABLE `promocodes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `seo_settings`
--
ALTER TABLE `seo_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `task_comments`
--
ALTER TABLE `task_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `user_tags`
--
ALTER TABLE `user_tags`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_tag_mappings`
--
ALTER TABLE `user_tag_mappings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  ADD CONSTRAINT `deletion_requests_admin_fk` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `deletion_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD CONSTRAINT `attribute_values_ibfk_1` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_message_chat` FOREIGN KEY (`chat_id`) REFERENCES `order_chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `chat_message_reads`
--
ALTER TABLE `chat_message_reads`
  ADD CONSTRAINT `fk_read_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `corporate_account_requests`
--
ALTER TABLE `corporate_account_requests`
  ADD CONSTRAINT `corporate_requests_admin_fk` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `corporate_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `discounts`
--
ALTER TABLE `discounts`
  ADD CONSTRAINT `discounts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `external_order_items`
--
ALTER TABLE `external_order_items`
  ADD CONSTRAINT `external_order_items_ibfk_1` FOREIGN KEY (`external_order_id`) REFERENCES `external_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `external_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mass_messages`
--
ALTER TABLE `mass_messages`
  ADD CONSTRAINT `mass_messages_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mass_message_recipients`
--
ALTER TABLE `mass_message_recipients`
  ADD CONSTRAINT `mass_message_recipients_ibfk_1` FOREIGN KEY (`mass_message_id`) REFERENCES `mass_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mass_message_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `materials_movements`
--
ALTER TABLE `materials_movements`
  ADD CONSTRAINT `materials_movements_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `materials_stock`
--
ALTER TABLE `materials_stock`
  ADD CONSTRAINT `materials_stock_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders_accounting`
--
ALTER TABLE `orders_accounting`
  ADD CONSTRAINT `orders_accounting_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders_accounting_old`
--
ALTER TABLE `orders_accounting_old`
  ADD CONSTRAINT `orders_accounting_old_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `order_chats`
--
ALTER TABLE `order_chats`
  ADD CONSTRAINT `fk_chat_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chat_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_expenses`
--
ALTER TABLE `order_expenses`
  ADD CONSTRAINT `fk_expense_orders_accounting` FOREIGN KEY (`order_accounting_id`) REFERENCES `orders_accounting` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_expenses_accounting` FOREIGN KEY (`order_accounting_id`) REFERENCES `orders_accounting` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_expenses_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_expenses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expenses_categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_payments`
--
ALTER TABLE `order_payments`
  ADD CONSTRAINT `order_payments_ibfk_1` FOREIGN KEY (`order_accounting_id`) REFERENCES `orders_accounting_old` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_promocodes`
--
ALTER TABLE `order_promocodes`
  ADD CONSTRAINT `order_promocodes_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD CONSTRAINT `product_attributes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_expenses`
--
ALTER TABLE `product_expenses`
  ADD CONSTRAINT `product_expenses_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_materials`
--
ALTER TABLE `product_materials`
  ADD CONSTRAINT `product_materials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_quantity_prices`
--
ALTER TABLE `product_quantity_prices`
  ADD CONSTRAINT `product_quantity_prices_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_assigned_to_fk` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `task_comments`
--
ALTER TABLE `task_comments`
  ADD CONSTRAINT `task_comments_task_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_tag_mappings`
--
ALTER TABLE `user_tag_mappings`
  ADD CONSTRAINT `user_tag_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `user_tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_tag_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

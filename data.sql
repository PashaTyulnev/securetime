-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Ноя 04 2025 г., 15:27
-- Версия сервера: 10.6.22-MariaDB-0ubuntu0.22.04.1
-- Версия PHP: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `secsoft`
--

-- --------------------------------------------------------

--
-- Структура таблицы `department`
--

CREATE TABLE `department` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `short_title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `position` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `department`
--

INSERT INTO `department` (`id`, `title`, `short_title`, `description`, `position`) VALUES
(1, 'Objekt- und Werkschutz', 'OWS', 'Bewachung von Gebäuden, Anlagen und Betriebsgeländen', 18),
(2, 'Revierdienst / Kontrollfahrten', 'RD', 'Kontrollfahrten zwischen verschiedenen Objekten', 17),
(3, 'Empfangs- und Pfortendienst', 'EPD', 'Zutrittskontrolle, Besuchermanagement', 16),
(4, 'Veranstaltungsschutz', 'VSD', 'Absicherung von Events, Konzerten, Sportveranstaltungen', 15),
(5, 'Geld- und Werttransport', 'GWT', 'Transport von Bargeld, Wertgegenständen', 14),
(6, 'Personenschutz', 'PS', 'Schutz gefährdeter Personen', 13),
(7, 'Baustellenbewachung', 'BBW', 'Bewachung von Baustellen und Material', 12),
(8, 'Alarmverfolgung', 'AV', 'Reaktion auf Alarme, Interventionsdienst', 11),
(9, 'Interventionsdienst', 'ID', 'Sofortmaßnahmen bei Alarm oder Störungen', 10),
(10, 'Notruf- und Serviceleitstelle', 'NSL', 'Zentrale Überwachung und Koordination', 9),
(11, 'Mobilstreifendienst', 'MSD', 'Mobile Kontrollen und Kontaktdienste', 8),
(12, 'Sicherheitsberatung', 'SB', 'Analyse, Planung und Umsetzung von Sicherheitskonzepten', 7),
(13, 'Einsatzleitung', 'EL', 'Koordination von Sicherheitskräften und Einsätzen', 2),
(14, 'Disposition', 'DISP', 'Dienstplanung, Personal- und Fahrzeugkoordination', 3),
(15, 'Personalabteilung', 'PA', 'Verwaltung von Mitarbeitern und Verträgen', 1),
(16, 'Qualitätsmanagement', 'QM', 'Kontrolle von Abläufen und Standards', 4),
(17, 'Schulungsabteilung / Ausbildung', 'SA', 'Fortbildung, Unterweisungen und Schulungen', 5),
(18, 'Betriebsrat', 'BR', 'Interessenvertretung der Mitarbeiter', 6),
(19, 'Administrator', 'Admin', 'Softwareentwicklung', 6);

-- --------------------------------------------------------

--
-- Структура таблицы `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Дамп данных таблицы `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20251027125151', '2025-10-27 12:51:59', 14),
('DoctrineMigrations\\Version20251027140654', '2025-10-27 14:06:59', 28),
('DoctrineMigrations\\Version20251028122721', '2025-10-28 12:27:26', 22),
('DoctrineMigrations\\Version20251029103424', '2025-10-29 10:34:30', 26),
('DoctrineMigrations\\Version20251029105540', '2025-10-29 10:55:48', 14),
('DoctrineMigrations\\Version20251029161044', '2025-10-29 16:10:50', 55),
('DoctrineMigrations\\Version20251031093326', '2025-10-31 09:33:31', 19),
('DoctrineMigrations\\Version20251031123152', '2025-10-31 12:31:58', 10),
('DoctrineMigrations\\Version20251031123410', '2025-10-31 12:34:15', 12),
('DoctrineMigrations\\Version20251031123733', '2025-10-31 12:37:38', 132),
('DoctrineMigrations\\Version20251031125747', '2025-10-31 12:57:49', 23);

-- --------------------------------------------------------

--
-- Структура таблицы `employee`
--

CREATE TABLE `employee` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `birth_date` date NOT NULL,
  `phone` varchar(255) NOT NULL,
  `number` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `employee`
--

INSERT INTO `employee` (`id`, `first_name`, `last_name`, `birth_date`, `phone`, `number`, `department_id`) VALUES
(1, 'Pavel', 'Tyulnev', '1990-09-09', '01247852', '123', 19),
(2, 'Daniel', 'Geier', '2000-01-01', '0154875421', '321111', 14);

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '(DC2Type:json)' CHECK (json_valid(`roles`)),
  `google_authenticator_secret` varchar(255) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user`
--

INSERT INTO `user` (`id`, `username`, `active`, `email`, `password`, `roles`, `google_authenticator_secret`, `employee_id`) VALUES
(1, 'ptyulnev', 1, 'dgeier@dwd-sicherheit.dee', '$2y$13$3wEFo4VzA648mAAk4XV.4eU6K9HKGWD.o0/TbzfHL77ZCTztEPZ5a', '[\"ROLE_ADMIN\"]', 'YEWVPZ4EKJBUEU3OFOU72HO4EMQNF7WYIZUGC74GHA4AZCE6UVVA', 1),
(2, 'dgeier', 1, 'dgeier@dwd-sicherheit.de', '$2y$13$3wEFo4VzA648mAAk4XV.4eU6K9HKGWD.o0/TbzfHL77ZCTztEPZ5a', '[\"ROLE_ADMIN\"]', 'YEWVPZ4EKJBUEU3OFOU72HO4EMQNF7WYIZUGC74GHA4AZCE6UVVA', 2);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Индексы таблицы `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_5D9F75A1AE80F5DF` (`department_id`);

--
-- Индексы таблицы `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`),
  ADD UNIQUE KEY `UNIQ_8D93D6498C03F15C` (`employee_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `department`
--
ALTER TABLE `department`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT для таблицы `employee`
--
ALTER TABLE `employee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT для таблицы `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `FK_5D9F75A1AE80F5DF` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`);

--
-- Ограничения внешнего ключа таблицы `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `FK_8D93D6498C03F15C` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

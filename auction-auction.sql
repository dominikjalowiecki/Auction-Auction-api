-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Czas generowania: 22 Lis 2022, 13:18
-- Wersja serwera: 10.4.24-MariaDB
-- Wersja PHP: 7.4.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Baza danych: `auction-auction`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bid`
--

CREATE TABLE `bid` (
  `id_bid` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_bidder` int(11) NOT NULL,
  `bid_price` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `category`
--

CREATE TABLE `category` (
  `id_category` int(11) NOT NULL,
  `name` varchar(30) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `country`
--

CREATE TABLE `country` (
  `id_country` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `discussion`
--

CREATE TABLE `discussion` (
  `id_discussion` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_user` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `favourite`
--

CREATE TABLE `favourite` (
  `id_favourite` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_item` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `image`
--

CREATE TABLE `image` (
  `id_image` int(11) NOT NULL,
  `image_url` varchar(300) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `item`
--

CREATE TABLE `item` (
  `id_item` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_bin NOT NULL,
  `id_creator` int(11) NOT NULL,
  `id_category` int(11) NOT NULL,
  `starting_price` decimal(10,2) NOT NULL,
  `starting_time` datetime NOT NULL,
  `id_winner` int(11) DEFAULT NULL,
  `ending_price` decimal(10,2) DEFAULT NULL,
  `ending_time` datetime NOT NULL,
  `is_closed` tinyint(1) NOT NULL,
  `is_accepted` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `item_image`
--

CREATE TABLE `item_image` (
  `id_item_image` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_image` int(11) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `login_attempt`
--

CREATE TABLE `login_attempt` (
  `id_login_attempt` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `is_successful` tinyint(1) NOT NULL,
  `ip_address` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `user_agent` varchar(250) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `message`
--

CREATE TABLE `message` (
  `id_message` int(11) NOT NULL,
  `id_discussion` int(11) NOT NULL,
  `id_sender` int(11) NOT NULL,
  `content` varchar(300) NOT NULL,
  `created_at` datetime NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `message_image`
--

CREATE TABLE `message_image` (
  `id_message_image` int(11) NOT NULL,
  `id_image` int(11) NOT NULL,
  `id_message` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `notification`
--

CREATE TABLE `notification` (
  `id_notification` int(11) NOT NULL,
  `id_recipient` int(11) NOT NULL,
  `title_html` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `body_html` varchar(150) COLLATE utf8mb4_bin NOT NULL,
  `href` varchar(150) COLLATE utf8mb4_bin DEFAULT NULL,
  `id_item` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `province`
--

CREATE TABLE `province` (
  `id_province` int(11) NOT NULL,
  `name` varchar(30) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `role`
--

CREATE TABLE `role` (
  `id_role` int(11) NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `task`
--

CREATE TABLE `task` (
  `id_task` int(11) NOT NULL,
  `task_type` enum('AUCTIONS_REPORT') NOT NULL,
  `id_user` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `is_processed` tinyint(1) NOT NULL DEFAULT 0,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `username` varchar(30) COLLATE utf8mb4_bin NOT NULL,
  `first_name` varchar(30) COLLATE utf8mb4_bin NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `email` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `phone` char(12) COLLATE utf8mb4_bin DEFAULT NULL,
  `birth_date` date NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `avatar` varchar(300) COLLATE utf8mb4_bin NOT NULL,
  `id_country` int(11) DEFAULT NULL,
  `id_province` int(11) DEFAULT NULL,
  `postcode` char(6) COLLATE utf8mb4_bin DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
  `street` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
  `id_role` int(11) NOT NULL DEFAULT 1,
  `last_online` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `pswc` int(11) NOT NULL DEFAULT 0,
  `reset_password_request` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `bid`
--
ALTER TABLE `bid`
  ADD PRIMARY KEY (`id_bid`),
  ADD KEY `bid_ibfk_1` (`id_bidder`),
  ADD KEY `bid_ibfk_2` (`id_item`);

--
-- Indeksy dla tabeli `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id_category`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indeksy dla tabeli `country`
--
ALTER TABLE `country`
  ADD PRIMARY KEY (`id_country`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indeksy dla tabeli `discussion`
--
ALTER TABLE `discussion`
  ADD PRIMARY KEY (`id_discussion`),
  ADD KEY `discussion_ibfk_1` (`id_item`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeksy dla tabeli `favourite`
--
ALTER TABLE `favourite`
  ADD PRIMARY KEY (`id_favourite`),
  ADD KEY `favourite_ibfk_1` (`id_item`),
  ADD KEY `favourite_ibfk_2` (`id_user`);

--
-- Indeksy dla tabeli `image`
--
ALTER TABLE `image`
  ADD PRIMARY KEY (`id_image`),
  ADD UNIQUE KEY `image_url` (`image_url`);

--
-- Indeksy dla tabeli `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `item_ibfk_1` (`id_category`),
  ADD KEY `item_ibfk_2` (`id_creator`),
  ADD KEY `starting_time` (`starting_time`);
ALTER TABLE `item` ADD FULLTEXT KEY `name` (`name`);

--
-- Indeksy dla tabeli `item_image`
--
ALTER TABLE `item_image`
  ADD PRIMARY KEY (`id_item_image`),
  ADD KEY `item_image_ibfk_1` (`id_item`),
  ADD KEY `item_image_ibfk_2` (`id_image`);

--
-- Indeksy dla tabeli `login_attempt`
--
ALTER TABLE `login_attempt`
  ADD PRIMARY KEY (`id_login_attempt`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeksy dla tabeli `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id_message`),
  ADD KEY `id_discussion` (`id_discussion`),
  ADD KEY `id_sender` (`id_sender`);

--
-- Indeksy dla tabeli `message_image`
--
ALTER TABLE `message_image`
  ADD PRIMARY KEY (`id_message_image`),
  ADD KEY `id_message` (`id_message`),
  ADD KEY `id_image` (`id_image`);

--
-- Indeksy dla tabeli `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id_notification`),
  ADD KEY `id_recipient` (`id_recipient`);

--
-- Indeksy dla tabeli `province`
--
ALTER TABLE `province`
  ADD PRIMARY KEY (`id_province`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indeksy dla tabeli `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id_role`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indeksy dla tabeli `task`
--
ALTER TABLE `task`
  ADD PRIMARY KEY (`id_task`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeksy dla tabeli `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_ibfk_1` (`id_country`),
  ADD KEY `user_ibfk_2` (`id_province`),
  ADD KEY `id_role` (`id_role`);

--
-- AUTO_INCREMENT dla zrzuconych tabel
--

--
-- AUTO_INCREMENT dla tabeli `bid`
--
ALTER TABLE `bid`
  MODIFY `id_bid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `category`
--
ALTER TABLE `category`
  MODIFY `id_category` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `country`
--
ALTER TABLE `country`
  MODIFY `id_country` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `discussion`
--
ALTER TABLE `discussion`
  MODIFY `id_discussion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `favourite`
--
ALTER TABLE `favourite`
  MODIFY `id_favourite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `image`
--
ALTER TABLE `image`
  MODIFY `id_image` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `item`
--
ALTER TABLE `item`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `item_image`
--
ALTER TABLE `item_image`
  MODIFY `id_item_image` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `login_attempt`
--
ALTER TABLE `login_attempt`
  MODIFY `id_login_attempt` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `message`
--
ALTER TABLE `message`
  MODIFY `id_message` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `message_image`
--
ALTER TABLE `message_image`
  MODIFY `id_message_image` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `notification`
--
ALTER TABLE `notification`
  MODIFY `id_notification` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `province`
--
ALTER TABLE `province`
  MODIFY `id_province` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `role`
--
ALTER TABLE `role`
  MODIFY `id_role` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `task`
--
ALTER TABLE `task`
  MODIFY `id_task` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ograniczenia dla zrzutów tabel
--

--
-- Ograniczenia dla tabeli `bid`
--
ALTER TABLE `bid`
  ADD CONSTRAINT `bid_ibfk_1` FOREIGN KEY (`id_bidder`) REFERENCES `user` (`id_user`),
  ADD CONSTRAINT `bid_ibfk_2` FOREIGN KEY (`id_item`) REFERENCES `item` (`id_item`);

--
-- Ograniczenia dla tabeli `discussion`
--
ALTER TABLE `discussion`
  ADD CONSTRAINT `discussion_ibfk_1` FOREIGN KEY (`id_item`) REFERENCES `item` (`id_item`),
  ADD CONSTRAINT `discussion_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `favourite`
--
ALTER TABLE `favourite`
  ADD CONSTRAINT `favourite_ibfk_1` FOREIGN KEY (`id_item`) REFERENCES `item` (`id_item`),
  ADD CONSTRAINT `favourite_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `item_ibfk_1` FOREIGN KEY (`id_category`) REFERENCES `category` (`id_category`),
  ADD CONSTRAINT `item_ibfk_2` FOREIGN KEY (`id_creator`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `item_image`
--
ALTER TABLE `item_image`
  ADD CONSTRAINT `item_image_ibfk_1` FOREIGN KEY (`id_item`) REFERENCES `item` (`id_item`),
  ADD CONSTRAINT `item_image_ibfk_2` FOREIGN KEY (`id_image`) REFERENCES `image` (`id_image`);

--
-- Ograniczenia dla tabeli `login_attempt`
--
ALTER TABLE `login_attempt`
  ADD CONSTRAINT `login_attempt_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`id_discussion`) REFERENCES `discussion` (`id_discussion`),
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`id_sender`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `message_image`
--
ALTER TABLE `message_image`
  ADD CONSTRAINT `message_image_ibfk_1` FOREIGN KEY (`id_message`) REFERENCES `message` (`id_message`),
  ADD CONSTRAINT `message_image_ibfk_2` FOREIGN KEY (`id_image`) REFERENCES `image` (`id_image`);

--
-- Ograniczenia dla tabeli `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`id_recipient`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `task`
--
ALTER TABLE `task`
  ADD CONSTRAINT `task_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ograniczenia dla tabeli `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`id_country`) REFERENCES `country` (`id_country`),
  ADD CONSTRAINT `user_ibfk_2` FOREIGN KEY (`id_province`) REFERENCES `province` (`id_province`),
  ADD CONSTRAINT `user_ibfk_3` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

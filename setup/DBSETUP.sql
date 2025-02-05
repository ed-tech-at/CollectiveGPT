-- Adminer 4.8.1 MySQL 10.11.6-MariaDB-0+deb12u1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `answers`;
CREATE TABLE `answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `for_prompt_id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(255) NOT NULL DEFAULT '',
  `answer` varchar(255) NOT NULL DEFAULT '',
  `submit_time` int(11) NOT NULL DEFAULT 0,
  `session_id` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`answer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `send_prompts`;
CREATE TABLE `send_prompts` (
  `prompt_id` int(11) NOT NULL AUTO_INCREMENT,
  `prompt` text NOT NULL DEFAULT '',
  `send_time` int(11) NOT NULL DEFAULT 0,
  `chatgpt_json` text NOT NULL DEFAULT '',
  `selected_word` varchar(255) NOT NULL DEFAULT '',
  `f_template_id` int(11) NOT NULL DEFAULT 0,
  `f_workshop_id` int(1) NOT NULL DEFAULT 0,
  `is_del` int(1) NOT NULL DEFAULT 0,
  `html_code` text NOT NULL DEFAULT '',
  PRIMARY KEY (`prompt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `template_prompt`;
CREATE TABLE `template_prompt` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `prompt` text NOT NULL DEFAULT '',
  `chatgpt_json` text NOT NULL DEFAULT '',
  `favorite` int(11) NOT NULL DEFAULT 0,
  `last_time_used` int(11) NOT NULL DEFAULT 0,
  `is_del` int(1) NOT NULL DEFAULT 0,
  `html_code` text NOT NULL DEFAULT '',
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `usernames`;
CREATE TABLE `usernames` (
  `usernames_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(32) NOT NULL DEFAULT '',
  `username` varchar(255) NOT NULL DEFAULT '',
  `created` int(11) NOT NULL DEFAULT 0,
  `f_workshop_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usernames_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `workshops`;
CREATE TABLE `workshops` (
  `workshops_id` int(11) NOT NULL AUTO_INCREMENT,
  `start_time` int(11) NOT NULL DEFAULT 0,
  `workshop_name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`workshops_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


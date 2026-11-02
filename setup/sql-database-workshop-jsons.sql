-- CollectiveGPT: Tabellenstruktur mit Workshop-Templates, abgeleiteten Workshops und KI-Vorgaben
--
-- Für eine NEUE, leere Datenbank (MariaDB >= 10.5). Legt nur die Struktur an, keine Inhalte;
-- bestehende Inhalte kommen über ein eigenes Einfüge-Script.
--
-- Anlegen z. B. so (Name frei wählbar, danach in php/pws.php als $db_dbname eintragen):
--   CREATE DATABASE ed_tech_workshops CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
--   mariadb ed_tech_workshops < setup/sql-database-workshop-jsons.sql
--
-- Eine Datenbank für Deutsch und Englisch: die Sprache hängt am Workshop-Template bzw. Workshop
-- (Spalte lang). $db_dbname_en wird nicht mehr verwendet.
--
-- Modell:
--   workshop_templates  wiederverwendbare Vorlage (wie bisher die Template-Liste, jetzt benannt und mehrfach möglich)
--   workshops           konkreter Workshop, abgeleitet von einem Template oder aus einer hochgeladenen JSON-Datei
--   template_prompt     ein Prompt; gehört ENTWEDER zu einem Template (f_workshop_template_id)
--                       ODER zu einem Workshop (f_workshops_id). Beim Ableiten werden die Zeilen kopiert,
--                       der Workshop läuft danach nur noch mit seinen eigenen Prompts.
--   send_prompts        tatsächlich gesendete Prompts während eines Workshops
--   answers / usernames Antworten und Namen der Teilnehmenden
--
-- KI-Vorgaben (template_prompt.ki_vorgaben): JSON-Liste, z. B.
--   [{"antwort":"Mittwoch","gewicht":0.8},{"antwort":"Donnerstag","gewicht":0.2}]
-- Ist sie gesetzt, zeigt die Admin-Ansicht diese simulierten KI-Antworten statt eines echten GPT-Aufrufs.
-- Leer ('') heißt: echte GPT-Antwort (aus chatgpt_json bzw. live geholt).

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';


CREATE TABLE `workshop_templates` (
  `workshop_template_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `lang` varchar(5) NOT NULL DEFAULT 'de',
  `description` text NOT NULL DEFAULT '',
  `created` int(11) NOT NULL DEFAULT 0,
  `updated` int(11) NOT NULL DEFAULT 0,
  `is_del` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`workshop_template_id`),
  KEY `idx_is_del_name` (`is_del`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `workshops` (
  `workshops_id` int(11) NOT NULL AUTO_INCREMENT,
  `f_workshop_template_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Template, von dem abgeleitet wurde (0 = aus JSON ohne Template)',
  `workshop_name` varchar(255) NOT NULL DEFAULT '',
  `lang` varchar(5) NOT NULL DEFAULT 'de',
  `start_time` int(11) NOT NULL DEFAULT 0,
  `created` int(11) NOT NULL DEFAULT 0,
  `json_uploaded` int(11) NOT NULL DEFAULT 0 COMMENT 'Zeitpunkt des letzten JSON-Uploads (0 = nie)',
  PRIMARY KEY (`workshops_id`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_f_workshop_template_id` (`f_workshop_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `template_prompt` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `f_workshop_template_id` int(11) NOT NULL DEFAULT 0 COMMENT 'gehört zu diesem Template (sonst 0)',
  `f_workshops_id` int(11) NOT NULL DEFAULT 0 COMMENT 'gehört zu diesem Workshop (sonst 0)',
  `f_source_template_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Template-Prompt, von dem kopiert wurde',
  `prompt` text NOT NULL DEFAULT '',
  `chatgpt_json` mediumtext NOT NULL DEFAULT '',
  `ki_vorgaben` text NOT NULL DEFAULT '' COMMENT 'JSON [{antwort, gewicht}] oder leer',
  `favorite` int(11) NOT NULL DEFAULT 0 COMMENT 'Reihenfolge; 0 = in der Live-Liste ausgeblendet',
  `last_time_used` int(11) NOT NULL DEFAULT 0,
  `is_del` tinyint(1) NOT NULL DEFAULT 0,
  `html_code` mediumtext NOT NULL DEFAULT '',
  PRIMARY KEY (`template_id`),
  KEY `idx_template` (`f_workshop_template_id`, `is_del`, `favorite`),
  KEY `idx_workshop` (`f_workshops_id`, `is_del`, `favorite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `send_prompts` (
  `prompt_id` int(11) NOT NULL AUTO_INCREMENT,
  `prompt` text NOT NULL DEFAULT '',
  `send_time` int(11) NOT NULL DEFAULT 0,
  `chatgpt_json` mediumtext NOT NULL DEFAULT '',
  `selected_word` varchar(255) NOT NULL DEFAULT '',
  `f_template_id` int(11) NOT NULL DEFAULT 0,
  `f_workshop_id` int(11) NOT NULL DEFAULT 0,
  `is_del` tinyint(1) NOT NULL DEFAULT 0,
  `html_code` mediumtext NOT NULL DEFAULT '',
  PRIMARY KEY (`prompt_id`),
  KEY `idx_f_workshop_id` (`f_workshop_id`),
  KEY `idx_send_time` (`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `for_prompt_id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(255) NOT NULL DEFAULT '',
  `answer` varchar(255) NOT NULL DEFAULT '',
  `submit_time` int(11) NOT NULL DEFAULT 0,
  `session_id` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`answer_id`),
  KEY `idx_for_prompt_id` (`for_prompt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `usernames` (
  `usernames_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(32) NOT NULL DEFAULT '',
  `username` varchar(255) NOT NULL DEFAULT '',
  `created` int(11) NOT NULL DEFAULT 0,
  `f_workshop_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usernames_id`),
  KEY `idx_f_workshop_id` (`f_workshop_id`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


SET foreign_key_checks = 1;

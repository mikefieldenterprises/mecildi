-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 07, 2026 at 10:40 PM
-- Server version: 8.0.45
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `local_mecildi`
--

-- --------------------------------------------------------

--
-- Table structure for table `CACHE_ROBOTS`
--

CREATE TABLE `CACHE_ROBOTS` (
  `DOMAIN` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `IS_ALLOWED` tinyint(1) NOT NULL,
  `LAST_CHECKED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CRAWL_DELAY` int DEFAULT NULL,
  `DOMAIN_PREFIX` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `CODE_LANG`
--

CREATE TABLE `CODE_LANG` (
  `CODE_LANG_ID` int NOT NULL,
  `CODE_LANG_NAME` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CODE_LANG_ISO3` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CODE_LANG_GOOT` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `CODE_LANG`
--

INSERT INTO `CODE_LANG` (`CODE_LANG_ID`, `CODE_LANG_NAME`, `CODE_LANG_ISO3`, `CODE_LANG_GOOT`) VALUES
(1, 'Afar', 'aar', 'aa'),
(2, 'Abkhazian', 'abk', 'ab'),
(3, 'Afrikaans', 'afr', 'af'),
(4, 'Akan', 'aka', 'ak'),
(5, 'Amharic', 'amh', 'am'),
(6, 'Ido', 'aqg', 'io'),
(7, 'Arabic', 'ara', 'ar'),
(8, 'Aragonese', 'arg', 'an'),
(9, 'Egyptian', 'arz', 'egy'),
(10, 'Assamese', 'asm', 'as'),
(11, 'Asturian', 'ast', NULL),
(12, 'Avaric', 'ava', 'av'),
(13, 'Aymara', 'aym', 'ay'),
(14, 'Azerbaijani', 'aze', 'az'),
(15, 'Bashkir', 'bak', 'ba'),
(16, 'Bavarian', 'bar', NULL),
(17, 'Belarusian', 'bel', 'be'),
(18, 'Bengali', 'ben', 'bn'),
(19, 'Bikol', 'bik', NULL),
(20, 'Bislama', 'bis', 'bi'),
(21, 'Tibetan', 'bod', 'bo'),
(22, 'Bosnian', 'bos', 'bs'),
(23, 'Bishnupriya', 'bpy', NULL),
(24, 'Breton', 'bre', 'br'),
(25, 'Buriat', 'bua', NULL),
(26, 'Buginese', 'bug', NULL),
(27, 'Bulgarian', 'bul', 'bg'),
(28, 'Catalan', 'cat', 'ca'),
(29, 'Chavacano', 'cbk', NULL),
(30, 'Cebuano', 'ceb', NULL),
(31, 'Czech', 'ces', 'cs'),
(32, 'Chechen', 'che', 'ce'),
(33, 'Cherokee', 'chr', NULL),
(34, 'Chuvash', 'chv', 'cv'),
(35, 'Cornish', 'cor', 'kw'),
(36, 'Corsican', 'cos', 'co'),
(37, 'Seselwa Creole French', 'crs', NULL),
(38, 'Welsh', 'cym', 'cy'),
(39, 'Danish', 'dan', 'da'),
(40, 'German', 'deu', 'de'),
(41, 'Dhivehi', 'div', 'dv'),
(42, 'Lower Sorbian', 'dsb', NULL),
(43, 'Dzongkha', 'dzo', 'dz'),
(44, 'Emilian', 'egl', NULL),
(45, 'Modern Greek', 'ell', 'el'),
(46, 'English', 'eng', 'en'),
(47, 'Esperanto', 'epo', 'eo'),
(48, 'Estonian', 'est', 'et'),
(49, 'Basque', 'eus', 'eu'),
(50, 'Faroese', 'fao', 'fo'),
(51, 'Persian', 'fas', 'fa'),
(52, 'Fijian', 'fij', 'fj'),
(53, 'Finnish', 'fin', 'fi'),
(54, 'French', 'fra', 'fr'),
(55, 'Northern Frisian', 'frr', NULL),
(56, 'Western Frisian', 'fry', 'fy'),
(57, 'Scottish Gaelic', 'gla', 'gd'),
(58, 'Irish', 'gle', 'ga'),
(59, 'Galician', 'glg', 'gl'),
(60, 'Manx', 'glv', 'gv'),
(61, 'Guarani', 'grn', 'gn'),
(62, 'Gujarati', 'guj', 'gu'),
(63, 'Haitian', 'hat', 'ht'),
(64, 'Hausa', 'hau', 'ha'),
(65, 'Hawaiian', 'haw', NULL),
(66, 'Serbo Croatian', 'hbs', 'sh'),
(67, 'Hebrew', 'heb', 'he'),
(68, 'Fiji Hindi', 'hif', 'hif'),
(69, 'Hindi', 'hin', 'hi'),
(70, 'Hmong', 'hmn', NULL),
(71, 'Mari', 'hob', NULL),
(72, 'Croatian', 'hrv', 'hr'),
(73, 'Upper Sorbian', 'hsb', NULL),
(74, 'Hungarian', 'hun', 'hu'),
(75, 'Armenian', 'hye', 'hy'),
(76, 'Igbo', 'ibo', 'ig'),
(77, 'Inuktitut', 'iku', 'iu'),
(78, 'Interlingua', 'ila', 'ia'),
(79, 'Interlingue', 'ile', 'ie'),
(80, 'Iloko', 'ilo', NULL),
(81, 'Indonesian', 'ind', 'id'),
(82, 'Inupiaq', 'ipk', 'ik'),
(83, 'Icelandic', 'isl', 'is'),
(84, 'Italian', 'ita', 'it'),
(85, 'Javanese', 'jav', 'jv'),
(86, 'Japanese', 'jpn', 'ja'),
(87, 'Kalaallisut', 'kal', 'kl'),
(88, 'Kannada', 'kan', 'kn'),
(89, 'Kashmiri', 'kas', 'ks'),
(90, 'Georgian', 'kat', 'ka'),
(91, 'Kazakh', 'kaz', 'kk'),
(92, 'Khasi', 'kha', NULL),
(93, 'Khmer', 'khm', 'km'),
(94, 'Kinyarwanda', 'kin', 'rw'),
(95, 'Konkani', 'kok', NULL),
(96, 'Komi', 'kom', 'kv'),
(97, 'Korean', 'kor', 'ko'),
(98, 'Karachay Balkar', 'krc', NULL),
(99, 'Kurdish', 'kur', 'ku'),
(100, 'Kirghiz', 'kyr', 'ky'),
(101, 'Lahnda', 'lah', NULL),
(102, 'Lao', 'lao', 'lo'),
(103, 'Latin', 'lat', 'la'),
(104, 'Latvian', 'lav', 'lv'),
(105, 'Lezghian', 'lez', NULL),
(106, 'Limbu', 'lif', NULL),
(107, 'Limburgan', 'lim', 'li'),
(108, 'Lingala', 'lin', 'ln'),
(109, 'Lithuanian', 'lit', 'lt'),
(110, 'Lombard', 'lmo', NULL),
(111, 'Northern Luri', 'lrc', NULL),
(112, 'Luxembourgish', 'ltz', 'lb'),
(113, 'Ganda', 'lug', 'lg'),
(114, 'Maithili', 'mai', NULL),
(115, 'Malayalam', 'mal', 'ml'),
(116, 'Marathi', 'mar', 'mr'),
(117, 'Morisyen', 'mfe', NULL),
(118, 'Macedonian', 'mkd', 'mk'),
(119, 'Malagasy', 'mlg', 'mg'),
(120, 'Maltese', 'mlt', 'mt'),
(121, 'Mongolian', 'mon', 'mn'),
(122, 'Maori', 'mri', 'mi'),
(123, 'Malay', 'msa', 'ms'),
(124, 'Mirandese', 'mwl', NULL),
(125, 'Burmese', 'mya', 'my'),
(126, 'Erzya', 'myv', NULL),
(127, 'Mazanderani', 'mzn', NULL),
(128, 'Neapolitan', 'nap', NULL),
(129, 'Nauru', 'nau', 'na'),
(130, 'South Ndebele', 'nbl', 'nr'),
(131, 'Low German', 'nds', NULL),
(132, 'Nepal Bhasa', 'new', NULL),
(133, 'Eastern Huasteca Nahuatl', 'nhe', NULL),
(134, 'Dutch', 'nld', 'nl'),
(135, 'Norwegian', 'nor', 'no'),
(136, 'Nepali', 'npi', 'ne'),
(137, 'Pedi', 'nso', NULL),
(138, 'Nyanja', 'nya', 'ny'),
(139, 'Occitan', 'oci', 'oc'),
(140, 'Oriya', 'ori', 'or'),
(141, 'Oromo', 'orm', 'om'),
(142, 'Ossetian', 'oss', 'os'),
(143, 'Pampanga', 'pam', NULL),
(144, 'Panjabi', 'pan', 'pa'),
(145, 'Pfaelzisch', 'pfl', NULL),
(146, 'Piemontese', 'pms', NULL),
(147, 'Polish', 'pol', 'pl'),
(148, 'Portuguese', 'por', 'pt'),
(149, 'Pushto', 'pus', 'ps'),
(150, 'Quechua', 'que', 'qu'),
(151, 'Romansh', 'roh', 'rm'),
(152, 'Romanian', 'ron', 'ro'),
(153, 'Rusyn', 'rue', NULL),
(154, 'Rundi', 'run', 'rn'),
(155, 'Russian', 'rus', 'ru'),
(156, 'Sango', 'sag', 'sg'),
(157, 'Yakut', 'sah', NULL),
(158, 'Sanskrit', 'san', 'sa'),
(159, 'Sicilian', 'scn', NULL),
(160, 'Scots', 'sco', NULL),
(161, 'Sinhala', 'sin', 'si'),
(162, 'Slovak', 'slk', 'sk'),
(163, 'Slovenian', 'slv', 'sl'),
(164, 'Samoan', 'smo', 'sm'),
(165, 'Shona', 'sna', 'sn'),
(166, 'Sindhi', 'snd', 'sd'),
(167, 'Somali', 'som', 'so'),
(168, 'Southern Sotho', 'sot', 'st'),
(169, 'Spanish', 'spa', 'es'),
(170, 'Albanian', 'sqi', 'sq'),
(171, 'Sardinian', 'srd', 'sc'),
(172, 'Serbian', 'srp', 'sr'),
(173, 'Swati', 'ssw', 'ss'),
(174, 'Sundanese', 'sun', 'su'),
(175, 'Swedish', 'swe', 'sv'),
(176, 'Swahili', 'swh', 'sw'),
(177, 'Syriac', 'syc', NULL),
(178, 'Tamil', 'tam', 'ta'),
(179, 'Tatar', 'tat', 'tt'),
(180, 'Telugu', 'tel', 'te'),
(181, 'Tajik', 'tgk', 'tg'),
(182, 'Tagalog', 'tgl', 'tl'),
(183, 'Thai', 'tha', 'th'),
(184, 'Tigrinya', 'tir', 'ti'),
(185, 'Klingon', 'tlh', NULL),
(186, 'Tonga', 'tog', 'to'),
(187, 'Tswana', 'tsn', 'tn'),
(188, 'Tsonga', 'tso', 'ts'),
(189, 'Turkmen', 'tuk', 'tk'),
(190, 'Turkish', 'tur', 'tr'),
(191, 'Tuvinian', 'tyv', NULL),
(192, 'Uighur', 'uig', 'ug'),
(193, 'Ukrainian', 'ukr', 'uk'),
(194, 'Urdu', 'urd', 'ur'),
(195, 'Uzbek', 'uzb', 'uz'),
(196, 'Venetian', 'vec', NULL),
(197, 'Venda', 'ven', 've'),
(198, 'Veps', 'vep', NULL),
(199, 'Vietnamese', 'vie', 'vi'),
(200, 'Vlaams', 'vls', NULL),
(201, 'Volapük', 'vol', 'vo'),
(202, 'Waray', 'war', NULL),
(203, 'Walloon', 'wln', 'wa'),
(204, 'Wolof', 'wol', 'wo'),
(205, 'Kalmyk', 'xal', NULL),
(206, 'Xhosa', 'xho', 'xh'),
(207, 'Mingrelian', 'xmf', NULL),
(208, 'Yiddish', 'yid', 'yi'),
(209, 'Yoruba', 'yor', 'yo'),
(210, 'Zhuang', 'zha', 'za'),
(211, 'Chinese', 'zho', 'zh'),
(212, 'Zulu', 'zul', 'zu'),
(213, 'Zaza', 'zza', NULL),
(214, 'Gothic', NULL, 'got'),
(215, 'Lojban', NULL, 'jbo'),
(216, 'Norwegian Nynorsk', NULL, 'nn');

-- --------------------------------------------------------

--
-- Table structure for table `DATA_PROGRESS`
--

CREATE TABLE `DATA_PROGRESS` (
  `PROGRESS_ID` int NOT NULL,
  `FILENAME` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TOTAL_LINES` int NOT NULL,
  `LAST_LINE_PROCESSED` int NOT NULL,
  `MODIFIED_DATE` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



--
-- Table structure for table `LOG_LANG_DETECTOR`
--

CREATE TABLE `LOG_LANG_DETECTOR` (
  `ID` int NOT NULL,
  `SERVICE_NAME` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `AUTH_KEY_OWNER` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NUM_CHARS_SENT` int DEFAULT NULL,
  `DATE_SENT` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Indexes for dumped tables
--

--
-- Indexes for table `CACHE_ROBOTS`
--
ALTER TABLE `CACHE_ROBOTS`
  ADD PRIMARY KEY (`DOMAIN`);

--
-- Indexes for table `CODE_LANG`
--
ALTER TABLE `CODE_LANG`
  ADD PRIMARY KEY (`CODE_LANG_ID`);

--
-- Indexes for table `DATA_PROGRESS`
--
ALTER TABLE `DATA_PROGRESS`
  ADD PRIMARY KEY (`PROGRESS_ID`);

--
-- Indexes for table `LOG_LANG_DETECTOR`
--
ALTER TABLE `LOG_LANG_DETECTOR`
  ADD PRIMARY KEY (`ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `CODE_LANG`
--
ALTER TABLE `CODE_LANG`
  MODIFY `CODE_LANG_ID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT for table `DATA_PROGRESS`
--
ALTER TABLE `DATA_PROGRESS`
  MODIFY `PROGRESS_ID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `LOG_LANG_DETECTOR`
--
ALTER TABLE `LOG_LANG_DETECTOR`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=373582;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

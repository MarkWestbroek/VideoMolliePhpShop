-- ============================================================
-- Testdata — HB Foto & Video
-- Voegt een test-staffel, test-event en 4 testvideo's in.
-- Veilig om meerdere keren uit te voeren (INSERT IGNORE).
-- Uitvoeren via: Plesk > Databases > phpMyAdmin > SQL-tabblad
-- ============================================================

-- ------------------------------------------------------------
-- Test-staffel: 3 prijstrappen
-- ------------------------------------------------------------
INSERT IGNORE INTO `staffels` (`id`, `naam`, `beschrijving`) VALUES
(1, 'Testkorting serie', '1e video €12,50 · 2e en 3e €9,95 · 4e en verder €7,50');

INSERT IGNORE INTO `staffelprijzen` (`staffel_id`, `aantal_van`, `aantal_tot`, `prijs`) VALUES
(1, 1, 1,   12.50),
(1, 2, 3,    9.95),
(1, 4, 999,  7.50);

-- ------------------------------------------------------------
-- Test-event (toegangscode: TEST2026)
-- ------------------------------------------------------------
INSERT IGNORE INTO `events` (`id`, `naam`, `organisator`, `beschrijving`, `toegangscode`, `active`) VALUES
(1, 'Testworkshop juni 2026', 'HB Foto & Video', 'Besloten video alleen voor deelnemers.', 'TEST2026', 1);

-- ------------------------------------------------------------
-- 4 testvideo's
--   1 - Gratis         (price=0,   geen staffel, geen event)
--   2 - Vaste prijs    (price=9.95, geen staffel, geen event)
--   3 - Staffelkorting (price=12.50, staffel_id=1, geen event)
--   4 - Event-besloten (price=7.50, geen staffel, event_id=1)
--
-- Bestanden: Vid2.mp4, Vid3.mp4, Vid4.mp4 aanwezig in private/videos/
-- Vid4.mp4 wordt hergebruikt voor de event-video (zelfde bestand).
-- ------------------------------------------------------------
INSERT IGNORE INTO `videos` (`id`, `title`, `description`, `price`, `staffel_id`, `event_id`, `filename`, `active`) VALUES
(1,
 'Introductie — gratis kijken',
 'Een korte introductievideo die iedereen gratis kan bekijken. Geen betaling nodig.',
 0.00, NULL, NULL, 'Vid2.mp4', 1),

(2,
 'Techniek: scherpstellen & dieptescherpte',
 'Uitleg over manueel scherpstellen, diafragma en de invloed op dieptescherpte.',
 9.95, NULL, NULL, 'Vid3.mp4', 1),

(3,
 'Compositie & licht (serie)',
 'Deel 1 van de compositieserie. Hoe lager de prijs per video naarmate je er meer koopt.',
 12.50, 1, NULL, 'Vid4.mp4', 1),

(4,
 'Workshop nachtfotografie — besloten',
 'Exclusieve opname voor deelnemers van de testworkshop. Voer de toegangscode in om te bekijken.',
 7.50, NULL, 1, 'Vid2.mp4', 1);

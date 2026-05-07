-- ============================================================
-- Anonymisierung aller personenbezogenen Daten
-- Datum: 2026-05-07
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- EMPLOYEE: Vorname, Nachname, Geburtsdatum, Telefon
-- ============================================================
UPDATE employee SET first_name = 'Max',       last_name = 'Mustermann',   birth_date = '1990-03-15', phone = '01511000001' WHERE id = 1;
UPDATE employee SET first_name = 'Lukas',     last_name = 'Berger',       birth_date = '2001-06-22', phone = '01522000002' WHERE id = 21;
UPDATE employee SET first_name = 'Klaus',     last_name = 'Hofmann',      birth_date = '1976-09-10', phone = '01511000003' WHERE id = 22;
UPDATE employee SET first_name = 'Stefan',    last_name = 'Zimmermann',   birth_date = '1995-12-05', phone = '01522000004' WHERE id = 23;
UPDATE employee SET first_name = 'Thomas',    last_name = 'Fischer',      birth_date = '1986-04-18', phone = '01511000005' WHERE id = 24;
UPDATE employee SET first_name = 'Peter',     last_name = 'Wagner',       birth_date = '1987-08-30', phone = '01522000006' WHERE id = 25;
UPDATE employee SET first_name = 'Michael',   last_name = 'Richter',      birth_date = '1987-02-14', phone = '01511000007' WHERE id = 26;
UPDATE employee SET first_name = 'Andreas',   last_name = 'Bauer',        birth_date = '1966-11-27', phone = '01522000008' WHERE id = 27;
UPDATE employee SET first_name = 'Jürgen',    last_name = 'Koch',         birth_date = '1956-07-03', phone = '01511000009' WHERE id = 29;
UPDATE employee SET first_name = 'Dieter',    last_name = 'Schulz',       birth_date = '1954-01-19', phone = '01522000010' WHERE id = 30;
UPDATE employee SET first_name = 'Heinz',     last_name = 'Schmidt',      birth_date = '1971-05-25', phone = '01511000011' WHERE id = 31;
UPDATE employee SET first_name = 'Hans',      last_name = 'Weber',        birth_date = '1997-03-08', phone = '01522000012' WHERE id = 32;
UPDATE employee SET first_name = 'Karl',      last_name = 'Müller',       birth_date = '1990-10-01', phone = '01511000013' WHERE id = 33;
UPDATE employee SET first_name = 'Werner',    last_name = 'Braun',        birth_date = '1989-07-16', phone = '01522000014' WHERE id = 34;
UPDATE employee SET first_name = 'Frank',     last_name = 'Meyer',        birth_date = '2002-09-20', phone = '01511000015' WHERE id = 37;
UPDATE employee SET first_name = 'Otto',      last_name = 'Krause',       birth_date = '2000-11-11', phone = '01522000016' WHERE id = 38;
UPDATE employee SET first_name = 'Rolf',      last_name = 'Hartmann',     birth_date = '1988-06-29', phone = '01511000017' WHERE id = 39;
UPDATE employee SET first_name = 'Günter',    last_name = 'Klein',        birth_date = '2002-04-13', phone = '01522000018' WHERE id = 40;
UPDATE employee SET first_name = 'Bernd',     last_name = 'Wolf',         birth_date = '1993-12-02', phone = '01511000019' WHERE id = 41;
UPDATE employee SET first_name = 'Gerhard',   last_name = 'Schwarz',      birth_date = '1987-01-07', phone = '01522000020' WHERE id = 42;
UPDATE employee SET first_name = 'Rainer',    last_name = 'Schäfer',      birth_date = '1984-08-24', phone = '01511000021' WHERE id = 43;
UPDATE employee SET first_name = 'Dietmar',   last_name = 'Lehmann',      birth_date = '1973-05-31', phone = '01522000022' WHERE id = 44;
UPDATE employee SET first_name = 'Horst',     last_name = 'Neumann',      birth_date = '2001-02-17', phone = '01511000023' WHERE id = 45;
UPDATE employee SET first_name = 'Manfred',   last_name = 'Kaiser',       birth_date = '1993-09-28', phone = '01522000024' WHERE id = 46;

-- ============================================================
-- USER: Username und Email (Passwörter bleiben unverändert)
-- ============================================================
-- id=1 (ptyulnev) bleibt unverändert — eigener Admin-Account
-- UPDATE user SET username = 'mmustermann', email = 'm.mustermann@demo-sicherheit.de' WHERE id = 1;
UPDATE user SET username = 'lberger',     email = 'l.berger@demo-sicherheit.de'     WHERE id = 20;
UPDATE user SET username = 'khofmann',    email = 'k.hofmann@demo-sicherheit.de'    WHERE id = 21;
UPDATE user SET username = 'szimmermann', email = 's.zimmermann@demo-sicherheit.de' WHERE id = 22;
UPDATE user SET username = 'tfischer',    email = 't.fischer@demo-sicherheit.de'    WHERE id = 23;
UPDATE user SET username = 'pwagner',     email = 'p.wagner@demo-sicherheit.de'     WHERE id = 24;
UPDATE user SET username = 'mrichter',    email = 'm.richter@demo-sicherheit.de'    WHERE id = 25;
UPDATE user SET username = 'abauer',      email = 'a.bauer@demo-sicherheit.de'      WHERE id = 28;
UPDATE user SET username = 'jkoch',       email = 'j.koch@demo-sicherheit.de'       WHERE id = 30;
UPDATE user SET username = 'dschulz',     email = 'd.schulz@demo-sicherheit.de'     WHERE id = 31;
UPDATE user SET username = 'hschmidt',    email = 'h.schmidt@demo-sicherheit.de'    WHERE id = 32;
UPDATE user SET username = 'hweber',      email = 'h.weber@demo-sicherheit.de'      WHERE id = 33;
UPDATE user SET username = 'kmueller',    email = 'k.mueller@demo-sicherheit.de'    WHERE id = 34;
UPDATE user SET username = 'wbraun',      email = 'w.braun@demo-sicherheit.de'      WHERE id = 35;
UPDATE user SET username = 'fmeyer',      email = 'f.meyer@demo-sicherheit.de'      WHERE id = 38;
UPDATE user SET username = 'okrause',     email = 'o.krause@demo-sicherheit.de'     WHERE id = 39;
UPDATE user SET username = 'rhartmann',   email = 'r.hartmann@demo-partner.de'      WHERE id = 40;
UPDATE user SET username = 'gklein',      email = 'g.klein@demo-partner.de'         WHERE id = 41;
UPDATE user SET username = 'bwolf',       email = 'b.wolf@demo-partner.de'          WHERE id = 42;
UPDATE user SET username = 'gschwarz',    email = 'g.schwarz@demo-partner.de'       WHERE id = 43;
UPDATE user SET username = 'rschaefer',   email = 'r.schaefer@demo-partner.de'      WHERE id = 44;
UPDATE user SET username = 'dlehmann',    email = 'd.lehmann@demo-sicherheit.de'    WHERE id = 45;
UPDATE user SET username = 'hneumann',    email = 'h.neumann@demo-sicherheit.de'    WHERE id = 46;
UPDATE user SET username = 'mkaiser',     email = 'm.kaiser@demo-sicherheit.de'     WHERE id = 47;

-- ============================================================
-- CONTACT: Vorname, Nachname, Telefon, Email
-- ============================================================
UPDATE contact SET first_name = 'Sandra',      last_name = 'Meier',       phone = '0173-1234001', email = 's.meier@demo-kunde.de'          WHERE id = 5;
UPDATE contact SET first_name = 'Haustechnik', last_name = 'Demo-Center', phone = '0172-1234002', email = 'haustechnik@demo-center.de'     WHERE id = 6;
UPDATE contact SET first_name = 'Marktleitung',last_name = 'Demo-Center', phone = '0351-1234003', email = 'marktleitung@demo-center.de'    WHERE id = 7;
UPDATE contact SET first_name = 'Lukas',       last_name = 'Berger',      phone = '01522000002',  email = 'l.berger@demo-sicherheit.de'    WHERE id = 8;
UPDATE contact SET first_name = 'Klaus',       last_name = 'Hofmann',     phone = '01511000003',  email = 'k.hofmann@demo-sicherheit.de'   WHERE id = 10;

-- ============================================================
-- PARTNER_COMPANY: Firmenname, Ansprechpartner, Telefon, Email
-- ============================================================
UPDATE partner_company SET title = 'Alpha Sicherheitsdienste GmbH',    first_name = 'Gerhard', last_name = 'Schwarz',  phone = '01522000020', email = 'g.schwarz@demo-partner.de'  WHERE id = 1;
UPDATE partner_company SET title = 'Beta Dienstleistungen GmbH',        first_name = 'Bernd',   last_name = 'Wolf',     phone = '01511000019', email = 'b.wolf@demo-partner.de'      WHERE id = 2;
UPDATE partner_company SET title = 'Gamma Service & Beratung',          first_name = 'Rolf',    last_name = 'Hartmann', phone = '01511000017', email = 'r.hartmann@demo-partner.de'  WHERE id = 3;
UPDATE partner_company SET title = 'Delta Security GmbH',               first_name = 'Günter',  last_name = 'Klein',    phone = '01522000018', email = 'g.klein@demo-partner.de'     WHERE id = 4;
UPDATE partner_company SET title = 'Epsilon Security Service',          first_name = 'Rainer',  last_name = 'Schäfer',  phone = '01511000021', email = 'r.schaefer@demo-partner.de'  WHERE id = 5;
UPDATE partner_company SET title = 'Zeta Schutz und Sicherheit GmbH',  first_name = 'Frank',   last_name = 'Mayer',    phone = '01522000025', email = 'f.mayer@demo-partner.de'     WHERE id = 6;

-- ============================================================
-- COMPANY: Firmenname
-- ============================================================
UPDATE company SET company = 'Alpha Holding GmbH'                       WHERE id = 1;
UPDATE company SET company = 'Demo Veranstaltungen GmbH'                WHERE id = 2;
UPDATE company SET company = 'Demo Sicherheitsdienst GmbH'              WHERE id = 3;
UPDATE company SET company = 'Beta Sicherheitsservice GmbH'             WHERE id = 4;
UPDATE company SET company = 'Gamma Service GmbH'                       WHERE id = 5;
UPDATE company SET company = 'Delta GmbH & Co. KG'                     WHERE id = 6;
UPDATE company SET company = 'Epsilon Media AG'                         WHERE id = 7;
UPDATE company SET company = 'Demo Wach- & Sicherheitsdienst GmbH'     WHERE id = 8;
UPDATE company SET company = 'Zeta Sicherheit & Service GmbH & Co. KG' WHERE id = 9;
UPDATE company SET company = 'Eta Immobilien GmbH'                     WHERE id = 10;

-- ============================================================
-- FACILITY: Echte Kundennamen und Standorte anonymisieren
-- ============================================================
UPDATE facility SET title = 'Standort Zirkus',          short_title = 'ZRK'  WHERE id = 3;
UPDATE facility SET title = 'Baustelle Industrie Süd',  short_title = 'BIS'  WHERE id = 4;
UPDATE facility SET title = 'Demo-Center Mitte',        short_title = 'DCM'  WHERE id = 5;
UPDATE facility SET title = 'Brandwache Hochhaus',      short_title = 'BWH'  WHERE id = 7;
UPDATE facility SET title = 'Büro Zentrale',            short_title = 'BZL'  WHERE id = 9;
UPDATE facility SET title = 'Revierstreife Einkaufszentrum', short_title = 'REZ' WHERE id = 10;
UPDATE facility SET title = 'Klinik Standort A4',       short_title = 'KSA4' WHERE id = 11;
UPDATE facility SET title = 'Hotel Dresden',            short_title = 'HTD'  WHERE id = 12;
UPDATE facility SET title = 'Asylbewerberheim Nord',    short_title = 'ABN'  WHERE id = 13;
UPDATE facility SET title = 'Revierstreife Ost',        short_title = 'RSO'  WHERE id = 14;
UPDATE facility SET title = 'Kaufhaus Zentrum',         short_title = 'KZT'  WHERE id = 15;
UPDATE facility SET title = 'Krankenhaus Ost',          short_title = 'KHO'  WHERE id = 16;
UPDATE facility SET title = 'Medienanstalt Dresden',    short_title = 'MAD'  WHERE id = 17;
UPDATE facility SET title = 'Asylbewerberheim Süd',     short_title = 'ABS'  WHERE id = 18;
UPDATE facility SET title = 'Wohnanlage Mitte',         short_title = 'WAM'  WHERE id = 19;
UPDATE facility SET title = 'Klinik Standort A1',       short_title = 'KSA1' WHERE id = 20;
UPDATE facility SET title = 'Standort Heidenau',        short_title = 'STH'  WHERE id = 21;
UPDATE facility SET title = 'Handelszentrum Dresden',   short_title = 'HZD'  WHERE id = 22;
UPDATE facility SET title = 'Friedhof Projekt',         short_title = 'FPR'  WHERE id = 23;

-- ============================================================
-- ADDRESS: Straßennamen anonymisieren (PLZ und Stadt bleiben)
-- ============================================================
UPDATE address SET street = 'Musterstraße 1'          WHERE id = 1;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 4;
UPDATE address SET street = 'Parkstraße 90'           WHERE id = 5;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 6;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 7;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 8;
UPDATE address SET street = 'Gartenstraße 15a'        WHERE id = 9;
UPDATE address SET street = 'Bahnhofstraße 37'        WHERE id = 10;
UPDATE address SET street = 'Industrieweg 23'         WHERE id = 11;
UPDATE address SET street = 'Industrieweg 23'         WHERE id = 12;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 13;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 14;
UPDATE address SET street = 'Schulstraße 5'           WHERE id = 15;
UPDATE address SET street = 'Schulstraße 5'           WHERE id = 16;
UPDATE address SET street = 'Parkstraße 90'           WHERE id = 17;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 18;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 19;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 20;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 21;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 22;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 23;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 24;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 25;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 26;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 27;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 28;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 29;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 30;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 31;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 32;
UPDATE address SET street = 'Bergstraße 105'          WHERE id = 33;
UPDATE address SET street = 'Bergstraße 105'          WHERE id = 34;
UPDATE address SET street = 'Schulstraße 5'           WHERE id = 35;
UPDATE address SET street = 'Industriestraße 77'      WHERE id = 36;
UPDATE address SET street = 'Kirchstraße 2a'          WHERE id = 37;
UPDATE address SET street = 'Kirchstraße 2a'          WHERE id = 38;
UPDATE address SET street = 'Kirchstraße 2a'          WHERE id = 39;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 40;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 41;
UPDATE address SET street = 'Feldstraße 3'            WHERE id = 42;
UPDATE address SET street = 'Gartenstraße 5'          WHERE id = 44;
UPDATE address SET street = 'Waldstraße 20'           WHERE id = 46;
UPDATE address SET street = 'Waldstraße 20'           WHERE id = 47;
UPDATE address SET street = 'Industrieweg 23'         WHERE id = 48;
UPDATE address SET street = 'Dorfstraße 17'           WHERE id = 49;
UPDATE address SET street = 'Feldstraße 3'            WHERE id = 50;
UPDATE address SET street = 'Feldstraße 3'            WHERE id = 51;
UPDATE address SET street = 'Feldstraße 3'            WHERE id = 52;
UPDATE address SET street = 'Feldstraße 3'            WHERE id = 53;
UPDATE address SET street = 'Feldstraße 3'            WHERE id = 54;
UPDATE address SET street = 'Allee 40'                WHERE id = 55;
UPDATE address SET street = 'Ringstraße 96'           WHERE id = 56;
UPDATE address SET street = 'Ringstraße 96'           WHERE id = 57;
UPDATE address SET street = 'Ringstraße 96'           WHERE id = 58;
UPDATE address SET street = 'Ringstraße 96'           WHERE id = 59;
UPDATE address SET street = 'Bergstraße 25'           WHERE id = 60;
UPDATE address SET street = 'Bergstraße 25'           WHERE id = 61;
UPDATE address SET street = 'Bergstraße 25'           WHERE id = 62;
UPDATE address SET street = 'Marktstraße 2c'          WHERE id = 63;
UPDATE address SET street = 'Marktstraße 2c'          WHERE id = 64;
UPDATE address SET street = 'Musterstraße 1'          WHERE id = 65;
UPDATE address SET street = 'Gartenstraße 15a'        WHERE id = 66;
UPDATE address SET street = 'Gartenstraße 15a'        WHERE id = 67;
UPDATE address SET street = 'Schloßstraße 15'         WHERE id = 68;
UPDATE address SET street = 'Braustraße 7'            WHERE id = 69;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 70;
UPDATE address SET street = 'Hauptstraße 2'           WHERE id = 71;
UPDATE address SET street = 'Marktstraße 15'          WHERE id = 72;
UPDATE address SET street = 'Stadtstraße 3'           WHERE id = 73;
UPDATE address SET street = 'Lindenstraße 88'         WHERE id = 74;
UPDATE address SET street = 'Industriestraße 49'      WHERE id = 75;
UPDATE address SET street = 'Kirchstraße 11'          WHERE id = 76;
UPDATE address SET street = 'Waldstraße 20'           WHERE id = 77;
UPDATE address SET street = 'Kirchstraße 2a'          WHERE id = 78;
UPDATE address SET street = 'Landstraße 52a'          WHERE id = 79;
UPDATE address SET street = 'Landstraße 52a'          WHERE id = 80;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 81;
UPDATE address SET street = 'Landstraße 52a'          WHERE id = 82;
UPDATE address SET street = 'Klosterstraße 1'         WHERE id = 83;
UPDATE address SET street = 'Bahnhofstraße 8'         WHERE id = 84;
UPDATE address SET street = 'Gartenweg 35'            WHERE id = 85;
UPDATE address SET street = 'Gartenweg 35'            WHERE id = 86;
UPDATE address SET street = 'Gartenweg 35'            WHERE id = 87;
UPDATE address SET street = 'Platz der Einheit 3'     WHERE id = 88;
UPDATE address SET street = 'Industriestraße 72'      WHERE id = 89;
UPDATE address SET street = 'Landstraße 299'          WHERE id = 90;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 91;
UPDATE address SET street = 'Lindenstraße 184'        WHERE id = 92;

SET FOREIGN_KEY_CHECKS = 1;

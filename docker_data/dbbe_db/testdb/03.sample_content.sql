--
-- Synthetic sample content for local/dev browsing & testing.
-- All names, texts, titles and identifiers below are fictional and were
-- written by hand -- none of this was extracted from the real DBBE dataset.
--

SET search_path TO data;

-- ---------- Location chain: region -> institution -> library ----------

INSERT INTO region (name, is_city) VALUES ('Testland', false) RETURNING identity \gset region_country_
INSERT INTO region (name, is_city, parent_idregion) VALUES ('Testville', true, :region_country_identity) RETURNING identity \gset region_city_

INSERT INTO institution (idregion, name, name_abbreviated) VALUES (:region_city_identity, 'Test National Library', 'TNL') RETURNING identity \gset institution_
INSERT INTO library (identity) VALUES (:institution_identity);

-- the institution insert above auto-creates a matching row in "location"
-- (see trigger ensure_institution_has_location); fetch its id
SELECT idlocation FROM location WHERE idinstitution = :institution_identity \gset library_location_

-- ---------- Persons ----------

INSERT INTO person (is_historical, is_modern, is_dbbe) VALUES (true, false, false) RETURNING identity \gset person_author_
INSERT INTO name (idperson, first_name, last_name, is_primary) VALUES (:person_author_identity, 'Ioannes', 'Testopoulos', true);

INSERT INTO person (is_historical, is_modern, is_dbbe) VALUES (true, false, false) RETURNING identity \gset person_scribe_
INSERT INTO name (idperson, first_name, last_name, is_primary) VALUES (:person_scribe_identity, 'Nikolaos', 'Grapheus', true);

-- ---------- Manuscript ----------

INSERT INTO manuscript DEFAULT VALUES RETURNING identity \gset manuscript_

INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :manuscript_identity, idlanguage, 'Testville, Test National Library, Test MS 1'
    FROM language WHERE name = 'Unknown';

INSERT INTO located_at (iddocument, idlocation, identification)
    VALUES (:manuscript_identity, :library_location_idlocation, 'Test MS 1');

INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_scribe_identity, :manuscript_identity, idrole FROM role WHERE system_name = 'scribe';

INSERT INTO document_status (iddocument, idstatus)
    SELECT :manuscript_identity, idstatus FROM status WHERE status = 'consulted on-site' AND type = 'manuscript';

INSERT INTO entity_management (identity, idmanagement) VALUES (:manuscript_identity, 1);

-- ---------- Occurrence (original_poem), witnessed in the manuscript above ----------

INSERT INTO original_poem DEFAULT VALUES RETURNING identity \gset occurrence_

UPDATE poem SET incipit = 'Χαῖρε τέκνον, χαῖρε καλὸν φῶς', verses = 4 WHERE identity = :occurrence_identity;

INSERT INTO original_poem_verse (idoriginal_poem, verse, "order") VALUES
    (:occurrence_identity, 'Χαῖρε τέκνον, χαῖρε καλὸν φῶς ἐμοῖς ὀφθαλμοῖς', 1),
    (:occurrence_identity, 'ὃν ἡ φύσις ἐκόσμησε κάλλει καὶ χάριτι', 2),
    (:occurrence_identity, 'σοφίᾳ τε καὶ λόγῳ καὶ πάσῃ ἀρετῇ', 3),
    (:occurrence_identity, 'εὐλογημένος εἴης εἰς αἰῶνας ἀμήν', 4);

INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :occurrence_identity, idlanguage, 'Test epigram on a benefactor'
    FROM language WHERE name = 'Unknown';

INSERT INTO document_genre (iddocument, idgenre)
    SELECT :occurrence_identity, idgenre FROM genre WHERE genre = 'Author-related epigram';

INSERT INTO poem_meter (idpoem, idmeter)
    SELECT :occurrence_identity, idmeter FROM meter WHERE name = 'Dodecasyllable';

INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :occurrence_identity, idrole FROM role WHERE system_name = 'author';

INSERT INTO document_contains (idcontainer, idcontent, page_start, page_end)
    VALUES (:manuscript_identity, :occurrence_identity, '1r', '1v');

INSERT INTO document_status (iddocument, idstatus)
    SELECT :occurrence_identity, idstatus FROM status WHERE status = 'Text completely known' AND type = 'occurrence_text';

INSERT INTO document_acknowledgement (iddocument, idacknowledgement) VALUES (:occurrence_identity, 1);

INSERT INTO entity_management (identity, idmanagement) VALUES (:occurrence_identity, 1);

-- ---------- Type (reconstructed_poem), based on the occurrence above ----------

INSERT INTO reconstructed_poem DEFAULT VALUES RETURNING identity \gset type_

UPDATE poem SET incipit = 'Χαῖρε τέκνον, χαῖρε καλὸν φῶς', verses = 4 WHERE identity = :type_identity;

INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :type_identity, idlanguage, 'Test epigram on a benefactor (reconstructed)'
    FROM language WHERE name = 'Unknown';

INSERT INTO document_genre (iddocument, idgenre)
    SELECT :type_identity, idgenre FROM genre WHERE genre = 'Author-related epigram';

INSERT INTO poem_meter (idpoem, idmeter)
    SELECT :type_identity, idmeter FROM meter WHERE name = 'Dodecasyllable';

INSERT INTO document_status (iddocument, idstatus)
    SELECT :type_identity, idstatus FROM status WHERE status = 'Critical text' AND type = 'type_critical';

INSERT INTO entity_management (identity, idmanagement) VALUES (:type_identity, 1);

-- this type is a reconstruction based on the occurrence above -- also a
-- realistic example of the dependency check exercised when deleting an
-- occurrence (see OccurrenceService::delete)
INSERT INTO factoid (subject_identity, object_identity, idfactoid_type)
    SELECT :type_identity, :occurrence_identity, idfactoid_type FROM factoid_type WHERE type = 'based on';

SET search_path TO data;

-- each initdb script runs as its own psql session, so \gset variables from
-- 03.sample_content.sql aren't available here -- look the author back up
SELECT p.identity FROM person p JOIN name n ON n.idperson = p.identity WHERE n.last_name = 'Testopoulos' \gset person_author_

-- ---------- Keywords (subject keywords + tags) ----------

INSERT INTO keyword (keyword, is_subject) VALUES ('test subject keyword', true);
INSERT INTO keyword (keyword, is_subject) VALUES ('another test subject keyword', true);
INSERT INTO keyword (keyword, is_subject) VALUES ('test tag', false);
INSERT INTO keyword (keyword, is_subject) VALUES ('another test tag', false);

-- ---------- Bibliography: article (needs a journal + journal issue) ----------

INSERT INTO journal DEFAULT VALUES RETURNING identity \gset journal_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :journal_identity, idlanguage, 'Test Journal of Byzantine Studies' FROM language WHERE name = 'Unknown';

INSERT INTO journal_issue (idjournal, year, volume) VALUES (:journal_identity, '2020', '1') RETURNING identity \gset journal_issue_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :journal_issue_identity, idlanguage, 'Test Journal of Byzantine Studies, vol. 1' FROM language WHERE name = 'Unknown';

INSERT INTO article DEFAULT VALUES RETURNING identity \gset article_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :article_identity, idlanguage, 'A test article about a test epigram' FROM language WHERE name = 'Unknown';
INSERT INTO document_contains (idcontainer, idcontent, page_start, page_end)
    VALUES (:journal_issue_identity, :article_identity, '1', '10');
INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :article_identity, idrole FROM role WHERE system_name = 'author';

-- ---------- Bibliography: book + book chapter ----------

INSERT INTO book (city, year) VALUES ('Testville', 2020) RETURNING identity \gset book_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :book_identity, idlanguage, 'A Test Book on Byzantine Epigrams' FROM language WHERE name = 'Unknown';
INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :book_identity, idrole FROM role WHERE system_name = 'author';

INSERT INTO bookchapter DEFAULT VALUES RETURNING identity \gset bookchapter_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :bookchapter_identity, idlanguage, 'A test chapter about a test epigram' FROM language WHERE name = 'Unknown';
INSERT INTO document_contains (idcontainer, idcontent, page_start, page_end)
    VALUES (:book_identity, :bookchapter_identity, '11', '20');
INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :bookchapter_identity, idrole FROM role WHERE system_name = 'author';

-- ---------- Bibliography: online source (is-a institution, not a document) ----------

INSERT INTO institution (name) VALUES ('Test Online Source') RETURNING identity \gset online_source_
INSERT INTO online_source (identity, url) VALUES (:online_source_identity, 'https://example.invalid/test');

-- ---------- Bibliography: blog + blog post ----------

INSERT INTO blog (url) VALUES ('https://example.invalid/test-blog') RETURNING identity \gset blog_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :blog_identity, idlanguage, 'A Test Blog on Byzantine Epigrams' FROM language WHERE name = 'Unknown';

INSERT INTO blog_post (url, post_date) VALUES ('https://example.invalid/test-blog/post-1', '2020-01-01') RETURNING identity \gset blog_post_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :blog_post_identity, idlanguage, 'A test post about a test epigram' FROM language WHERE name = 'Unknown';
INSERT INTO document_contains (idcontainer, idcontent)
    VALUES (:blog_identity, :blog_post_identity);
INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :blog_post_identity, idrole FROM role WHERE system_name = 'author';

-- ---------- Bibliography: phd ----------

INSERT INTO phd (city, year) VALUES ('Testville', 2020) RETURNING identity \gset phd_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :phd_identity, idlanguage, 'A Test PhD Thesis on Byzantine Epigrams' FROM language WHERE name = 'Unknown';
INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :phd_identity, idrole FROM role WHERE system_name = 'author';

-- ---------- Bibliography: bib varia ----------

INSERT INTO bib_varia (year, city) VALUES (2020, 'Testville') RETURNING identity \gset bib_varia_
INSERT INTO document_title (iddocument, idlanguage, title)
    SELECT :bib_varia_identity, idlanguage, 'A Test Miscellaneous Reference' FROM language WHERE name = 'Unknown';
INSERT INTO bibrole (idperson, iddocument, idrole)
    SELECT :person_author_identity, :bib_varia_identity, idrole FROM role WHERE system_name = 'author';

-- ---------- A DBBE-flagged person (used for e.g. translator/contributor pickers) ----------

INSERT INTO person (is_historical, is_modern, is_dbbe) VALUES (false, true, true) RETURNING identity \gset person_dbbe_
INSERT INTO name (idperson, first_name, last_name, is_primary) VALUES (:person_dbbe_identity, 'Jane', 'Testerson', true);

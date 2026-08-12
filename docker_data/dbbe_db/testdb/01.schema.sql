SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;CREATE SCHEMA data;
CREATE SCHEMA logic;
CREATE SCHEMA migration;
CREATE TYPE data.fuzzydate AS (
	floor date,
	ceiling date
);
CREATE TYPE data.fuzzyinterval AS (
	start_floor date,
	start_ceiling date,
	end_floor date,
	end_ceiling date
);
CREATE FUNCTION data.delete_entity() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
	IF OLD.identity IS NOT NULL THEN
		DELETE FROM entity WHERE identity = OLD.identity;
	ELSE
		RAISE EXCEPTION 'identity field not set in row to be deleted, could not delete entity.';
	END IF;
	RETURN NULL;

END;$$;
CREATE FUNCTION data.ensure_document_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
	IF NEW.identity IS NULL THEN
		INSERT INTO document DEFAULT values returning identity into NEW.identity;
	END IF;
	RETURN NEW;
END;$$;
CREATE FUNCTION data.ensure_entity_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$DECLARE
	resultid integer;

BEGIN
	IF NEW.identity IS NULL THEN
		INSERT INTO entity DEFAULT VALUES returning identity into NEW.identity;
	END IF;
	RETURN NEW;
END;$$;
CREATE FUNCTION data.ensure_fund_has_location() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
	IF NEW.idfund IS NOT NULL THEN

		INSERT INTO location (idfund) values (NEW.idfund);

		RETURN NEW;

	ELSE

		RAISE EXCEPTION 'Could not add entry to location, no idfund field set.';

	END IF;

END;$$;
CREATE FUNCTION data.ensure_institution_has_location() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	IF NEW.identity IS NOT NULL THEN

		INSERT INTO location (idinstitution) values (NEW.identity);

		RETURN NEW;

	ELSE

		RAISE EXCEPTION 'Could not add entry to location, no identity field set.';

	END IF;

END;$$;
CREATE FUNCTION data.ensure_institution_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	IF NEW.identity IS NULL THEN

		INSERT INTO institution DEFAULT values returning identity into NEW.identity;

	END IF;

	RETURN NEW;

END;$$;
CREATE FUNCTION data.ensure_person_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	IF NEW.identity IS NULL THEN

		INSERT INTO person DEFAULT values returning identity into NEW.identity;

	END IF;

	RETURN NEW;

END;$$;
CREATE FUNCTION data.ensure_poem_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	IF NEW.identity IS NULL THEN

		INSERT INTO poem DEFAULT values returning identity into NEW.identity;

	END IF;

	RETURN NEW;

END;$$;
CREATE FUNCTION data.ensure_region_has_location() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	IF NEW.identity IS NOT NULL THEN

		INSERT INTO location (idregion) values (NEW.identity);

		RETURN NEW;

	ELSE

		RAISE EXCEPTION 'Could not add entry to location, no identity field set.';

	END IF;

END;$$;
CREATE FUNCTION data.is_roman_numeral(character varying) RETURNS boolean
    LANGUAGE plpgsql
    AS $_$
DECLARE

	inputstring ALIAS FOR $1;

	romannumeralregex varchar := '^(M{0,4})(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$';

	result integer := 0;

	res varchar[];

BEGIN

	return (select inputstring ~ romannumeralregex);

END;

$_$;
CREATE FUNCTION data.roman_numeral_to_integer(character varying) RETURNS integer
    LANGUAGE plpgsql
    AS $_$
DECLARE

	inputstring ALIAS FOR $1;

	romannumeralregex varchar := '^(M{0,4})(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$';

	result integer := 0;

	res varchar[];

BEGIN
	select into res regexp_matches(inputstring, romannumeralregex);
	result := 1000*length(res[1]);
	IF length(res[2])=0 THEN
	ELSIF res[2] = 'CD' THEN
		result:=result+400;
	ELSIF res[2] = 'CM' THEN
		result:=result+900;
	ELSIF res[2] = 'D' THEN
		result:=result+500;
	ELSIF res[2] ~ 'DC+' THEN
		result:=result+500+(length(res[2])-1)*100;
	ELSIF res[2] ~ 'C+' THEN
		result:=result+(length(res[2])*100);
	ELSE
		RAISE EXCEPTION 'Not a valid roman numeral.';
	END IF;
	IF length(res[3])=0 THEN
	ELSIF res[3] = 'XC' THEN
		result:=result+90;
	ELSIF res[3] = 'XL' THEN
		result:=result+40;
	ELSIF res[3] = 'L' THEN
		result:=result+50;
	ELSIF res[3] ~ 'LX+' THEN
		result:=result+50+10*(length(res[3])-1);
	ELSIF res[3] ~ 'X+' THEN
		result:=result+10*length(res[3]);
	ELSE
		RAISE EXCEPTION 'Not a valid roman numeral.';
	END IF;
	IF length(res[4])=0 THEN
	ELSIF res[4] = 'IX' THEN
		result:=result+9;
	ELSIF res[4] = 'IV' THEN
		result:=result+4;
	ELSIF res[4] = 'V' THEN
		result:=result+5;
	ELSIF res[4] ~ 'VI+' THEN
		result:=result+5+(length(res[4])-1);
	ELSIF res[4] ~ 'I+' THEN
		result:=result+length(res[4]);
	ELSE
		RAISE EXCEPTION 'Not a valid roman numeral.';
	END IF;
	return result;
END;

$_$;
CREATE FUNCTION public.some_func() RETURNS void
    LANGUAGE plpgsql
    AS $$

DECLARE
    approw record;
    journalissueid integer;
    journalid integer;
BEGIN
    set search_path = 'data';    IF NOT EXISTS (
        SELECT 1 FROM pg_catalog.pg_tables
        WHERE  schemaname = 'data'
        AND    tablename  = 'journal_issue'
    )
    THEN
        ALTER TABLE data.journal
            RENAME TO journal_issue;
        ALTER TABLE data.journal_issue
            RENAME CONSTRAINT pk_journal TO pk_journal_issue;
        ALTER TABLE data.journal_issue
            RENAME CONSTRAINT fk_journal_document TO fk_journal_issue_document;
        ALTER TRIGGER delete_entity_instead_of_journal
            ON data.journal_issue
            RENAME TO delete_entity_instead_of_journal_issue;
        ALTER TRIGGER ensure_journal_has_document
            ON data.journal_issue
            RENAME TO ensure_journal_issue_has_document;
        CREATE TABLE data.journal (
            identity integer NOT NULL,
                CONSTRAINT pk_journal PRIMARY KEY (identity),
                CONSTRAINT fk_journal_document
                    FOREIGN KEY (identity)
                    REFERENCES data.document (identity) MATCH SIMPLE
                    ON UPDATE CASCADE ON DELETE CASCADE
        );
        CREATE TRIGGER delete_entity_instead_of_journal
            AFTER DELETE
            ON data.journal
            FOR EACH ROW
            EXECUTE PROCEDURE data.delete_entity();
        CREATE TRIGGER ensure_journal_has_document
            BEFORE INSERT
            ON data.journal
            FOR EACH ROW
            EXECUTE PROCEDURE data.ensure_document_presence();
        ALTER TABLE data.journal_issue
            ADD COLUMN idjournal INTEGER;
        ALTER TABLE data.journal_issue
            ADD CONSTRAINT fk_journal_journal_issue
            FOREIGN KEY (idjournal)
            REFERENCES data.journal (identity) MATCH SIMPLE
            ON UPDATE RESTRICT ON DELETE RESTRICT;
    END IF;

    FOR approw IN (
        select * from data.article
        inner join data.document_contains on article.identity = document_contains.idcontent
        inner join data.journal_issue on document_contains.idcontainer = journal_issue.identity
        inner join data.document_title on journal_issue.identity = document_title.iddocument
    ) LOOP
        select identity into journalissueid from data.journal_issue inner join data.document_title on idjournal = iddocument where
            ((title is null and approw.title is null) or (title = approw.title)) and
            ((volume is null and approw.volume is null) or (volume = approw.volume)) and
            ((year is null and approw.year is null) or (year = approw.year)) and
            ((number is null and approw.number is null) or (number = approw.number));
        IF journalissueid is null THEN
            select identity into journalid from data.journal inner join data.document_title on identity = iddocument where
                ((title is null and approw.title is null) or (title = approw.title));
            IF journalid is null THEN
                insert into data.journal DEFAULT VALUES returning identity into journalid;
                insert into data.document_title (iddocument, idlanguage, title) values (journalid, (select idlanguage from language where code = '?'), approw.title);
            END IF;
            insert into data.journal_issue (volume, number, year, idjournal) values (approw.volume, approw.number, approw.year, journalid) returning identity into journalissueid;
        END IF;
        update data.document_contains set idcontainer = journalissueid where idcontent = approw.idcontent;
    END LOOP;
END;

$$;SET default_tablespace = '';

SET default_table_access_method = heap;CREATE TABLE data.acknowledgement (
    id integer NOT NULL,
    acknowledgement character varying NOT NULL
);
CREATE TABLE data.acknowledgement_expression (
    expression_text text NOT NULL
);
CREATE SEQUENCE data.acknowledgement_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.acknowledgement_id_seq OWNED BY data.acknowledgement.id;
CREATE TABLE data.article (
    identity integer NOT NULL
);
COMMENT ON TABLE data.article IS 'Subclass of Entity';
CREATE TABLE data.bib_varia (
    identity integer NOT NULL,
    city character varying,
    year integer,
    institution character varying
);
CREATE TABLE data.bibrole (
    idperson integer NOT NULL,
    iddocument integer NOT NULL,
    rank integer,
    created timestamp with time zone DEFAULT now() NOT NULL,
    idrole integer NOT NULL
);
CREATE TABLE data.blog (
    identity integer NOT NULL,
    url character varying,
    last_accessed timestamp with time zone
);
CREATE TABLE data.blog_post (
    identity integer NOT NULL,
    url character varying,
    post_date timestamp with time zone
);
CREATE TABLE data.book (
    identity integer NOT NULL,
    total_volumes integer,
    city character varying,
    year integer,
    old_volume integer,
    series character varying,
    publisher character varying,
    editor character varying,
    volume character varying,
    idcluster integer,
    idseries integer,
    series_volume character varying,
    forthcoming boolean DEFAULT false NOT NULL
);
COMMENT ON TABLE data.book IS 'Subclass of Document';
CREATE TABLE data.book_cluster (
    identity integer NOT NULL
);
CREATE TABLE data.book_series (
    identity integer NOT NULL
);
CREATE TABLE data.bookchapter (
    identity integer NOT NULL
);
COMMENT ON TABLE data.bookchapter IS 'Subclass of Document';
CREATE TABLE data.document (
    identity integer NOT NULL,
    text_content text,
    is_illustrated boolean
);
COMMENT ON COLUMN data.document.identity IS 'refers to entity.identity';
CREATE TABLE data.document_acknowledgement (
    iddocument integer NOT NULL,
    idacknowledgement integer
);
CREATE TABLE data.document_contains (
    idcontainer integer NOT NULL,
    idcontent integer NOT NULL,
    general_location character varying,
    physical_location_removeme character varying,
    page_start character varying,
    page_end character varying,
    folium_start character varying,
    folium_start_recto boolean,
    folium_end character varying,
    folium_end_recto boolean,
    contextual_info character varying,
    unsure boolean DEFAULT false NOT NULL,
    processed_removeme boolean DEFAULT false NOT NULL,
    iddocumentcontains integer NOT NULL,
    comment character varying,
    "order" integer,
    alternative_folium_start character varying,
    alternative_folium_start_recto boolean,
    alternative_folium_end character varying,
    alternative_folium_end_recto boolean,
    alternative_page_start character varying,
    alternative_page_end character varying
);
CREATE SEQUENCE data.document_contains_iddocumentcontains_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.document_contains_iddocumentcontains_seq OWNED BY data.document_contains.iddocumentcontains;
CREATE TABLE data.document_genre (
    iddocument integer NOT NULL,
    idgenre integer NOT NULL
);
CREATE TABLE data.document_group (
    iddocument integer NOT NULL,
    idgroup integer NOT NULL
);
CREATE TABLE data.document_image (
    iddocument integer NOT NULL,
    idimage integer NOT NULL
);
CREATE TABLE data.document_keyword (
    iddocument integer NOT NULL,
    idkeyword integer NOT NULL
);
CREATE TABLE data.document_status (
    iddocument integer NOT NULL,
    idstatus integer NOT NULL
);
CREATE TABLE data.document_title (
    iddocument integer NOT NULL,
    idlanguage integer NOT NULL,
    title character varying NOT NULL
);
CREATE TABLE data.entity (
    identity integer NOT NULL,
    public_comment character varying,
    private_comment character varying,
    created timestamp with time zone DEFAULT now() NOT NULL,
    modified timestamp with time zone DEFAULT now() NOT NULL,
    removeme_comment_private_processed boolean DEFAULT false NOT NULL,
    removeme_comment_public_processed boolean DEFAULT false NOT NULL,
    checked boolean DEFAULT false NOT NULL,
    public boolean
);
COMMENT ON COLUMN data.entity.checked IS 'this field originates from the old database: biblio_objects.checked

can now be used for everything, simple flag to indicate it has been checked by someone';
CREATE SEQUENCE data.entity_identity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.entity_identity_seq OWNED BY data.entity.identity;
CREATE TABLE data.entity_management (
    identity integer NOT NULL,
    idmanagement integer NOT NULL
);
CREATE TABLE data.entity_url (
    idurl integer NOT NULL,
    identity integer NOT NULL,
    title character varying,
    url character varying NOT NULL,
    "order" integer
);
CREATE SEQUENCE data.entity_url_idurl_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.entity_url_idurl_seq OWNED BY data.entity_url.idurl;
CREATE TABLE data.evidence (
    idevidence integer NOT NULL,
    idreference integer,
    idperson integer,
    date data.fuzzydate
);
CREATE TABLE data.evidence_factoid (
    idevidence integer NOT NULL,
    idfactoid integer NOT NULL
);
CREATE SEQUENCE data.evidence_idevidence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.evidence_idevidence_seq OWNED BY data.evidence.idevidence;
CREATE TABLE data.factoid (
    idfactoid integer NOT NULL,
    subject_identity integer NOT NULL,
    object_identity integer,
    date data.fuzzydate,
    "interval" data.fuzzyinterval,
    idlocation integer,
    idfactoid_type integer NOT NULL,
    rank integer,
    CONSTRAINT date_interval_exclusive CHECK ((NOT ((date IS NOT NULL) AND ("interval" IS NOT NULL))))
);
CREATE TABLE data.factoid_backup_26082025 (
    idfactoid integer,
    subject_identity integer,
    object_identity integer,
    date data.fuzzydate,
    "interval" data.fuzzyinterval,
    idlocation integer,
    idfactoid_type integer,
    rank integer
);
CREATE SEQUENCE data.factoid_idfactoid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.factoid_idfactoid_seq OWNED BY data.factoid.idfactoid;
CREATE TABLE data.factoid_type (
    description character varying,
    "group" character varying,
    idfactoid_type integer NOT NULL,
    type character varying NOT NULL,
    idinverse integer
);
CREATE SEQUENCE data.factoid_type_idfactoid_type_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.factoid_type_idfactoid_type_seq OWNED BY data.factoid_type.idfactoid_type;
CREATE TABLE data.fund (
    idfund integer NOT NULL,
    idlibrary integer,
    name character varying,
    created timestamp with time zone,
    modified timestamp with time zone
);
CREATE SEQUENCE data.fund_idfund_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.fund_idfund_seq OWNED BY data.fund.idfund;
CREATE TABLE data.genre (
    idgenre integer NOT NULL,
    idparentgenre integer,
    genre character varying,
    description character varying,
    is_content boolean,
    idperson integer
);
CREATE SEQUENCE data.genre_idgenre_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.genre_idgenre_seq OWNED BY data.genre.idgenre;
CREATE TABLE data.global_id (
    idauthority integer NOT NULL,
    idsubject integer NOT NULL,
    identifier character varying NOT NULL,
    extra character varying,
    volume integer
);
COMMENT ON TABLE data.global_id IS 'This allows an entity (the authority) to link to another identity (the subject) using an identifier.';
CREATE TABLE data.node (
    identity integer NOT NULL,
    idparentnode integer,
    iddocument integer
);
COMMENT ON TABLE data.node IS 'node is used to logically group documents into a tree';
COMMENT ON COLUMN data.node.iddocument IS 'This is optionally set. When set, this node is actually a document... if it is not set, the node functions as a pure node';
CREATE SEQUENCE data.group_identity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.group_identity_seq OWNED BY data.node.identity;
CREATE TABLE data.identifier (
    ididentifier integer NOT NULL,
    type text[] NOT NULL,
    system_name character varying NOT NULL,
    name character varying NOT NULL,
    ids integer[] NOT NULL,
    regex character varying NOT NULL,
    description character varying,
    is_primary boolean NOT NULL,
    "order" integer NOT NULL,
    link character varying,
    created timestamp with time zone DEFAULT now() NOT NULL,
    modified timestamp with time zone DEFAULT now() NOT NULL,
    extra boolean DEFAULT false,
    link_type character varying,
    extra_required boolean
);
CREATE SEQUENCE data.identifier_ididentifier_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.identifier_ididentifier_seq OWNED BY data.identifier.ididentifier;
CREATE TABLE data.image (
    idimage integer NOT NULL,
    url character varying,
    is_private boolean DEFAULT false NOT NULL,
    filename character varying
);
CREATE SEQUENCE data.image_idimage_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.image_idimage_seq OWNED BY data.image.idimage;
CREATE TABLE data.institution (
    identity integer NOT NULL,
    idregion integer,
    name text,
    name_abbreviated character varying
);
CREATE TABLE data.journal (
    identity integer NOT NULL
);
CREATE TABLE data.journal_issue (
    identity integer NOT NULL,
    year character varying,
    month integer,
    place character varying,
    publisher character varying,
    editor character varying,
    number character varying,
    volume character varying,
    title_abbreviated character varying,
    idjournal integer NOT NULL,
    forthcoming boolean DEFAULT false NOT NULL,
    series character varying(255)
);
CREATE TABLE data.keyword (
    identity integer NOT NULL,
    keyword character varying,
    is_subject boolean
);
CREATE TABLE data.language (
    idlanguage integer NOT NULL,
    name character varying NOT NULL,
    code character varying,
    description character varying
);
CREATE SEQUENCE data.language_idlanguage_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.language_idlanguage_seq OWNED BY data.language.idlanguage;
CREATE TABLE data.lemma_cache (
    input character varying NOT NULL,
    output character varying
);
CREATE TABLE data.library (
    identity integer NOT NULL
);
CREATE TABLE data.located_at (
    iddocument integer NOT NULL,
    idlocation integer NOT NULL,
    identification character varying,
    "interval" data.fuzzyinterval,
    extra character varying
);
CREATE TABLE data.location (
    idregion integer,
    idinstitution integer,
    idfund integer,
    idlocation integer NOT NULL,
    CONSTRAINT one_of_three_is_not_null CHECK ((((
CASE
    WHEN (idregion IS NULL) THEN 0
    ELSE 1
END +
CASE
    WHEN (idfund IS NULL) THEN 0
    ELSE 1
END) +
CASE
    WHEN (idinstitution IS NULL) THEN 0
    ELSE 1
END) = 1))
);
CREATE SEQUENCE data.location_idlocation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.location_idlocation_seq OWNED BY data.location.idlocation;
CREATE TABLE data.management (
    id integer NOT NULL,
    name character varying NOT NULL
);
CREATE SEQUENCE data.management_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.management_id_seq OWNED BY data.management.id;
CREATE TABLE data.manuscript (
    identity integer NOT NULL
);
CREATE TABLE data.meter (
    idmeter integer NOT NULL,
    name character varying NOT NULL
);
CREATE SEQUENCE data.meter_idmeter_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.meter_idmeter_seq OWNED BY data.meter.idmeter;
CREATE TABLE data.monastery (
    identity integer NOT NULL
);
CREATE TABLE data.name (
    idname integer NOT NULL,
    idperson integer NOT NULL,
    idtransliterationsystem integer,
    first_name character varying,
    middle_name character varying,
    last_name character varying,
    extra character varying,
    unprocessed character varying,
    processed character varying,
    self_designations character varying,
    is_primary boolean DEFAULT true NOT NULL
);
CREATE SEQUENCE data.name_idname_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.name_idname_seq OWNED BY data.name.idname;
CREATE TABLE data.occupation (
    idoccupation integer NOT NULL,
    occupation character varying NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    modified timestamp with time zone DEFAULT now() NOT NULL,
    idparentoccupation integer,
    idregion integer
);
CREATE SEQUENCE data.occupation_idoccupation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.occupation_idoccupation_seq OWNED BY data.occupation.idoccupation;
CREATE TABLE data.online_source (
    identity integer NOT NULL,
    url character varying,
    last_accessed timestamp with time zone
);
CREATE TABLE data.original_poem (
    identity integer NOT NULL,
    paleographical_info character varying,
    transcription_reviewed boolean
);
COMMENT ON TABLE data.original_poem IS 'Subclass of Poem';
CREATE TABLE data.original_poem_verse (
    id integer NOT NULL,
    idoriginal_poem integer NOT NULL,
    idgroup integer,
    verse character varying NOT NULL,
    "order" integer NOT NULL
);
CREATE SEQUENCE data.original_poem_verse_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.original_poem_verse_id_seq OWNED BY data.original_poem_verse.id;
CREATE TABLE data.person (
    identity integer NOT NULL,
    email character varying,
    is_historical boolean,
    is_modern boolean,
    is_dbbe boolean
);
COMMENT ON TABLE data.person IS 'email needs to be moved to person_email';
CREATE TABLE data.person_acknowledgement (
    idperson integer NOT NULL,
    idacknowledgement integer NOT NULL
);
CREATE TABLE data.person_email (
    idperson integer NOT NULL,
    email character varying NOT NULL
);
CREATE TABLE data.person_occupation (
    idperson integer NOT NULL,
    idoccupation integer NOT NULL
);
CREATE TABLE data.person_self_designation (
    idperson integer NOT NULL,
    idself_designation integer NOT NULL
);
CREATE TABLE data.phd (
    identity integer NOT NULL,
    city character varying,
    year integer,
    institution character varying,
    volume character varying,
    forthcoming boolean DEFAULT false NOT NULL
);
CREATE TABLE data.poem (
    identity integer NOT NULL,
    verses integer,
    incipit character varying
);
COMMENT ON TABLE data.poem IS 'Subclass of Document';
CREATE TABLE data.poem_meter (
    idpoem integer NOT NULL,
    idmeter integer NOT NULL
);
CREATE TABLE data.reconstructed_poem (
    identity integer NOT NULL,
    critical_apparatus character varying
);
COMMENT ON TABLE data.reconstructed_poem IS 'Subclass of Poem';
CREATE TABLE data.reconstructed_poem_lemma (
    id integer NOT NULL,
    id_reconstructed_poem integer NOT NULL,
    lemma character varying
);
CREATE SEQUENCE data.reconstructed_poem_lemma_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.reconstructed_poem_lemma_id_seq OWNED BY data.reconstructed_poem_lemma.id;
CREATE TABLE data.reference (
    idreference integer NOT NULL,
    idsource integer NOT NULL,
    idtarget integer NOT NULL,
    date date,
    url character varying,
    temp_page_removeme character varying,
    page_start character varying,
    page_end character varying,
    temp_page_processed_removeme boolean DEFAULT false NOT NULL,
    figure character varying,
    "table" character varying,
    image character varying,
    footnote character varying,
    source_remark character varying,
    private_comment character varying,
    public_comment character varying,
    idreference_type integer
);
COMMENT ON COLUMN data.reference.source_remark IS 'This remark was made by the source of the reference. Do not confuse with internal comments!';
CREATE SEQUENCE data.reference_idreference_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.reference_idreference_seq OWNED BY data.reference.idreference;
CREATE TABLE data.reference_type (
    idreference_type integer NOT NULL,
    type character varying NOT NULL
);
CREATE SEQUENCE data.reference_type_idreference_type_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.reference_type_idreference_type_seq OWNED BY data.reference_type.idreference_type;
CREATE TABLE data.region (
    identity integer NOT NULL,
    parent_idregion integer,
    name text,
    historical_name text,
    is_city boolean
);
CREATE TABLE data.role (
    idrole integer NOT NULL,
    type text[] NOT NULL,
    system_name character varying NOT NULL,
    name character varying NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    modified timestamp with time zone DEFAULT now() NOT NULL,
    is_contributor_role boolean,
    has_rank boolean,
    "order" integer
);
CREATE SEQUENCE data.role_idrole_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.role_idrole_seq OWNED BY data.role.idrole;
CREATE TABLE data.self_designation (
    id integer NOT NULL,
    name character varying NOT NULL
);
CREATE SEQUENCE data.self_designation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.self_designation_id_seq OWNED BY data.self_designation.id;
CREATE TABLE data.status (
    idstatus integer NOT NULL,
    status character varying NOT NULL,
    type character varying NOT NULL
);
CREATE SEQUENCE data.status_idstatus_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.status_idstatus_seq OWNED BY data.status.idstatus;
CREATE TABLE data.translation (
    identity integer NOT NULL,
    idlanguage integer
);
CREATE TABLE data.translation_of (
    idtranslation integer NOT NULL,
    iddocument integer NOT NULL
);
CREATE TABLE data.transliterationsystem (
    idtransliterationsystem integer NOT NULL,
    name character varying
);
CREATE SEQUENCE data.transliterationsystem_idtransliterationsystem_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE data.transliterationsystem_idtransliterationsystem_seq OWNED BY data.transliterationsystem.idtransliterationsystem;
CREATE TABLE logic.contributor_of (
    iduser integer NOT NULL,
    iddocument integer NOT NULL
);
CREATE TABLE logic.feedback (
    id integer NOT NULL,
    url character varying(4000) NOT NULL,
    email character varying(4000) NOT NULL,
    message character varying(4000) NOT NULL,
    created timestamp(0) without time zone DEFAULT now() NOT NULL,
    status character varying(40) DEFAULT 'new'::character varying NOT NULL
);
CREATE SEQUENCE logic.feedback_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.feedback_id_seq OWNED BY logic.feedback.id;
CREATE TABLE logic.fos_user (
    id integer NOT NULL,
    username character varying(180) NOT NULL,
    username_canonical character varying(180) NOT NULL,
    email character varying(180) NOT NULL,
    email_canonical character varying(180) NOT NULL,
    enabled boolean NOT NULL,
    salt character varying(255) DEFAULT NULL::character varying,
    password character varying(255) NOT NULL,
    last_login timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    confirmation_token character varying(180) DEFAULT NULL::character varying,
    password_requested_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    roles text NOT NULL,
    full_name character varying(255) DEFAULT NULL::character varying,
    start_tenure date,
    end_tenure date,
    created timestamp(0) without time zone NOT NULL,
    modified timestamp(0) without time zone NOT NULL
);
COMMENT ON COLUMN logic.fos_user.roles IS '(DC2Type:array)';
CREATE SEQUENCE logic.fos_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
CREATE TABLE logic.news_event (
    id integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    modified timestamp with time zone DEFAULT now() NOT NULL,
    title character varying NOT NULL,
    url character varying,
    date character varying NOT NULL,
    public boolean NOT NULL,
    "order" integer NOT NULL,
    abstract character varying,
    text character varying,
    user_email character varying(254) NOT NULL
);
CREATE SEQUENCE logic.news_event_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.news_event_id_seq OWNED BY logic.news_event.id;
CREATE TABLE logic.page (
    id integer NOT NULL,
    revision integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    title character varying NOT NULL,
    slug character varying NOT NULL,
    content character varying NOT NULL,
    display_navigation boolean,
    user_email character varying(254) NOT NULL
);
CREATE SEQUENCE logic.page_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.page_id_seq OWNED BY logic.page.id;
CREATE TABLE logic.revision (
    idrevision integer NOT NULL,
    type character varying,
    identity integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    old_value character varying,
    new_value character varying,
    user_email character varying(254) NOT NULL
);
CREATE TABLE logic.revision_2019_05_15 (
    idrevision integer NOT NULL,
    type character varying,
    identity integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    iduser integer NOT NULL,
    old_value character varying,
    new_value character varying
);
CREATE TABLE logic.revision_old (
    idrevision integer NOT NULL,
    type character varying,
    identity integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    iduser integer NOT NULL,
    old_value character varying,
    new_value character varying
);
CREATE SEQUENCE logic.revision_idrevision_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.revision_idrevision_seq OWNED BY logic.revision_old.idrevision;
CREATE SEQUENCE logic.revision_idrevision_seq1
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.revision_idrevision_seq1 OWNED BY logic.revision_2019_05_15.idrevision;
CREATE SEQUENCE logic.revision_idrevision_seq2
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.revision_idrevision_seq2 OWNED BY logic.revision.idrevision;
CREATE TABLE logic."user" (
    id integer NOT NULL,
    username character varying(255) NOT NULL,
    roles text NOT NULL,
    created timestamp(0) without time zone NOT NULL,
    modified timestamp(0) without time zone NOT NULL,
    last_login timestamp(0) without time zone DEFAULT NULL::timestamp without time zone
);
COMMENT ON COLUMN logic."user".roles IS '(DC2Type:array)';
CREATE SEQUENCE logic.user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE logic.user_id_seq OWNED BY logic."user".id;
CREATE TABLE logic.user_old (
    identity integer NOT NULL,
    password_hash character varying NOT NULL,
    start_tenure date,
    end_tenure date
);
CREATE TABLE logic.working_on (
    iduser integer NOT NULL,
    iddocument integer NOT NULL
);


CREATE TABLE migration.manuscripts_to_manuscript (
                                                     old_id integer NOT NULL,
                                                     identity integer NOT NULL
);
CREATE TABLE migration.occurrence_to_entity (
                                                old_id integer,
                                                identity integer
);
CREATE TABLE migration.type_to_reconstructed_poem (
                                                      idtype integer,
                                                      identity integer
);
ALTER TABLE ONLY data.acknowledgement ALTER COLUMN id SET DEFAULT nextval('data.acknowledgement_id_seq'::regclass);
ALTER TABLE ONLY data.document_contains ALTER COLUMN iddocumentcontains SET DEFAULT nextval('data.document_contains_iddocumentcontains_seq'::regclass);
ALTER TABLE ONLY data.entity ALTER COLUMN identity SET DEFAULT nextval('data.entity_identity_seq'::regclass);
ALTER TABLE ONLY data.entity_url ALTER COLUMN idurl SET DEFAULT nextval('data.entity_url_idurl_seq'::regclass);
ALTER TABLE ONLY data.evidence ALTER COLUMN idevidence SET DEFAULT nextval('data.evidence_idevidence_seq'::regclass);
ALTER TABLE ONLY data.factoid ALTER COLUMN idfactoid SET DEFAULT nextval('data.factoid_idfactoid_seq'::regclass);
ALTER TABLE ONLY data.factoid_type ALTER COLUMN idfactoid_type SET DEFAULT nextval('data.factoid_type_idfactoid_type_seq'::regclass);
ALTER TABLE ONLY data.fund ALTER COLUMN idfund SET DEFAULT nextval('data.fund_idfund_seq'::regclass);
ALTER TABLE ONLY data.genre ALTER COLUMN idgenre SET DEFAULT nextval('data.genre_idgenre_seq'::regclass);
ALTER TABLE ONLY data.identifier ALTER COLUMN ididentifier SET DEFAULT nextval('data.identifier_ididentifier_seq'::regclass);
ALTER TABLE ONLY data.image ALTER COLUMN idimage SET DEFAULT nextval('data.image_idimage_seq'::regclass);
ALTER TABLE ONLY data.language ALTER COLUMN idlanguage SET DEFAULT nextval('data.language_idlanguage_seq'::regclass);
ALTER TABLE ONLY data.location ALTER COLUMN idlocation SET DEFAULT nextval('data.location_idlocation_seq'::regclass);
ALTER TABLE ONLY data.management ALTER COLUMN id SET DEFAULT nextval('data.management_id_seq'::regclass);
ALTER TABLE ONLY data.meter ALTER COLUMN idmeter SET DEFAULT nextval('data.meter_idmeter_seq'::regclass);
ALTER TABLE ONLY data.name ALTER COLUMN idname SET DEFAULT nextval('data.name_idname_seq'::regclass);
ALTER TABLE ONLY data.node ALTER COLUMN identity SET DEFAULT nextval('data.group_identity_seq'::regclass);
ALTER TABLE ONLY data.occupation ALTER COLUMN idoccupation SET DEFAULT nextval('data.occupation_idoccupation_seq'::regclass);
ALTER TABLE ONLY data.original_poem_verse ALTER COLUMN id SET DEFAULT nextval('data.original_poem_verse_id_seq'::regclass);
ALTER TABLE ONLY data.reconstructed_poem_lemma ALTER COLUMN id SET DEFAULT nextval('data.reconstructed_poem_lemma_id_seq'::regclass);
ALTER TABLE ONLY data.reference ALTER COLUMN idreference SET DEFAULT nextval('data.reference_idreference_seq'::regclass);
ALTER TABLE ONLY data.reference_type ALTER COLUMN idreference_type SET DEFAULT nextval('data.reference_type_idreference_type_seq'::regclass);
ALTER TABLE ONLY data.role ALTER COLUMN idrole SET DEFAULT nextval('data.role_idrole_seq'::regclass);
ALTER TABLE ONLY data.self_designation ALTER COLUMN id SET DEFAULT nextval('data.self_designation_id_seq'::regclass);
ALTER TABLE ONLY data.status ALTER COLUMN idstatus SET DEFAULT nextval('data.status_idstatus_seq'::regclass);
ALTER TABLE ONLY data.transliterationsystem ALTER COLUMN idtransliterationsystem SET DEFAULT nextval('data.transliterationsystem_idtransliterationsystem_seq'::regclass);
ALTER TABLE ONLY logic.feedback ALTER COLUMN id SET DEFAULT nextval('logic.feedback_id_seq'::regclass);
ALTER TABLE ONLY logic.news_event ALTER COLUMN id SET DEFAULT nextval('logic.news_event_id_seq'::regclass);
ALTER TABLE ONLY logic.page ALTER COLUMN id SET DEFAULT nextval('logic.page_id_seq'::regclass);
ALTER TABLE ONLY logic.revision ALTER COLUMN idrevision SET DEFAULT nextval('logic.revision_idrevision_seq2'::regclass);
ALTER TABLE ONLY logic.revision_2019_05_15 ALTER COLUMN idrevision SET DEFAULT nextval('logic.revision_idrevision_seq1'::regclass);
ALTER TABLE ONLY logic.revision_old ALTER COLUMN idrevision SET DEFAULT nextval('logic.revision_idrevision_seq'::regclass);
ALTER TABLE ONLY logic."user" ALTER COLUMN id SET DEFAULT nextval('logic.user_id_seq'::regclass);
ALTER TABLE ONLY migration.manuscripts_to_manuscript ADD CONSTRAINT pk_mtm PRIMARY KEY (old_id, identity);
ALTER TABLE ONLY data.identifier
    ADD CONSTRAINT identifier_system_name_key UNIQUE (system_name);
ALTER TABLE ONLY data.lemma_cache
    ADD CONSTRAINT lemma_cache_pkey PRIMARY KEY (input);
ALTER TABLE ONLY data.acknowledgement
    ADD CONSTRAINT pk_acknowledgement PRIMARY KEY (id);
ALTER TABLE ONLY data.article
    ADD CONSTRAINT pk_article PRIMARY KEY (identity);
ALTER TABLE ONLY data.bib_varia
    ADD CONSTRAINT pk_bib_varia PRIMARY KEY (identity);
ALTER TABLE ONLY data.blog
    ADD CONSTRAINT pk_blog PRIMARY KEY (identity);
ALTER TABLE ONLY data.blog_post
    ADD CONSTRAINT pk_blog_post PRIMARY KEY (identity);
ALTER TABLE ONLY data.book
    ADD CONSTRAINT pk_book PRIMARY KEY (identity);
ALTER TABLE ONLY data.book_cluster
    ADD CONSTRAINT pk_book_cluster PRIMARY KEY (identity);
ALTER TABLE ONLY data.book_series
    ADD CONSTRAINT pk_book_series PRIMARY KEY (identity);
ALTER TABLE ONLY data.bookchapter
    ADD CONSTRAINT pk_bookchapter PRIMARY KEY (identity);
ALTER TABLE ONLY data.document
    ADD CONSTRAINT pk_document PRIMARY KEY (identity);
ALTER TABLE ONLY data.document_contains
    ADD CONSTRAINT pk_document_contains PRIMARY KEY (iddocumentcontains);
ALTER TABLE ONLY data.document_genre
    ADD CONSTRAINT pk_document_genre PRIMARY KEY (iddocument, idgenre);
ALTER TABLE ONLY data.document_group
    ADD CONSTRAINT pk_document_group PRIMARY KEY (iddocument, idgroup);
ALTER TABLE ONLY data.document_image
    ADD CONSTRAINT pk_document_image PRIMARY KEY (iddocument, idimage);
ALTER TABLE ONLY data.document_keyword
    ADD CONSTRAINT pk_document_keyword PRIMARY KEY (iddocument, idkeyword);
ALTER TABLE ONLY data.document_status
    ADD CONSTRAINT pk_document_status PRIMARY KEY (iddocument, idstatus);
ALTER TABLE ONLY data.document_title
    ADD CONSTRAINT pk_document_title PRIMARY KEY (iddocument, idlanguage);
ALTER TABLE ONLY data.entity
    ADD CONSTRAINT pk_entity PRIMARY KEY (identity);
ALTER TABLE ONLY data.entity_management
    ADD CONSTRAINT pk_entity_management PRIMARY KEY (identity, idmanagement);
ALTER TABLE ONLY data.evidence
    ADD CONSTRAINT pk_evidence PRIMARY KEY (idevidence);
ALTER TABLE ONLY data.evidence_factoid
    ADD CONSTRAINT pk_evidence_factoid PRIMARY KEY (idevidence, idfactoid);
ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT pk_factoid PRIMARY KEY (idfactoid);
ALTER TABLE ONLY data.factoid_type
    ADD CONSTRAINT pk_factoid_type PRIMARY KEY (idfactoid_type);
ALTER TABLE ONLY data.fund
    ADD CONSTRAINT pk_fund PRIMARY KEY (idfund);
ALTER TABLE ONLY data.genre
    ADD CONSTRAINT pk_genre PRIMARY KEY (idgenre);
ALTER TABLE ONLY data.global_id
    ADD CONSTRAINT pk_global_id PRIMARY KEY (idauthority, idsubject);
ALTER TABLE ONLY data.identifier
    ADD CONSTRAINT pk_identifier PRIMARY KEY (ididentifier);
ALTER TABLE ONLY data.image
    ADD CONSTRAINT pk_image PRIMARY KEY (idimage);
ALTER TABLE ONLY data.institution
    ADD CONSTRAINT pk_institution PRIMARY KEY (identity);
ALTER TABLE ONLY data.journal
    ADD CONSTRAINT pk_journal PRIMARY KEY (identity);
ALTER TABLE ONLY data.journal_issue
    ADD CONSTRAINT pk_journal_issue PRIMARY KEY (identity);
ALTER TABLE ONLY data.keyword
    ADD CONSTRAINT pk_keyword PRIMARY KEY (identity);
ALTER TABLE ONLY data.language
    ADD CONSTRAINT pk_language PRIMARY KEY (idlanguage);
ALTER TABLE ONLY data.library
    ADD CONSTRAINT pk_library PRIMARY KEY (identity);
ALTER TABLE ONLY data.location
    ADD CONSTRAINT pk_location PRIMARY KEY (idlocation);
ALTER TABLE ONLY data.management
    ADD CONSTRAINT pk_management PRIMARY KEY (id);
ALTER TABLE ONLY data.manuscript
    ADD CONSTRAINT pk_manuscript PRIMARY KEY (identity);
ALTER TABLE ONLY data.meter
    ADD CONSTRAINT pk_meter PRIMARY KEY (idmeter);
ALTER TABLE ONLY data.monastery
    ADD CONSTRAINT pk_monastery PRIMARY KEY (identity);
ALTER TABLE ONLY data.name
    ADD CONSTRAINT pk_name PRIMARY KEY (idname);
ALTER TABLE ONLY data.node
    ADD CONSTRAINT pk_node PRIMARY KEY (identity);
ALTER TABLE ONLY data.occupation
    ADD CONSTRAINT pk_occupation PRIMARY KEY (idoccupation);
ALTER TABLE ONLY data.online_source
    ADD CONSTRAINT pk_online_source PRIMARY KEY (identity);
ALTER TABLE ONLY data.original_poem
    ADD CONSTRAINT pk_original_poem PRIMARY KEY (identity);
ALTER TABLE ONLY data.original_poem_verse
    ADD CONSTRAINT pk_original_poem_verse PRIMARY KEY (id);
ALTER TABLE ONLY data.person
    ADD CONSTRAINT pk_person PRIMARY KEY (identity);
ALTER TABLE ONLY data.person_email
    ADD CONSTRAINT pk_person_email PRIMARY KEY (idperson, email);
ALTER TABLE ONLY data.person_occupation
    ADD CONSTRAINT pk_person_occupation PRIMARY KEY (idperson, idoccupation);
ALTER TABLE ONLY data.person_self_designation
    ADD CONSTRAINT pk_person_self_designation PRIMARY KEY (idperson, idself_designation);
ALTER TABLE ONLY data.phd
    ADD CONSTRAINT pk_phd PRIMARY KEY (identity);
ALTER TABLE ONLY data.poem
    ADD CONSTRAINT pk_poem PRIMARY KEY (identity);
ALTER TABLE ONLY data.poem_meter
    ADD CONSTRAINT pk_poem_meter PRIMARY KEY (idmeter, idpoem);
ALTER TABLE ONLY data.reconstructed_poem
    ADD CONSTRAINT pk_reconstructed_poem PRIMARY KEY (identity);
ALTER TABLE ONLY data.reference
    ADD CONSTRAINT pk_reference PRIMARY KEY (idreference);
ALTER TABLE ONLY data.reference_type
    ADD CONSTRAINT pk_reference_type PRIMARY KEY (idreference_type);
ALTER TABLE ONLY data.region
    ADD CONSTRAINT pk_region PRIMARY KEY (identity);
ALTER TABLE ONLY data.role
    ADD CONSTRAINT pk_role PRIMARY KEY (idrole);
ALTER TABLE ONLY data.self_designation
    ADD CONSTRAINT pk_self_designation PRIMARY KEY (id);
ALTER TABLE ONLY data.status
    ADD CONSTRAINT pk_status PRIMARY KEY (idstatus);
ALTER TABLE ONLY data.translation
    ADD CONSTRAINT pk_translation PRIMARY KEY (identity);
ALTER TABLE ONLY data.translation_of
    ADD CONSTRAINT pk_translation_of PRIMARY KEY (idtranslation, iddocument);
ALTER TABLE ONLY data.transliterationsystem
    ADD CONSTRAINT pk_transliterationsystem PRIMARY KEY (idtransliterationsystem);
ALTER TABLE ONLY data.role
    ADD CONSTRAINT role_system_name_key UNIQUE (system_name);
ALTER TABLE ONLY data.person_email
    ADD CONSTRAINT unq_email UNIQUE (email);
ALTER TABLE ONLY logic.feedback
    ADD CONSTRAINT feedback_pkey PRIMARY KEY (id);
ALTER TABLE ONLY logic.fos_user
    ADD CONSTRAINT fos_user_pkey PRIMARY KEY (id);
ALTER TABLE ONLY logic.contributor_of
    ADD CONSTRAINT pk_contributor_of PRIMARY KEY (iduser, iddocument);
ALTER TABLE ONLY logic.news_event
    ADD CONSTRAINT pk_news_event PRIMARY KEY (id);
ALTER TABLE ONLY logic.page
    ADD CONSTRAINT pk_page PRIMARY KEY (id);
ALTER TABLE ONLY logic.revision
    ADD CONSTRAINT pk_revision PRIMARY KEY (idrevision);
ALTER TABLE ONLY logic.user_old
    ADD CONSTRAINT pk_user PRIMARY KEY (identity);
ALTER TABLE ONLY logic.working_on
    ADD CONSTRAINT pk_working_on PRIMARY KEY (iduser, iddocument);
ALTER TABLE ONLY logic."user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (id);

CREATE INDEX fki_factoid_factoid_type_new ON data.factoid USING btree (idfactoid_type);
CREATE UNIQUE INDEX uniq_a9f818c092fc23a8 ON logic.fos_user USING btree (username_canonical);
CREATE UNIQUE INDEX uniq_a9f818c0a0d96fbf ON logic.fos_user USING btree (email_canonical);
CREATE UNIQUE INDEX uniq_a9f818c0c05fb297 ON logic.fos_user USING btree (confirmation_token);
CREATE TRIGGER delete_entity_instead_of_bib_varia AFTER DELETE ON data.bib_varia FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_blog AFTER DELETE ON data.blog FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_blog_post AFTER DELETE ON data.blog_post FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_book AFTER DELETE ON data.book FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_book_cluster AFTER DELETE ON data.book_cluster FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_book_series AFTER DELETE ON data.book_series FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_bookchapter AFTER DELETE ON data.bookchapter FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_document AFTER DELETE ON data.document FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_document AFTER DELETE ON data.library FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_institution AFTER DELETE ON data.institution FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_journal AFTER DELETE ON data.journal FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_journal_issue AFTER DELETE ON data.journal_issue FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_manuscript AFTER DELETE ON data.manuscript FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_monastery AFTER DELETE ON data.monastery FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_node AFTER DELETE ON data.node FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_person AFTER DELETE ON data.person FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_phd AFTER DELETE ON data.phd FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_reconstructed_poem AFTER DELETE ON data.reconstructed_poem FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER delete_entity_instead_of_region AFTER DELETE ON data.region FOR EACH ROW EXECUTE FUNCTION data.delete_entity();
CREATE TRIGGER ensure_article_has_document BEFORE INSERT ON data.article FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_bib_varia_has_document BEFORE INSERT ON data.bib_varia FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_blog_has_document BEFORE INSERT ON data.blog FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_blog_post_has_document BEFORE INSERT ON data.blog_post FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_book_cluster_has_document BEFORE INSERT ON data.book_cluster FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_book_has_document BEFORE INSERT ON data.book FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_book_series_has_document BEFORE INSERT ON data.book_series FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_bookchapter_has_document BEFORE INSERT ON data.bookchapter FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_document_has_identity BEFORE INSERT ON data.document FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();
CREATE TRIGGER ensure_fund_has_location AFTER INSERT ON data.fund FOR EACH ROW EXECUTE FUNCTION data.ensure_fund_has_location();
CREATE TRIGGER ensure_institution_has_identity BEFORE INSERT ON data.institution FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();
CREATE TRIGGER ensure_institution_has_location AFTER INSERT ON data.institution FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_has_location();
CREATE TRIGGER ensure_journal_has_document BEFORE INSERT ON data.journal FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_journal_issue_has_document BEFORE INSERT ON data.journal_issue FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_keyword_has_identity BEFORE INSERT ON data.keyword FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();
CREATE TRIGGER ensure_library_has_institution BEFORE INSERT ON data.library FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_presence();
CREATE TRIGGER ensure_manuscript_has_document BEFORE INSERT ON data.manuscript FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_monastery_has_institution BEFORE INSERT ON data.monastery FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_presence();
CREATE TRIGGER ensure_node_has_identity BEFORE INSERT ON data.node FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();
CREATE TRIGGER ensure_online_source_has_institution BEFORE INSERT ON data.online_source FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_presence();
CREATE TRIGGER ensure_original_poem_has_identity BEFORE INSERT ON data.original_poem FOR EACH ROW EXECUTE FUNCTION data.ensure_poem_presence();
CREATE TRIGGER ensure_person_has_identity BEFORE INSERT ON data.person FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();
CREATE TRIGGER ensure_phd_has_document BEFORE INSERT ON data.phd FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_poem_has_identity BEFORE INSERT ON data.poem FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
CREATE TRIGGER ensure_reconstructed_poem_has_identity BEFORE INSERT ON data.reconstructed_poem FOR EACH ROW EXECUTE FUNCTION data.ensure_poem_presence();
CREATE TRIGGER ensure_region_has_identity BEFORE INSERT ON data.region FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();
CREATE TRIGGER ensure_region_has_location AFTER INSERT ON data.region FOR EACH ROW EXECUTE FUNCTION data.ensure_region_has_location();
CREATE TRIGGER ensure_translation_has_document BEFORE INSERT ON data.translation FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();
ALTER TABLE ONLY data.article
    ADD CONSTRAINT fk_article_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.bib_varia
    ADD CONSTRAINT fk_bib_varia_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.bibrole
    ADD CONSTRAINT fk_bibrole_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.bibrole
    ADD CONSTRAINT fk_bibrole_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.bibrole
    ADD CONSTRAINT fk_bibrole_role FOREIGN KEY (idrole) REFERENCES data.role(idrole) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.blog
    ADD CONSTRAINT fk_blog_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.blog_post
    ADD CONSTRAINT fk_blog_post_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.book
    ADD CONSTRAINT fk_book_book_cluster FOREIGN KEY (idcluster) REFERENCES data.book_cluster(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.book
    ADD CONSTRAINT fk_book_book_series FOREIGN KEY (idseries) REFERENCES data.book_series(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.book_cluster
    ADD CONSTRAINT fk_book_cluster_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.book
    ADD CONSTRAINT fk_book_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.book_series
    ADD CONSTRAINT fk_book_series_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.bookchapter
    ADD CONSTRAINT fk_bookchapter_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_acknowledgement
    ADD CONSTRAINT fk_document_acknowledgement_acknowledgement FOREIGN KEY (idacknowledgement) REFERENCES data.acknowledgement(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.document_acknowledgement
    ADD CONSTRAINT fk_document_acknowledgement_document FOREIGN KEY (iddocument) REFERENCES data.entity(identity);
ALTER TABLE ONLY data.document_contains
    ADD CONSTRAINT fk_document_contains_container FOREIGN KEY (idcontainer) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_contains
    ADD CONSTRAINT fk_document_contains_content FOREIGN KEY (idcontent) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document
    ADD CONSTRAINT fk_document_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_genre
    ADD CONSTRAINT fk_document_genre_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_genre
    ADD CONSTRAINT fk_document_genre_genre FOREIGN KEY (idgenre) REFERENCES data.genre(idgenre) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_group
    ADD CONSTRAINT fk_document_group_document FOREIGN KEY (iddocument) REFERENCES data.document(identity);
ALTER TABLE ONLY data.document_group
    ADD CONSTRAINT fk_document_group_group FOREIGN KEY (idgroup) REFERENCES data.node(identity);
ALTER TABLE ONLY data.document_image
    ADD CONSTRAINT fk_document_image_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_image
    ADD CONSTRAINT fk_document_image_image FOREIGN KEY (idimage) REFERENCES data.image(idimage) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_keyword
    ADD CONSTRAINT fk_document_keyword_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_keyword
    ADD CONSTRAINT fk_document_keyword_keyword FOREIGN KEY (idkeyword) REFERENCES data.keyword(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_status
    ADD CONSTRAINT fk_document_status_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_status
    ADD CONSTRAINT fk_document_status_status FOREIGN KEY (idstatus) REFERENCES data.status(idstatus) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_title
    ADD CONSTRAINT fk_document_title_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.document_title
    ADD CONSTRAINT fk_document_title_language FOREIGN KEY (idlanguage) REFERENCES data.language(idlanguage);
ALTER TABLE ONLY data.entity_management
    ADD CONSTRAINT fk_entity_management_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.entity_management
    ADD CONSTRAINT fk_entity_management_management FOREIGN KEY (idmanagement) REFERENCES data.management(id) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.entity_url
    ADD CONSTRAINT fk_entity_url_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.evidence_factoid
    ADD CONSTRAINT fk_evidence_factoid_evidence FOREIGN KEY (idevidence) REFERENCES data.evidence(idevidence);
ALTER TABLE ONLY data.evidence_factoid
    ADD CONSTRAINT fk_evidence_factoid_factoid FOREIGN KEY (idfactoid) REFERENCES data.factoid(idfactoid);
ALTER TABLE ONLY data.evidence
    ADD CONSTRAINT fk_evidence_reference FOREIGN KEY (idreference) REFERENCES data.reference(idreference);
ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_factoid_type_new FOREIGN KEY (idfactoid_type) REFERENCES data.factoid_type(idfactoid_type);
ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_location FOREIGN KEY (idlocation) REFERENCES data.location(idlocation);
ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_object FOREIGN KEY (object_identity) REFERENCES data.entity(identity);
ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_subject FOREIGN KEY (subject_identity) REFERENCES data.entity(identity);
ALTER TABLE ONLY data.factoid_type
    ADD CONSTRAINT fk_factoid_type_inverse FOREIGN KEY (idinverse) REFERENCES data.factoid_type(idfactoid_type) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.fund
    ADD CONSTRAINT fk_fund_library FOREIGN KEY (idlibrary) REFERENCES data.library(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.genre
    ADD CONSTRAINT fk_genre_genre FOREIGN KEY (idparentgenre) REFERENCES data.genre(idgenre) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.genre
    ADD CONSTRAINT fk_genre_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.global_id
    ADD CONSTRAINT fk_global_id_authority FOREIGN KEY (idauthority) REFERENCES data.entity(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.global_id
    ADD CONSTRAINT fk_global_id_subject FOREIGN KEY (idsubject) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.institution
    ADD CONSTRAINT fk_institution_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.journal
    ADD CONSTRAINT fk_journal_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.journal_issue
    ADD CONSTRAINT fk_journal_issue_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.journal_issue
    ADD CONSTRAINT fk_journal_journal_issue FOREIGN KEY (idjournal) REFERENCES data.journal(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.keyword
    ADD CONSTRAINT fk_keyword_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.reconstructed_poem_lemma
    ADD CONSTRAINT fk_lemma_reconstructed_poem FOREIGN KEY (id_reconstructed_poem) REFERENCES data.reconstructed_poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.library
    ADD CONSTRAINT fk_library_institution FOREIGN KEY (identity) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.located_at
    ADD CONSTRAINT fk_located_at_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.located_at
    ADD CONSTRAINT fk_located_at_location FOREIGN KEY (idlocation) REFERENCES data.location(idlocation) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.location
    ADD CONSTRAINT fk_location_fund FOREIGN KEY (idfund) REFERENCES data.fund(idfund) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.location
    ADD CONSTRAINT fk_location_institution FOREIGN KEY (idinstitution) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.location
    ADD CONSTRAINT fk_location_region FOREIGN KEY (idregion) REFERENCES data.region(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.manuscript
    ADD CONSTRAINT fk_manuscript_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.monastery
    ADD CONSTRAINT fk_monastery_institution FOREIGN KEY (identity) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.name
    ADD CONSTRAINT fk_name_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.name
    ADD CONSTRAINT fk_name_transliterationsystem FOREIGN KEY (idtransliterationsystem) REFERENCES data.transliterationsystem(idtransliterationsystem);
ALTER TABLE ONLY data.node
    ADD CONSTRAINT fk_node_document FOREIGN KEY (iddocument) REFERENCES data.document(identity);
ALTER TABLE ONLY data.node
    ADD CONSTRAINT fk_node_parent_node FOREIGN KEY (idparentnode) REFERENCES data.node(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.occupation
    ADD CONSTRAINT fk_occupation_region FOREIGN KEY (idregion) REFERENCES data.region(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.online_source
    ADD CONSTRAINT fk_online_source_institution FOREIGN KEY (identity) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.original_poem
    ADD CONSTRAINT fk_original__poem_document FOREIGN KEY (identity) REFERENCES data.poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.original_poem_verse
    ADD CONSTRAINT fk_original_poem_verse_original_poem FOREIGN KEY (idoriginal_poem) REFERENCES data.original_poem(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.person_email
    ADD CONSTRAINT fk_person_email_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.person
    ADD CONSTRAINT fk_person_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.person_occupation
    ADD CONSTRAINT fk_person_occupation_occupation FOREIGN KEY (idoccupation) REFERENCES data.occupation(idoccupation) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.person_occupation
    ADD CONSTRAINT fk_person_occupation_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.person_self_designation
    ADD CONSTRAINT fk_person_self_designation_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.person_self_designation
    ADD CONSTRAINT fk_person_self_designation_self_designation FOREIGN KEY (idself_designation) REFERENCES data.self_designation(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.phd
    ADD CONSTRAINT fk_phd_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.poem
    ADD CONSTRAINT fk_poem_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.poem_meter
    ADD CONSTRAINT fk_poem_meter_meter FOREIGN KEY (idmeter) REFERENCES data.meter(idmeter) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.poem_meter
    ADD CONSTRAINT fk_poem_meter_poem FOREIGN KEY (idpoem) REFERENCES data.poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.reconstructed_poem
    ADD CONSTRAINT fk_reconstructed_poem_document FOREIGN KEY (identity) REFERENCES data.poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.reference
    ADD CONSTRAINT fk_reference_reference_type FOREIGN KEY (idreference_type) REFERENCES data.reference_type(idreference_type) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.reference
    ADD CONSTRAINT fk_reference_source FOREIGN KEY (idsource) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.reference
    ADD CONSTRAINT fk_reference_target FOREIGN KEY (idtarget) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.region
    ADD CONSTRAINT fk_region_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.translation
    ADD CONSTRAINT fk_translation_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.translation
    ADD CONSTRAINT fk_translation_language FOREIGN KEY (idlanguage) REFERENCES data.language(idlanguage) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY data.translation_of
    ADD CONSTRAINT fk_translation_of_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY data.translation_of
    ADD CONSTRAINT fk_translation_of_translation FOREIGN KEY (idtranslation) REFERENCES data.translation(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY logic.contributor_of
    ADD CONSTRAINT fk_contributor_of_user FOREIGN KEY (iduser) REFERENCES logic.user_old(identity) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE ONLY logic.revision_old
    ADD CONSTRAINT fk_revision_fos_user FOREIGN KEY (iduser) REFERENCES logic.fos_user(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE ONLY logic.working_on
    ADD CONSTRAINT fk_working_on_user FOREIGN KEY (iduser) REFERENCES logic.user_old(identity) ON UPDATE CASCADE ON DELETE CASCADE;

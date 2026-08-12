--
-- PostgreSQL database dump
--

-- Dumped from database version 16.1 (Debian 16.1-1.pgdg120+1)
-- Dumped by pg_dump version 16.1 (Debian 16.1-1.pgdg120+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: data; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA data;


--
-- Name: julie; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA julie;


--
-- Name: SCHEMA julie; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA julie IS 'Contains a LIVE database. Is used by julie for substring annotations.';


--
-- Name: julie_before_2019_12_11; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA julie_before_2019_12_11;


--
-- Name: logic; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA logic;


--
-- Name: migration; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA migration;


--
-- Name: fuzzydate; Type: TYPE; Schema: data; Owner: -
--

CREATE TYPE data.fuzzydate AS (
	floor date,
	ceiling date
);


--
-- Name: fuzzyinterval; Type: TYPE; Schema: data; Owner: -
--

CREATE TYPE data.fuzzyinterval AS (
	start_floor date,
	start_ceiling date,
	end_floor date,
	end_ceiling date
);


--
-- Name: delete_entity(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.delete_entity() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- delete the entity if identity is set

	IF OLD.identity IS NOT NULL THEN

		DELETE FROM entity WHERE identity = OLD.identity;

	ELSE

		-- this function could not properly function, probably something wrong with the db logic.

		RAISE EXCEPTION 'identity field not set in row to be deleted, could not delete entity.';

	END IF;

	-- it's a delete, just return null

	RETURN NULL;

END;$$;


--
-- Name: ensure_document_presence(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_document_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- insert a new document if the current row does not contain an identity yet

	IF NEW.identity IS NULL THEN

		INSERT INTO document DEFAULT values returning identity into NEW.identity;

	END IF;

	-- proceed with the NEW value...

	RETURN NEW;

END;$$;


--
-- Name: ensure_entity_presence(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_entity_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$DECLARE 

	resultid integer;

BEGIN

	-- insert a new entity if the current row does not contain an identity yet

	IF NEW.identity IS NULL THEN

		INSERT INTO entity DEFAULT VALUES returning identity into NEW.identity;

	END IF;

	-- proceed with the NEW value...

	RETURN NEW;

END;$$;


--
-- Name: ensure_fund_has_location(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_fund_has_location() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- add the new fund's ID to the location table!

	IF NEW.idfund IS NOT NULL THEN

		INSERT INTO location (idfund) values (NEW.idfund);

		-- proceed with the NEW value...

		RETURN NEW;

	ELSE

		RAISE EXCEPTION 'Could not add entry to location, no idfund field set.';

	END IF;

END;$$;


--
-- Name: ensure_institution_has_location(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_institution_has_location() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- add the new institution's ID to the location table!

	IF NEW.identity IS NOT NULL THEN

		INSERT INTO location (idinstitution) values (NEW.identity);

		-- proceed with the NEW value...

		RETURN NEW;

	ELSE

		RAISE EXCEPTION 'Could not add entry to location, no identity field set.';

	END IF;

END;$$;


--
-- Name: ensure_institution_presence(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_institution_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- insert a new institution if the current row does not contain an identity yet

	IF NEW.identity IS NULL THEN

		INSERT INTO institution DEFAULT values returning identity into NEW.identity;

	END IF;

	-- proceed with the NEW value...

	RETURN NEW;

END;$$;


--
-- Name: ensure_person_presence(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_person_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- insert a new document if the current row does not contain an identity yet

	IF NEW.identity IS NULL THEN

		INSERT INTO person DEFAULT values returning identity into NEW.identity;

	END IF;

	-- proceed with the NEW value...

	RETURN NEW;

END;$$;


--
-- Name: ensure_poem_presence(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_poem_presence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- insert a new document if the current row does not contain an identity yet

	IF NEW.identity IS NULL THEN

		INSERT INTO poem DEFAULT values returning identity into NEW.identity;

	END IF;

	-- proceed with the NEW value...

	RETURN NEW;

END;$$;


--
-- Name: ensure_region_has_location(); Type: FUNCTION; Schema: data; Owner: -
--

CREATE FUNCTION data.ensure_region_has_location() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

	-- add the new region's ID to the location table!

	IF NEW.identity IS NOT NULL THEN

		INSERT INTO location (idregion) values (NEW.identity);

		-- proceed with the NEW value...

		RETURN NEW;

	ELSE

		RAISE EXCEPTION 'Could not add entry to location, no identity field set.';

	END IF;

END;$$;


--
-- Name: is_roman_numeral(character varying); Type: FUNCTION; Schema: data; Owner: -
--

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


--
-- Name: roman_numeral_to_integer(character varying); Type: FUNCTION; Schema: data; Owner: -
--

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

	-- raise notice 'res %', res;

	result := 1000*length(res[1]);

	IF length(res[2])=0 THEN

		-- do nothing

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

		-- do nothing

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

		--do nothing

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


--
-- Name: some_func(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.some_func() RETURNS void
    LANGUAGE plpgsql
    AS $$

DECLARE
    approw record;
    journalissueid integer;
    journalid integer;
BEGIN
    set search_path = 'data';


    IF NOT EXISTS (
        SELECT 1 FROM pg_catalog.pg_tables
        WHERE  schemaname = 'data'
        AND    tablename  = 'journal_issue'
    )
    THEN

        -- Rename journal to journal_issue
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

        -- Create new table journal
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

        -- Add link between journal_issue and journal
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
            -- check if journal exists
            select identity into journalid from data.journal inner join data.document_title on identity = iddocument where
                ((title is null and approw.title is null) or (title = approw.title));
            IF journalid is null THEN
                insert into data.journal DEFAULT VALUES returning identity into journalid;
                insert into data.document_title (iddocument, idlanguage, title) values (journalid, (select idlanguage from language where code = '?'), approw.title);
            END IF;
            insert into data.journal_issue (volume, number, year, idjournal) values (approw.volume, approw.number, approw.year, journalid) returning identity into journalissueid;
        END IF;
        -- we have a journal issue, now link it up to the article
        update data.document_contains set idcontainer = journalissueid where idcontent = approw.idcontent;
    END LOOP;
END;

$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: acknowledgement; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.acknowledgement (
    id integer NOT NULL,
    acknowledgement character varying NOT NULL
);


--
-- Name: acknowledgement_expression; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.acknowledgement_expression (
    expression_text text NOT NULL
);


--
-- Name: acknowledgement_id_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.acknowledgement_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: acknowledgement_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.acknowledgement_id_seq OWNED BY data.acknowledgement.id;


--
-- Name: article; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.article (
    identity integer NOT NULL
);


--
-- Name: TABLE article; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.article IS 'Subclass of Entity';


--
-- Name: bib_varia; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.bib_varia (
    identity integer NOT NULL,
    city character varying,
    year integer,
    institution character varying
);


--
-- Name: bibrole; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.bibrole (
    idperson integer NOT NULL,
    iddocument integer NOT NULL,
    rank integer,
    created timestamp with time zone DEFAULT now() NOT NULL,
    idrole integer NOT NULL
);


--
-- Name: blog; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.blog (
    identity integer NOT NULL,
    url character varying,
    last_accessed timestamp with time zone
);


--
-- Name: blog_post; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.blog_post (
    identity integer NOT NULL,
    url character varying,
    post_date timestamp with time zone
);


--
-- Name: book; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: TABLE book; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.book IS 'Subclass of Document';


--
-- Name: book_cluster; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.book_cluster (
    identity integer NOT NULL
);


--
-- Name: book_series; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.book_series (
    identity integer NOT NULL
);


--
-- Name: bookchapter; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.bookchapter (
    identity integer NOT NULL
);


--
-- Name: TABLE bookchapter; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.bookchapter IS 'Subclass of Document';


--
-- Name: document; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document (
    identity integer NOT NULL,
    text_content text,
    is_illustrated boolean
);


--
-- Name: COLUMN document.identity; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON COLUMN data.document.identity IS 'refers to entity.identity';


--
-- Name: document_acknowledgement; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_acknowledgement (
    iddocument integer NOT NULL,
    idacknowledgement integer
);


--
-- Name: document_contains; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: document_contains_iddocumentcontains_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.document_contains_iddocumentcontains_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_contains_iddocumentcontains_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.document_contains_iddocumentcontains_seq OWNED BY data.document_contains.iddocumentcontains;


--
-- Name: document_genre; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_genre (
    iddocument integer NOT NULL,
    idgenre integer NOT NULL
);


--
-- Name: document_group; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_group (
    iddocument integer NOT NULL,
    idgroup integer NOT NULL
);


--
-- Name: document_image; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_image (
    iddocument integer NOT NULL,
    idimage integer NOT NULL
);


--
-- Name: document_keyword; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_keyword (
    iddocument integer NOT NULL,
    idkeyword integer NOT NULL
);


--
-- Name: document_status; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_status (
    iddocument integer NOT NULL,
    idstatus integer NOT NULL
);


--
-- Name: document_title; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.document_title (
    iddocument integer NOT NULL,
    idlanguage integer NOT NULL,
    title character varying NOT NULL
);


--
-- Name: entity; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: COLUMN entity.checked; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON COLUMN data.entity.checked IS 'this field originates from the old database: biblio_objects.checked

can now be used for everything, simple flag to indicate it has been checked by someone';


--
-- Name: entity_identity_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.entity_identity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entity_identity_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.entity_identity_seq OWNED BY data.entity.identity;


--
-- Name: entity_management; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.entity_management (
    identity integer NOT NULL,
    idmanagement integer NOT NULL
);


--
-- Name: entity_url; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.entity_url (
    idurl integer NOT NULL,
    identity integer NOT NULL,
    title character varying,
    url character varying NOT NULL,
    "order" integer
);


--
-- Name: entity_url_idurl_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.entity_url_idurl_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entity_url_idurl_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.entity_url_idurl_seq OWNED BY data.entity_url.idurl;


--
-- Name: evidence; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.evidence (
    idevidence integer NOT NULL,
    idreference integer,
    idperson integer,
    date data.fuzzydate
);


--
-- Name: evidence_factoid; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.evidence_factoid (
    idevidence integer NOT NULL,
    idfactoid integer NOT NULL
);


--
-- Name: evidence_idevidence_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.evidence_idevidence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: evidence_idevidence_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.evidence_idevidence_seq OWNED BY data.evidence.idevidence;


--
-- Name: factoid; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: factoid_backup_26082025; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: factoid_idfactoid_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.factoid_idfactoid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: factoid_idfactoid_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.factoid_idfactoid_seq OWNED BY data.factoid.idfactoid;


--
-- Name: factoid_type; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.factoid_type (
    description character varying,
    "group" character varying,
    idfactoid_type integer NOT NULL,
    type character varying NOT NULL,
    idinverse integer
);


--
-- Name: factoid_type_idfactoid_type_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.factoid_type_idfactoid_type_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: factoid_type_idfactoid_type_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.factoid_type_idfactoid_type_seq OWNED BY data.factoid_type.idfactoid_type;


--
-- Name: fund; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.fund (
    idfund integer NOT NULL,
    idlibrary integer,
    name character varying,
    created timestamp with time zone,
    modified timestamp with time zone
);


--
-- Name: fund_idfund_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.fund_idfund_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fund_idfund_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.fund_idfund_seq OWNED BY data.fund.idfund;


--
-- Name: genre; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.genre (
    idgenre integer NOT NULL,
    idparentgenre integer,
    genre character varying,
    description character varying,
    is_content boolean,
    idperson integer
);


--
-- Name: genre_idgenre_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.genre_idgenre_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: genre_idgenre_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.genre_idgenre_seq OWNED BY data.genre.idgenre;


--
-- Name: global_id; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.global_id (
    idauthority integer NOT NULL,
    idsubject integer NOT NULL,
    identifier character varying NOT NULL,
    extra character varying,
    volume integer
);


--
-- Name: TABLE global_id; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.global_id IS 'This allows an entity (the authority) to link to another identity (the subject) using an identifier.';


--
-- Name: node; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.node (
    identity integer NOT NULL,
    idparentnode integer,
    iddocument integer
);


--
-- Name: TABLE node; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.node IS 'node is used to logically group documents into a tree';


--
-- Name: COLUMN node.iddocument; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON COLUMN data.node.iddocument IS 'This is optionally set. When set, this node is actually a document... if it is not set, the node functions as a pure node';


--
-- Name: group_identity_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.group_identity_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: group_identity_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.group_identity_seq OWNED BY data.node.identity;


--
-- Name: identifier; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: identifier_ididentifier_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.identifier_ididentifier_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: identifier_ididentifier_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.identifier_ididentifier_seq OWNED BY data.identifier.ididentifier;


--
-- Name: image; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.image (
    idimage integer NOT NULL,
    url character varying,
    is_private boolean DEFAULT false NOT NULL,
    filename character varying
);


--
-- Name: image_idimage_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.image_idimage_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: image_idimage_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.image_idimage_seq OWNED BY data.image.idimage;


--
-- Name: institution; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.institution (
    identity integer NOT NULL,
    idregion integer,
    name text,
    name_abbreviated character varying
);


--
-- Name: journal; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.journal (
    identity integer NOT NULL
);


--
-- Name: journal_issue; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: keyword; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.keyword (
    identity integer NOT NULL,
    keyword character varying,
    is_subject boolean
);


--
-- Name: language; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.language (
    idlanguage integer NOT NULL,
    name character varying NOT NULL,
    code character varying,
    description character varying
);


--
-- Name: language_idlanguage_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.language_idlanguage_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: language_idlanguage_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.language_idlanguage_seq OWNED BY data.language.idlanguage;


--
-- Name: lemma_cache; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.lemma_cache (
    input character varying NOT NULL,
    output character varying
);


--
-- Name: library; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.library (
    identity integer NOT NULL
);


--
-- Name: located_at; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.located_at (
    iddocument integer NOT NULL,
    idlocation integer NOT NULL,
    identification character varying,
    "interval" data.fuzzyinterval,
    extra character varying
);


--
-- Name: location; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: location_idlocation_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.location_idlocation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: location_idlocation_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.location_idlocation_seq OWNED BY data.location.idlocation;


--
-- Name: management; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.management (
    id integer NOT NULL,
    name character varying NOT NULL
);


--
-- Name: management_id_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.management_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: management_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.management_id_seq OWNED BY data.management.id;


--
-- Name: manuscript; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.manuscript (
    identity integer NOT NULL
);


--
-- Name: meter; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.meter (
    idmeter integer NOT NULL,
    name character varying NOT NULL
);


--
-- Name: meter_idmeter_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.meter_idmeter_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meter_idmeter_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.meter_idmeter_seq OWNED BY data.meter.idmeter;


--
-- Name: monastery; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.monastery (
    identity integer NOT NULL
);


--
-- Name: name; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: name_idname_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.name_idname_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: name_idname_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.name_idname_seq OWNED BY data.name.idname;


--
-- Name: occupation; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.occupation (
    idoccupation integer NOT NULL,
    occupation character varying NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    modified timestamp with time zone DEFAULT now() NOT NULL,
    idparentoccupation integer,
    idregion integer
);


--
-- Name: occupation_idoccupation_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.occupation_idoccupation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occupation_idoccupation_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.occupation_idoccupation_seq OWNED BY data.occupation.idoccupation;


--
-- Name: online_source; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.online_source (
    identity integer NOT NULL,
    url character varying,
    last_accessed timestamp with time zone
);


--
-- Name: original_poem; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.original_poem (
    identity integer NOT NULL,
    paleographical_info character varying,
    transcription_reviewed boolean
);


--
-- Name: TABLE original_poem; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.original_poem IS 'Subclass of Poem';


--
-- Name: original_poem_verse; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.original_poem_verse (
    id integer NOT NULL,
    idoriginal_poem integer NOT NULL,
    idgroup integer,
    verse character varying NOT NULL,
    "order" integer NOT NULL
);


--
-- Name: original_poem_verse_id_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.original_poem_verse_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: original_poem_verse_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.original_poem_verse_id_seq OWNED BY data.original_poem_verse.id;


--
-- Name: person; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.person (
    identity integer NOT NULL,
    email character varying,
    is_historical boolean,
    is_modern boolean,
    is_dbbe boolean
);


--
-- Name: TABLE person; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.person IS 'email needs to be moved to person_email';


--
-- Name: person_acknowledgement; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.person_acknowledgement (
    idperson integer NOT NULL,
    idacknowledgement integer NOT NULL
);


--
-- Name: person_email; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.person_email (
    idperson integer NOT NULL,
    email character varying NOT NULL
);


--
-- Name: person_occupation; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.person_occupation (
    idperson integer NOT NULL,
    idoccupation integer NOT NULL
);


--
-- Name: person_self_designation; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.person_self_designation (
    idperson integer NOT NULL,
    idself_designation integer NOT NULL
);


--
-- Name: phd; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.phd (
    identity integer NOT NULL,
    city character varying,
    year integer,
    institution character varying,
    volume character varying,
    forthcoming boolean DEFAULT false NOT NULL
);


--
-- Name: poem; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.poem (
    identity integer NOT NULL,
    verses integer,
    incipit character varying
);


--
-- Name: TABLE poem; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.poem IS 'Subclass of Document';


--
-- Name: poem_meter; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.poem_meter (
    idpoem integer NOT NULL,
    idmeter integer NOT NULL
);


--
-- Name: reconstructed_poem; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.reconstructed_poem (
    identity integer NOT NULL,
    critical_apparatus character varying
);


--
-- Name: TABLE reconstructed_poem; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON TABLE data.reconstructed_poem IS 'Subclass of Poem';


--
-- Name: reconstructed_poem_lemma; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.reconstructed_poem_lemma (
    id integer NOT NULL,
    id_reconstructed_poem integer NOT NULL,
    lemma character varying
);


--
-- Name: reconstructed_poem_lemma_id_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.reconstructed_poem_lemma_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reconstructed_poem_lemma_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.reconstructed_poem_lemma_id_seq OWNED BY data.reconstructed_poem_lemma.id;


--
-- Name: reference; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: COLUMN reference.source_remark; Type: COMMENT; Schema: data; Owner: -
--

COMMENT ON COLUMN data.reference.source_remark IS 'This remark was made by the source of the reference. Do not confuse with internal comments!';


--
-- Name: reference_idreference_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.reference_idreference_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reference_idreference_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.reference_idreference_seq OWNED BY data.reference.idreference;


--
-- Name: reference_type; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.reference_type (
    idreference_type integer NOT NULL,
    type character varying NOT NULL
);


--
-- Name: reference_type_idreference_type_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.reference_type_idreference_type_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reference_type_idreference_type_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.reference_type_idreference_type_seq OWNED BY data.reference_type.idreference_type;


--
-- Name: region; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.region (
    identity integer NOT NULL,
    parent_idregion integer,
    name text,
    historical_name text,
    is_city boolean
);


--
-- Name: role; Type: TABLE; Schema: data; Owner: -
--

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


--
-- Name: role_idrole_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.role_idrole_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_idrole_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.role_idrole_seq OWNED BY data.role.idrole;


--
-- Name: self_designation; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.self_designation (
    id integer NOT NULL,
    name character varying NOT NULL
);


--
-- Name: self_designation_id_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.self_designation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: self_designation_id_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.self_designation_id_seq OWNED BY data.self_designation.id;


--
-- Name: status; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.status (
    idstatus integer NOT NULL,
    status character varying NOT NULL,
    type character varying NOT NULL
);


--
-- Name: status_idstatus_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.status_idstatus_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: status_idstatus_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.status_idstatus_seq OWNED BY data.status.idstatus;


--
-- Name: translation; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.translation (
    identity integer NOT NULL,
    idlanguage integer
);


--
-- Name: translation_of; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.translation_of (
    idtranslation integer NOT NULL,
    iddocument integer NOT NULL
);


--
-- Name: transliterationsystem; Type: TABLE; Schema: data; Owner: -
--

CREATE TABLE data.transliterationsystem (
    idtransliterationsystem integer NOT NULL,
    name character varying
);


--
-- Name: transliterationsystem_idtransliterationsystem_seq; Type: SEQUENCE; Schema: data; Owner: -
--

CREATE SEQUENCE data.transliterationsystem_idtransliterationsystem_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transliterationsystem_idtransliterationsystem_seq; Type: SEQUENCE OWNED BY; Schema: data; Owner: -
--

ALTER SEQUENCE data.transliterationsystem_idtransliterationsystem_seq OWNED BY data.transliterationsystem.idtransliterationsystem;


--
-- Name: substringannotation; Type: TABLE; Schema: julie; Owner: -
--

CREATE TABLE julie.substringannotation (
    idoccurrence integer NOT NULL,
    startindex integer,
    endindex integer,
    "substring" character varying,
    idsubstringannotation integer NOT NULL,
    key character varying,
    value character varying,
    old_idoccurrence integer
);


--
-- Name: annotation_idannotation_seq; Type: SEQUENCE; Schema: julie; Owner: -
--

CREATE SEQUENCE julie.annotation_idannotation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: annotation_idannotation_seq; Type: SEQUENCE OWNED BY; Schema: julie; Owner: -
--

ALTER SEQUENCE julie.annotation_idannotation_seq OWNED BY julie.substringannotation.idsubstringannotation;


--
-- Name: poemannotation; Type: TABLE; Schema: julie; Owner: -
--

CREATE TABLE julie.poemannotation (
    idoccurrence integer NOT NULL,
    prosodycorrect boolean,
    old_idoccurrence integer
);


--
-- Name: substringannotation; Type: TABLE; Schema: julie_before_2019_12_11; Owner: -
--

CREATE TABLE julie_before_2019_12_11.substringannotation (
    idoccurrence integer NOT NULL,
    startindex integer,
    endindex integer,
    "substring" character varying,
    idsubstringannotation integer NOT NULL,
    key character varying,
    value character varying,
    old_idoccurrence integer
);


--
-- Name: annotation_idannotation_seq; Type: SEQUENCE; Schema: julie_before_2019_12_11; Owner: -
--

CREATE SEQUENCE julie_before_2019_12_11.annotation_idannotation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: annotation_idannotation_seq; Type: SEQUENCE OWNED BY; Schema: julie_before_2019_12_11; Owner: -
--

ALTER SEQUENCE julie_before_2019_12_11.annotation_idannotation_seq OWNED BY julie_before_2019_12_11.substringannotation.idsubstringannotation;


--
-- Name: poemannotation; Type: TABLE; Schema: julie_before_2019_12_11; Owner: -
--

CREATE TABLE julie_before_2019_12_11.poemannotation (
    idoccurrence integer NOT NULL,
    prosodycorrect boolean,
    old_idoccurrence integer
);


--
-- Name: contributor_of; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.contributor_of (
    iduser integer NOT NULL,
    iddocument integer NOT NULL
);


--
-- Name: feedback; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.feedback (
    id integer NOT NULL,
    url character varying(4000) NOT NULL,
    email character varying(4000) NOT NULL,
    message character varying(4000) NOT NULL,
    created timestamp(0) without time zone DEFAULT now() NOT NULL,
    status character varying(40) DEFAULT 'new'::character varying NOT NULL
);


--
-- Name: feedback_id_seq; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.feedback_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feedback_id_seq; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.feedback_id_seq OWNED BY logic.feedback.id;


--
-- Name: fos_user; Type: TABLE; Schema: logic; Owner: -
--

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


--
-- Name: COLUMN fos_user.roles; Type: COMMENT; Schema: logic; Owner: -
--

COMMENT ON COLUMN logic.fos_user.roles IS '(DC2Type:array)';


--
-- Name: fos_user_id_seq; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.fos_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: news_event; Type: TABLE; Schema: logic; Owner: -
--

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


--
-- Name: news_event_id_seq; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.news_event_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: news_event_id_seq; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.news_event_id_seq OWNED BY logic.news_event.id;


--
-- Name: page; Type: TABLE; Schema: logic; Owner: -
--

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


--
-- Name: page_id_seq; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.page_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: page_id_seq; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.page_id_seq OWNED BY logic.page.id;


--
-- Name: revision; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.revision (
    idrevision integer NOT NULL,
    type character varying,
    identity integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    old_value character varying,
    new_value character varying,
    user_email character varying(254) NOT NULL
);


--
-- Name: revision_2019_05_15; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.revision_2019_05_15 (
    idrevision integer NOT NULL,
    type character varying,
    identity integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    iduser integer NOT NULL,
    old_value character varying,
    new_value character varying
);


--
-- Name: revision_old; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.revision_old (
    idrevision integer NOT NULL,
    type character varying,
    identity integer NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    iduser integer NOT NULL,
    old_value character varying,
    new_value character varying
);


--
-- Name: revision_idrevision_seq; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.revision_idrevision_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: revision_idrevision_seq; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.revision_idrevision_seq OWNED BY logic.revision_old.idrevision;


--
-- Name: revision_idrevision_seq1; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.revision_idrevision_seq1
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: revision_idrevision_seq1; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.revision_idrevision_seq1 OWNED BY logic.revision_2019_05_15.idrevision;


--
-- Name: revision_idrevision_seq2; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.revision_idrevision_seq2
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: revision_idrevision_seq2; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.revision_idrevision_seq2 OWNED BY logic.revision.idrevision;


--
-- Name: user; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic."user" (
    id integer NOT NULL,
    username character varying(255) NOT NULL,
    roles text NOT NULL,
    created timestamp(0) without time zone NOT NULL,
    modified timestamp(0) without time zone NOT NULL,
    last_login timestamp(0) without time zone DEFAULT NULL::timestamp without time zone
);


--
-- Name: COLUMN "user".roles; Type: COMMENT; Schema: logic; Owner: -
--

COMMENT ON COLUMN logic."user".roles IS '(DC2Type:array)';


--
-- Name: user_id_seq; Type: SEQUENCE; Schema: logic; Owner: -
--

CREATE SEQUENCE logic.user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_id_seq; Type: SEQUENCE OWNED BY; Schema: logic; Owner: -
--

ALTER SEQUENCE logic.user_id_seq OWNED BY logic."user".id;


--
-- Name: user_old; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.user_old (
    identity integer NOT NULL,
    password_hash character varying NOT NULL,
    start_tenure date,
    end_tenure date
);


--
-- Name: working_on; Type: TABLE; Schema: logic; Owner: -
--

CREATE TABLE logic.working_on (
    iduser integer NOT NULL,
    iddocument integer NOT NULL
);


--
-- Name: biblio_article_to_entity; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.biblio_article_to_entity (
    blbiio_article_id integer,
    identity integer
);


--
-- Name: biblio_book_to_entity; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.biblio_book_to_entity (
    biblio_book_id integer,
    identity integer
);


--
-- Name: biblio_contribution_to_entity; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.biblio_contribution_to_entity (
    old_id integer,
    identity integer
);


--
-- Name: biblio_online_source_to_entity; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.biblio_online_source_to_entity (
    old_id integer,
    identity integer
);


--
-- Name: content_to_genre; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.content_to_genre (
    idcontent integer,
    idgenre integer
);


--
-- Name: genres_to_genre; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.genres_to_genre (
    old_id integer,
    idgenre integer
);


--
-- Name: keywords_to_keyword; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.keywords_to_keyword (
    old_id integer,
    identity integer
);


--
-- Name: location_funds_to_fund; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.location_funds_to_fund (
    old_id integer,
    idfund integer
);


--
-- Name: location_library_to_library; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.location_library_to_library (
    library_id integer,
    identity integer
);


--
-- Name: location_places_to_region; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.location_places_to_region (
    place_id integer,
    identity integer
);


--
-- Name: manuscript_statuses_status; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.manuscript_statuses_status (
    old_id integer NOT NULL,
    idstatus integer NOT NULL
);


--
-- Name: manuscripts_to_manuscript; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.manuscripts_to_manuscript (
    old_id integer NOT NULL,
    identity integer NOT NULL
);


--
-- Name: occurrence_text_status_to_status; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.occurrence_text_status_to_status (
    old_id integer,
    idstatus integer
);


--
-- Name: occurrence_to_entity; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.occurrence_to_entity (
    old_id integer,
    identity integer
);


--
-- Name: occurrences_record_statuses_to_status; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.occurrences_record_statuses_to_status (
    old_id integer,
    idstatus integer
);


--
-- Name: origin_location_to_monastery; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.origin_location_to_monastery (
    old_id integer,
    identity integer
);


--
-- Name: origin_region_to_region; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.origin_region_to_region (
    old_id integer,
    identity integer
);


--
-- Name: persons_functions_occupation; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.persons_functions_occupation (
    old_id integer,
    idoccupation integer
);


--
-- Name: persons_to_person; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.persons_to_person (
    person_id integer,
    idperson integer
);


--
-- Name: persons_types; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.persons_types (
    old_id integer,
    idoccupation integer
);


--
-- Name: relationships_description_factoidtypes; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.relationships_description_factoidtypes (
    old_id integer,
    new_id integer
);


--
-- Name: subjects_to_keyword; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.subjects_to_keyword (
    old_id integer,
    identity integer
);


--
-- Name: type_to_reconstructed_poem; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.type_to_reconstructed_poem (
    idtype integer,
    identity integer
);


--
-- Name: types_text_statuses_to_status; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.types_text_statuses_to_status (
    old_id integer,
    idstatus integer
);


--
-- Name: users_to_user; Type: TABLE; Schema: migration; Owner: -
--

CREATE TABLE migration.users_to_user (
    old_id integer,
    iduser integer
);


--
-- Name: audit_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_log (
    entity_type character varying(1000),
    user_id integer,
    created timestamp without time zone,
    old_value text,
    id integer NOT NULL,
    entity_id integer,
    new_value text
);


--
-- Name: audit_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_log_id_seq OWNED BY public.audit_log.id;


--
-- Name: backup_reference_to_remove; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.backup_reference_to_remove (
    idreference integer,
    idsource integer,
    idtarget integer,
    date date,
    url character varying,
    temp_page_removeme character varying,
    page_start character varying,
    page_end character varying,
    temp_page_processed_removeme boolean,
    figure character varying,
    "table" character varying,
    image character varying,
    footnote character varying,
    source_remark character varying,
    private_comment character varying,
    public_comment character varying,
    idreference_type integer
);


--
-- Name: biblio_articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.biblio_articles (
    obj_id integer,
    notes text,
    year smallint,
    created timestamp without time zone,
    author_lastname character varying(255),
    title character varying(500),
    volume integer,
    number integer,
    journal character varying(255),
    pages character varying(255),
    deleted integer,
    coauthor_1 character varying(255),
    coauthor_3 character varying(255),
    coauthor_2 character varying(255),
    author_firstname character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: biblio_articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.biblio_articles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biblio_articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biblio_articles_id_seq OWNED BY public.biblio_articles.id;


--
-- Name: biblio_books; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.biblio_books (
    editor character varying(255),
    obj_id integer,
    notes text,
    total_volumes smallint,
    city character varying(255),
    year smallint,
    created timestamp without time zone,
    translator character varying(255),
    author_lastname character varying(255),
    title character varying(500),
    volume smallint,
    deleted integer,
    coauthor_1 character varying(255),
    coauthor_3 character varying(255),
    series character varying(255),
    coauthor_2 character varying(255),
    author_firstname character varying(255),
    publisher character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: biblio_books_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.biblio_books_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biblio_books_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biblio_books_id_seq OWNED BY public.biblio_books.id;


--
-- Name: biblio_contributions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.biblio_contributions (
    editor character varying(255),
    obj_id integer,
    notes text,
    total_volumes smallint,
    city character varying(255),
    year smallint,
    created timestamp without time zone,
    author_lastname character varying(255),
    title character varying(500),
    volume smallint,
    pages character varying(100),
    deleted integer,
    coauthor_1 character varying(255),
    book_title character varying(500),
    coauthor_3 character varying(255),
    coauthor_2 character varying(255),
    author_firstname character varying(255),
    publisher character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: biblio_contributions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.biblio_contributions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biblio_contributions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biblio_contributions_id_seq OWNED BY public.biblio_contributions.id;


--
-- Name: biblio_objects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.biblio_objects (
    olivier text,
    deleted integer,
    created timestamp without time zone,
    item_key character varying(255),
    checked integer,
    modified timestamp without time zone,
    id integer NOT NULL,
    type integer,
    vassis character varying(30)
);


--
-- Name: biblio_objects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.biblio_objects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biblio_objects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biblio_objects_id_seq OWNED BY public.biblio_objects.id;


--
-- Name: biblio_online_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.biblio_online_sources (
    obj_id integer,
    last_accessed timestamp without time zone,
    deleted integer,
    created timestamp without time zone,
    author_firstname character varying(255),
    author_lastname character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL,
    webpage character varying(255)
);


--
-- Name: biblio_online_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.biblio_online_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biblio_online_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biblio_online_sources_id_seq OWNED BY public.biblio_online_sources.id;


--
-- Name: chars; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chars (
    ori_char character varying(255),
    utf8_hex character varying(255),
    replace_char character varying(255),
    unicode_hex character varying(255),
    replace_hex character varying(255),
    id integer NOT NULL
);


--
-- Name: chars_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chars_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chars_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chars_id_seq OWNED BY public.chars.id;


--
-- Name: content; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.content (
    deleted integer,
    parent_id integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: content_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.content_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: content_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.content_id_seq OWNED BY public.content.id;


--
-- Name: genres; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.genres (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    description character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: genres_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.genres_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: genres_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.genres_id_seq OWNED BY public.genres.id;


--
-- Name: keywords; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.keywords (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: keywords_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.keywords_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: keywords_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.keywords_id_seq OWNED BY public.keywords.id;


--
-- Name: lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lists (
    id integer NOT NULL,
    type character varying(255),
    value character varying(255)
);


--
-- Name: lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lists_id_seq OWNED BY public.lists.id;


--
-- Name: location_funds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.location_funds (
    deleted integer,
    library_id integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: location_funds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.location_funds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: location_funds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.location_funds_id_seq OWNED BY public.location_funds.id;


--
-- Name: location_libraries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.location_libraries (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL,
    place_id integer
);


--
-- Name: location_libraries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.location_libraries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: location_libraries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.location_libraries_id_seq OWNED BY public.location_libraries.id;


--
-- Name: location_places; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.location_places (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: location_places_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.location_places_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: location_places_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.location_places_id_seq OWNED BY public.location_places.id;


--
-- Name: manuscripts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.manuscripts (
    fund_nr character varying(255),
    comments text,
    diktyon_nr character varying(10),
    is_illustrated integer,
    created timestamp without time zone,
    bibliography_id integer,
    fund_id integer,
    date_end integer,
    content_parent_id integer,
    content_child_id integer,
    date_start integer,
    origin_location_id integer,
    last_updated_by integer,
    status_id integer,
    deleted integer,
    library_id integer,
    name character varying(500),
    comments_intern text,
    creator_id integer,
    origin_region_id integer,
    modified timestamp without time zone,
    id integer NOT NULL,
    place_id integer
);


--
-- Name: manuscripts_bibliography; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.manuscripts_bibliography (
    manuscript_id integer NOT NULL,
    bibliography_object_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone,
    page character varying(100)
);


--
-- Name: manuscripts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.manuscripts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: manuscripts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.manuscripts_id_seq OWNED BY public.manuscripts.id;


--
-- Name: manuscripts_link_persons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.manuscripts_link_persons (
    manuscript_id integer NOT NULL,
    comments text,
    deleted integer,
    created timestamp without time zone,
    modified timestamp without time zone,
    person_id integer NOT NULL
);


--
-- Name: manuscripts_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.manuscripts_statuses (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    description character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: manuscripts_statuses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.manuscripts_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: manuscripts_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.manuscripts_statuses_id_seq OWNED BY public.manuscripts_statuses.id;


--
-- Name: meters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meters (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: meters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meters_id_seq OWNED BY public.meters.id;


--
-- Name: occasions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occasions (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    description character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: occasions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occasions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occasions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occasions_id_seq OWNED BY public.occasions.id;


--
-- Name: occurrences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences (
    manuscript_id integer,
    has_recovered_data integer,
    text_status_id integer,
    reviewed_by_rachele integer,
    lemma character varying(1000),
    occurrence_content text,
    image_source text,
    place_in_mss_folium text,
    paleographical_info text,
    last_updated_by integer,
    contextual_information text,
    modified timestamp without time zone,
    id integer NOT NULL,
    place_in_mss_general text,
    image character varying(255),
    text_missing integer,
    occurrence_kind integer,
    edition_id integer,
    comments text,
    type_id integer,
    created timestamp without time zone,
    acknowledgements character varying(1000),
    date_end integer,
    literature_id integer,
    date_start integer,
    record_status_id integer,
    deleted integer,
    incipit text,
    comments_intern text,
    creator_id integer,
    meter_id integer,
    verses integer,
    transcription_reviewed integer
);


--
-- Name: occurrences_bibliography; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_bibliography (
    bibliography_object_id integer NOT NULL,
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone,
    page character varying(100),
    bibliography_type integer
);


--
-- Name: occurrences_genres; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_genres (
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone,
    genre_id integer NOT NULL
);


--
-- Name: occurrences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occurrences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occurrences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occurrences_id_seq OWNED BY public.occurrences.id;


--
-- Name: occurrences_keywords; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_keywords (
    keyword_id integer NOT NULL,
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone
);


--
-- Name: occurrences_person_patron; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_person_patron (
    person_production_id integer NOT NULL,
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone
);


--
-- Name: occurrences_person_production_backlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_person_production_backlog (
    person_production_id integer,
    created timestamp without time zone,
    occurrence_id integer,
    modified timestamp without time zone,
    pk integer NOT NULL
);


--
-- Name: occurrences_person_production_backlog_pk_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occurrences_person_production_backlog_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occurrences_person_production_backlog_pk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occurrences_person_production_backlog_pk_seq OWNED BY public.occurrences_person_production_backlog.pk;


--
-- Name: occurrences_person_scribe; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_person_scribe (
    person_scribe_id integer NOT NULL,
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone
);


--
-- Name: occurrences_person_scribe_backlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_person_scribe_backlog (
    person_scribe_id integer,
    created timestamp without time zone,
    occurrence_id integer,
    modified timestamp without time zone,
    pk integer NOT NULL
);


--
-- Name: occurrences_person_scribe_backlog_pk_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occurrences_person_scribe_backlog_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occurrences_person_scribe_backlog_pk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occurrences_person_scribe_backlog_pk_seq OWNED BY public.occurrences_person_scribe_backlog.pk;


--
-- Name: occurrences_record_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_record_statuses (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    description character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: occurrences_record_statuses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occurrences_record_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occurrences_record_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occurrences_record_statuses_id_seq OWNED BY public.occurrences_record_statuses.id;


--
-- Name: occurrences_relationships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_relationships (
    relationship_type character varying(255),
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone,
    related_occurrence_id integer NOT NULL
);


--
-- Name: occurrences_subjects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_subjects (
    subject_id integer NOT NULL,
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone
);


--
-- Name: occurrences_text_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.occurrences_text_statuses (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    description character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: occurrences_text_statuses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.occurrences_text_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: occurrences_text_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.occurrences_text_statuses_id_seq OWNED BY public.occurrences_text_statuses.id;


--
-- Name: origin_location; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.origin_location (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    origin_region_id integer,
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: origin_location_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.origin_location_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: origin_location_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.origin_location_id_seq OWNED BY public.origin_location.id;


--
-- Name: origin_region; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.origin_region (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: origin_region_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.origin_region_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: origin_region_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.origin_region_id_seq OWNED BY public.origin_region.id;


--
-- Name: pages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pages (
    author integer,
    created timestamp without time zone,
    modified timestamp without time zone,
    id integer NOT NULL,
    title character varying(100),
    slug character varying(100),
    content text
);


--
-- Name: pages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pages_id_seq OWNED BY public.pages.id;


--
-- Name: persons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.persons (
    rgk_number character varying(45),
    deleted integer,
    vgh_number character varying(45),
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL,
    pbw_number character varying(45)
);


--
-- Name: persons_functions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.persons_functions (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: persons_functions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.persons_functions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: persons_functions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.persons_functions_id_seq OWNED BY public.persons_functions.id;


--
-- Name: persons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.persons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: persons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.persons_id_seq OWNED BY public.persons.id;


--
-- Name: persons_link_functions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.persons_link_functions (
    created timestamp without time zone,
    function_id integer NOT NULL,
    modified timestamp without time zone,
    person_id integer NOT NULL
);


--
-- Name: persons_link_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.persons_link_types (
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone,
    person_id integer NOT NULL
);


--
-- Name: persons_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.persons_types (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: persons_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.persons_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: persons_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.persons_types_id_seq OWNED BY public.persons_types.id;


--
-- Name: relationships_description_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.relationships_description_types (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: relationships_description_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.relationships_description_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: relationships_description_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.relationships_description_types_id_seq OWNED BY public.relationships_description_types.id;


--
-- Name: subjects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subjects (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: subjects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subjects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subjects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subjects_id_seq OWNED BY public.subjects.id;


--
-- Name: types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types (
    comments text,
    identifier_vassis_2005_page character varying(1000),
    identifier_vassis_2011_title character varying(1000),
    indian_summer_2017 integer,
    text_status_id integer,
    identifier_ptb character varying(1000),
    created timestamp without time zone,
    critical_apparatus text,
    lemma character varying(1000),
    acknowledgements character varying(1000),
    type_content text,
    identifier_vassis_2005_title character varying(1000),
    identifier_ap character varying(1000),
    deleted integer,
    incipit text,
    latin_title character varying(1000),
    translation text,
    comments_intern text,
    modified timestamp without time zone,
    id integer NOT NULL,
    source_id integer,
    identifier_vassis_2011_page character varying(1000),
    meter_id integer,
    person_id integer
);


--
-- Name: types_bibliography; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_bibliography (
    bibliography_object_id integer NOT NULL,
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone,
    bibliography_type integer,
    page character varying(100)
);


--
-- Name: types_genres; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_genres (
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone,
    genre_id integer NOT NULL
);


--
-- Name: types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.types_id_seq OWNED BY public.types.id;


--
-- Name: types_keywords; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_keywords (
    keyword_id integer NOT NULL,
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone
);


--
-- Name: types_occurrences_text_source; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_occurrences_text_source (
    type_id integer NOT NULL,
    created timestamp without time zone,
    occurrence_id integer NOT NULL,
    modified timestamp without time zone
);


--
-- Name: types_person_poet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_person_poet (
    poet_id integer NOT NULL,
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone
);


--
-- Name: types_relationships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_relationships (
    related_type_id integer NOT NULL,
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone,
    relationship_description_type_id integer
);


--
-- Name: types_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_sources (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: types_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.types_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: types_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.types_sources_id_seq OWNED BY public.types_sources.id;


--
-- Name: types_subjects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_subjects (
    subject_id integer NOT NULL,
    type_id integer NOT NULL,
    created timestamp without time zone,
    modified timestamp without time zone
);


--
-- Name: types_text_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.types_text_statuses (
    deleted integer,
    created timestamp without time zone,
    name character varying(255),
    modified timestamp without time zone,
    id integer NOT NULL
);


--
-- Name: types_text_statuses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.types_text_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: types_text_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.types_text_statuses_id_seq OWNED BY public.types_text_statuses.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    last_ip character varying(45),
    initials character varying(20),
    created timestamp without time zone,
    last_login timestamp without time zone,
    login character varying(20),
    type integer,
    password character varying(50),
    full_name character varying(200),
    deleted integer,
    modified timestamp without time zone,
    id integer NOT NULL,
    email character varying(255),
    status integer
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: acknowledgement id; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.acknowledgement ALTER COLUMN id SET DEFAULT nextval('data.acknowledgement_id_seq'::regclass);


--
-- Name: document_contains iddocumentcontains; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_contains ALTER COLUMN iddocumentcontains SET DEFAULT nextval('data.document_contains_iddocumentcontains_seq'::regclass);


--
-- Name: entity identity; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity ALTER COLUMN identity SET DEFAULT nextval('data.entity_identity_seq'::regclass);


--
-- Name: entity_url idurl; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity_url ALTER COLUMN idurl SET DEFAULT nextval('data.entity_url_idurl_seq'::regclass);


--
-- Name: evidence idevidence; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.evidence ALTER COLUMN idevidence SET DEFAULT nextval('data.evidence_idevidence_seq'::regclass);


--
-- Name: factoid idfactoid; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid ALTER COLUMN idfactoid SET DEFAULT nextval('data.factoid_idfactoid_seq'::regclass);


--
-- Name: factoid_type idfactoid_type; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid_type ALTER COLUMN idfactoid_type SET DEFAULT nextval('data.factoid_type_idfactoid_type_seq'::regclass);


--
-- Name: fund idfund; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.fund ALTER COLUMN idfund SET DEFAULT nextval('data.fund_idfund_seq'::regclass);


--
-- Name: genre idgenre; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.genre ALTER COLUMN idgenre SET DEFAULT nextval('data.genre_idgenre_seq'::regclass);


--
-- Name: identifier ididentifier; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.identifier ALTER COLUMN ididentifier SET DEFAULT nextval('data.identifier_ididentifier_seq'::regclass);


--
-- Name: image idimage; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.image ALTER COLUMN idimage SET DEFAULT nextval('data.image_idimage_seq'::regclass);


--
-- Name: language idlanguage; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.language ALTER COLUMN idlanguage SET DEFAULT nextval('data.language_idlanguage_seq'::regclass);


--
-- Name: location idlocation; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.location ALTER COLUMN idlocation SET DEFAULT nextval('data.location_idlocation_seq'::regclass);


--
-- Name: management id; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.management ALTER COLUMN id SET DEFAULT nextval('data.management_id_seq'::regclass);


--
-- Name: meter idmeter; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.meter ALTER COLUMN idmeter SET DEFAULT nextval('data.meter_idmeter_seq'::regclass);


--
-- Name: name idname; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.name ALTER COLUMN idname SET DEFAULT nextval('data.name_idname_seq'::regclass);


--
-- Name: node identity; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.node ALTER COLUMN identity SET DEFAULT nextval('data.group_identity_seq'::regclass);


--
-- Name: occupation idoccupation; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.occupation ALTER COLUMN idoccupation SET DEFAULT nextval('data.occupation_idoccupation_seq'::regclass);


--
-- Name: original_poem_verse id; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.original_poem_verse ALTER COLUMN id SET DEFAULT nextval('data.original_poem_verse_id_seq'::regclass);


--
-- Name: reconstructed_poem_lemma id; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reconstructed_poem_lemma ALTER COLUMN id SET DEFAULT nextval('data.reconstructed_poem_lemma_id_seq'::regclass);


--
-- Name: reference idreference; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference ALTER COLUMN idreference SET DEFAULT nextval('data.reference_idreference_seq'::regclass);


--
-- Name: reference_type idreference_type; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference_type ALTER COLUMN idreference_type SET DEFAULT nextval('data.reference_type_idreference_type_seq'::regclass);


--
-- Name: role idrole; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.role ALTER COLUMN idrole SET DEFAULT nextval('data.role_idrole_seq'::regclass);


--
-- Name: self_designation id; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.self_designation ALTER COLUMN id SET DEFAULT nextval('data.self_designation_id_seq'::regclass);


--
-- Name: status idstatus; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.status ALTER COLUMN idstatus SET DEFAULT nextval('data.status_idstatus_seq'::regclass);


--
-- Name: transliterationsystem idtransliterationsystem; Type: DEFAULT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.transliterationsystem ALTER COLUMN idtransliterationsystem SET DEFAULT nextval('data.transliterationsystem_idtransliterationsystem_seq'::regclass);


--
-- Name: substringannotation idsubstringannotation; Type: DEFAULT; Schema: julie; Owner: -
--

ALTER TABLE ONLY julie.substringannotation ALTER COLUMN idsubstringannotation SET DEFAULT nextval('julie.annotation_idannotation_seq'::regclass);


--
-- Name: substringannotation idsubstringannotation; Type: DEFAULT; Schema: julie_before_2019_12_11; Owner: -
--

ALTER TABLE ONLY julie_before_2019_12_11.substringannotation ALTER COLUMN idsubstringannotation SET DEFAULT nextval('julie_before_2019_12_11.annotation_idannotation_seq'::regclass);


--
-- Name: feedback id; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.feedback ALTER COLUMN id SET DEFAULT nextval('logic.feedback_id_seq'::regclass);


--
-- Name: news_event id; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.news_event ALTER COLUMN id SET DEFAULT nextval('logic.news_event_id_seq'::regclass);


--
-- Name: page id; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.page ALTER COLUMN id SET DEFAULT nextval('logic.page_id_seq'::regclass);


--
-- Name: revision idrevision; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.revision ALTER COLUMN idrevision SET DEFAULT nextval('logic.revision_idrevision_seq2'::regclass);


--
-- Name: revision_2019_05_15 idrevision; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.revision_2019_05_15 ALTER COLUMN idrevision SET DEFAULT nextval('logic.revision_idrevision_seq1'::regclass);


--
-- Name: revision_old idrevision; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.revision_old ALTER COLUMN idrevision SET DEFAULT nextval('logic.revision_idrevision_seq'::regclass);


--
-- Name: user id; Type: DEFAULT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic."user" ALTER COLUMN id SET DEFAULT nextval('logic.user_id_seq'::regclass);


--
-- Name: audit_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_log ALTER COLUMN id SET DEFAULT nextval('public.audit_log_id_seq'::regclass);


--
-- Name: biblio_articles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_articles ALTER COLUMN id SET DEFAULT nextval('public.biblio_articles_id_seq'::regclass);


--
-- Name: biblio_books id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_books ALTER COLUMN id SET DEFAULT nextval('public.biblio_books_id_seq'::regclass);


--
-- Name: biblio_contributions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_contributions ALTER COLUMN id SET DEFAULT nextval('public.biblio_contributions_id_seq'::regclass);


--
-- Name: biblio_objects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_objects ALTER COLUMN id SET DEFAULT nextval('public.biblio_objects_id_seq'::regclass);


--
-- Name: biblio_online_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_online_sources ALTER COLUMN id SET DEFAULT nextval('public.biblio_online_sources_id_seq'::regclass);


--
-- Name: chars id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chars ALTER COLUMN id SET DEFAULT nextval('public.chars_id_seq'::regclass);


--
-- Name: content id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content ALTER COLUMN id SET DEFAULT nextval('public.content_id_seq'::regclass);


--
-- Name: genres id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.genres ALTER COLUMN id SET DEFAULT nextval('public.genres_id_seq'::regclass);


--
-- Name: keywords id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.keywords ALTER COLUMN id SET DEFAULT nextval('public.keywords_id_seq'::regclass);


--
-- Name: lists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lists ALTER COLUMN id SET DEFAULT nextval('public.lists_id_seq'::regclass);


--
-- Name: location_funds id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.location_funds ALTER COLUMN id SET DEFAULT nextval('public.location_funds_id_seq'::regclass);


--
-- Name: location_libraries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.location_libraries ALTER COLUMN id SET DEFAULT nextval('public.location_libraries_id_seq'::regclass);


--
-- Name: location_places id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.location_places ALTER COLUMN id SET DEFAULT nextval('public.location_places_id_seq'::regclass);


--
-- Name: manuscripts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manuscripts ALTER COLUMN id SET DEFAULT nextval('public.manuscripts_id_seq'::regclass);


--
-- Name: manuscripts_statuses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manuscripts_statuses ALTER COLUMN id SET DEFAULT nextval('public.manuscripts_statuses_id_seq'::regclass);


--
-- Name: meters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meters ALTER COLUMN id SET DEFAULT nextval('public.meters_id_seq'::regclass);


--
-- Name: occasions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occasions ALTER COLUMN id SET DEFAULT nextval('public.occasions_id_seq'::regclass);


--
-- Name: occurrences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences ALTER COLUMN id SET DEFAULT nextval('public.occurrences_id_seq'::regclass);


--
-- Name: occurrences_person_production_backlog pk; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_person_production_backlog ALTER COLUMN pk SET DEFAULT nextval('public.occurrences_person_production_backlog_pk_seq'::regclass);


--
-- Name: occurrences_person_scribe_backlog pk; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_person_scribe_backlog ALTER COLUMN pk SET DEFAULT nextval('public.occurrences_person_scribe_backlog_pk_seq'::regclass);


--
-- Name: occurrences_record_statuses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_record_statuses ALTER COLUMN id SET DEFAULT nextval('public.occurrences_record_statuses_id_seq'::regclass);


--
-- Name: occurrences_text_statuses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_text_statuses ALTER COLUMN id SET DEFAULT nextval('public.occurrences_text_statuses_id_seq'::regclass);


--
-- Name: origin_location id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.origin_location ALTER COLUMN id SET DEFAULT nextval('public.origin_location_id_seq'::regclass);


--
-- Name: origin_region id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.origin_region ALTER COLUMN id SET DEFAULT nextval('public.origin_region_id_seq'::regclass);


--
-- Name: pages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages ALTER COLUMN id SET DEFAULT nextval('public.pages_id_seq'::regclass);


--
-- Name: persons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons ALTER COLUMN id SET DEFAULT nextval('public.persons_id_seq'::regclass);


--
-- Name: persons_functions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons_functions ALTER COLUMN id SET DEFAULT nextval('public.persons_functions_id_seq'::regclass);


--
-- Name: persons_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons_types ALTER COLUMN id SET DEFAULT nextval('public.persons_types_id_seq'::regclass);


--
-- Name: relationships_description_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.relationships_description_types ALTER COLUMN id SET DEFAULT nextval('public.relationships_description_types_id_seq'::regclass);


--
-- Name: subjects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subjects ALTER COLUMN id SET DEFAULT nextval('public.subjects_id_seq'::regclass);


--
-- Name: types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types ALTER COLUMN id SET DEFAULT nextval('public.types_id_seq'::regclass);


--
-- Name: types_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_sources ALTER COLUMN id SET DEFAULT nextval('public.types_sources_id_seq'::regclass);


--
-- Name: types_text_statuses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_text_statuses ALTER COLUMN id SET DEFAULT nextval('public.types_text_statuses_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: identifier identifier_system_name_key; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.identifier
    ADD CONSTRAINT identifier_system_name_key UNIQUE (system_name);


--
-- Name: lemma_cache lemma_cache_pkey; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.lemma_cache
    ADD CONSTRAINT lemma_cache_pkey PRIMARY KEY (input);


--
-- Name: acknowledgement pk_acknowledgement; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.acknowledgement
    ADD CONSTRAINT pk_acknowledgement PRIMARY KEY (id);


--
-- Name: article pk_article; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.article
    ADD CONSTRAINT pk_article PRIMARY KEY (identity);


--
-- Name: bib_varia pk_bib_varia; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bib_varia
    ADD CONSTRAINT pk_bib_varia PRIMARY KEY (identity);


--
-- Name: blog pk_blog; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.blog
    ADD CONSTRAINT pk_blog PRIMARY KEY (identity);


--
-- Name: blog_post pk_blog_post; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.blog_post
    ADD CONSTRAINT pk_blog_post PRIMARY KEY (identity);


--
-- Name: book pk_book; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book
    ADD CONSTRAINT pk_book PRIMARY KEY (identity);


--
-- Name: book_cluster pk_book_cluster; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book_cluster
    ADD CONSTRAINT pk_book_cluster PRIMARY KEY (identity);


--
-- Name: book_series pk_book_series; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book_series
    ADD CONSTRAINT pk_book_series PRIMARY KEY (identity);


--
-- Name: bookchapter pk_bookchapter; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bookchapter
    ADD CONSTRAINT pk_bookchapter PRIMARY KEY (identity);


--
-- Name: document pk_document; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document
    ADD CONSTRAINT pk_document PRIMARY KEY (identity);


--
-- Name: document_contains pk_document_contains; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_contains
    ADD CONSTRAINT pk_document_contains PRIMARY KEY (iddocumentcontains);


--
-- Name: document_genre pk_document_genre; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_genre
    ADD CONSTRAINT pk_document_genre PRIMARY KEY (iddocument, idgenre);


--
-- Name: document_group pk_document_group; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_group
    ADD CONSTRAINT pk_document_group PRIMARY KEY (iddocument, idgroup);


--
-- Name: document_image pk_document_image; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_image
    ADD CONSTRAINT pk_document_image PRIMARY KEY (iddocument, idimage);


--
-- Name: document_keyword pk_document_keyword; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_keyword
    ADD CONSTRAINT pk_document_keyword PRIMARY KEY (iddocument, idkeyword);


--
-- Name: document_status pk_document_status; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_status
    ADD CONSTRAINT pk_document_status PRIMARY KEY (iddocument, idstatus);


--
-- Name: document_title pk_document_title; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_title
    ADD CONSTRAINT pk_document_title PRIMARY KEY (iddocument, idlanguage);


--
-- Name: entity pk_entity; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity
    ADD CONSTRAINT pk_entity PRIMARY KEY (identity);


--
-- Name: entity_management pk_entity_management; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity_management
    ADD CONSTRAINT pk_entity_management PRIMARY KEY (identity, idmanagement);


--
-- Name: evidence pk_evidence; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.evidence
    ADD CONSTRAINT pk_evidence PRIMARY KEY (idevidence);


--
-- Name: evidence_factoid pk_evidence_factoid; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.evidence_factoid
    ADD CONSTRAINT pk_evidence_factoid PRIMARY KEY (idevidence, idfactoid);


--
-- Name: factoid pk_factoid; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT pk_factoid PRIMARY KEY (idfactoid);


--
-- Name: factoid_type pk_factoid_type; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid_type
    ADD CONSTRAINT pk_factoid_type PRIMARY KEY (idfactoid_type);


--
-- Name: fund pk_fund; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.fund
    ADD CONSTRAINT pk_fund PRIMARY KEY (idfund);


--
-- Name: genre pk_genre; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.genre
    ADD CONSTRAINT pk_genre PRIMARY KEY (idgenre);


--
-- Name: global_id pk_global_id; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.global_id
    ADD CONSTRAINT pk_global_id PRIMARY KEY (idauthority, idsubject);


--
-- Name: identifier pk_identifier; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.identifier
    ADD CONSTRAINT pk_identifier PRIMARY KEY (ididentifier);


--
-- Name: image pk_image; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.image
    ADD CONSTRAINT pk_image PRIMARY KEY (idimage);


--
-- Name: institution pk_institution; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.institution
    ADD CONSTRAINT pk_institution PRIMARY KEY (identity);


--
-- Name: journal pk_journal; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.journal
    ADD CONSTRAINT pk_journal PRIMARY KEY (identity);


--
-- Name: journal_issue pk_journal_issue; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.journal_issue
    ADD CONSTRAINT pk_journal_issue PRIMARY KEY (identity);


--
-- Name: keyword pk_keyword; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.keyword
    ADD CONSTRAINT pk_keyword PRIMARY KEY (identity);


--
-- Name: language pk_language; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.language
    ADD CONSTRAINT pk_language PRIMARY KEY (idlanguage);


--
-- Name: library pk_library; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.library
    ADD CONSTRAINT pk_library PRIMARY KEY (identity);


--
-- Name: location pk_location; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.location
    ADD CONSTRAINT pk_location PRIMARY KEY (idlocation);


--
-- Name: management pk_management; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.management
    ADD CONSTRAINT pk_management PRIMARY KEY (id);


--
-- Name: manuscript pk_manuscript; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.manuscript
    ADD CONSTRAINT pk_manuscript PRIMARY KEY (identity);


--
-- Name: meter pk_meter; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.meter
    ADD CONSTRAINT pk_meter PRIMARY KEY (idmeter);


--
-- Name: monastery pk_monastery; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.monastery
    ADD CONSTRAINT pk_monastery PRIMARY KEY (identity);


--
-- Name: name pk_name; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.name
    ADD CONSTRAINT pk_name PRIMARY KEY (idname);


--
-- Name: node pk_node; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.node
    ADD CONSTRAINT pk_node PRIMARY KEY (identity);


--
-- Name: occupation pk_occupation; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.occupation
    ADD CONSTRAINT pk_occupation PRIMARY KEY (idoccupation);


--
-- Name: online_source pk_online_source; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.online_source
    ADD CONSTRAINT pk_online_source PRIMARY KEY (identity);


--
-- Name: original_poem pk_original_poem; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.original_poem
    ADD CONSTRAINT pk_original_poem PRIMARY KEY (identity);


--
-- Name: original_poem_verse pk_original_poem_verse; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.original_poem_verse
    ADD CONSTRAINT pk_original_poem_verse PRIMARY KEY (id);


--
-- Name: person pk_person; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person
    ADD CONSTRAINT pk_person PRIMARY KEY (identity);


--
-- Name: person_email pk_person_email; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_email
    ADD CONSTRAINT pk_person_email PRIMARY KEY (idperson, email);


--
-- Name: person_occupation pk_person_occupation; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_occupation
    ADD CONSTRAINT pk_person_occupation PRIMARY KEY (idperson, idoccupation);


--
-- Name: person_self_designation pk_person_self_designation; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_self_designation
    ADD CONSTRAINT pk_person_self_designation PRIMARY KEY (idperson, idself_designation);


--
-- Name: phd pk_phd; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.phd
    ADD CONSTRAINT pk_phd PRIMARY KEY (identity);


--
-- Name: poem pk_poem; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.poem
    ADD CONSTRAINT pk_poem PRIMARY KEY (identity);


--
-- Name: poem_meter pk_poem_meter; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.poem_meter
    ADD CONSTRAINT pk_poem_meter PRIMARY KEY (idmeter, idpoem);


--
-- Name: reconstructed_poem pk_reconstructed_poem; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reconstructed_poem
    ADD CONSTRAINT pk_reconstructed_poem PRIMARY KEY (identity);


--
-- Name: reference pk_reference; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference
    ADD CONSTRAINT pk_reference PRIMARY KEY (idreference);


--
-- Name: reference_type pk_reference_type; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference_type
    ADD CONSTRAINT pk_reference_type PRIMARY KEY (idreference_type);


--
-- Name: region pk_region; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.region
    ADD CONSTRAINT pk_region PRIMARY KEY (identity);


--
-- Name: role pk_role; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.role
    ADD CONSTRAINT pk_role PRIMARY KEY (idrole);


--
-- Name: self_designation pk_self_designation; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.self_designation
    ADD CONSTRAINT pk_self_designation PRIMARY KEY (id);


--
-- Name: status pk_status; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.status
    ADD CONSTRAINT pk_status PRIMARY KEY (idstatus);


--
-- Name: translation pk_translation; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.translation
    ADD CONSTRAINT pk_translation PRIMARY KEY (identity);


--
-- Name: translation_of pk_translation_of; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.translation_of
    ADD CONSTRAINT pk_translation_of PRIMARY KEY (idtranslation, iddocument);


--
-- Name: transliterationsystem pk_transliterationsystem; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.transliterationsystem
    ADD CONSTRAINT pk_transliterationsystem PRIMARY KEY (idtransliterationsystem);


--
-- Name: role role_system_name_key; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.role
    ADD CONSTRAINT role_system_name_key UNIQUE (system_name);


--
-- Name: person_email unq_email; Type: CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_email
    ADD CONSTRAINT unq_email UNIQUE (email);


--
-- Name: substringannotation pk_annotation; Type: CONSTRAINT; Schema: julie; Owner: -
--

ALTER TABLE ONLY julie.substringannotation
    ADD CONSTRAINT pk_annotation PRIMARY KEY (idsubstringannotation);


--
-- Name: poemannotation pk_poemannotation; Type: CONSTRAINT; Schema: julie; Owner: -
--

ALTER TABLE ONLY julie.poemannotation
    ADD CONSTRAINT pk_poemannotation PRIMARY KEY (idoccurrence);


--
-- Name: substringannotation pk_annotation; Type: CONSTRAINT; Schema: julie_before_2019_12_11; Owner: -
--

ALTER TABLE ONLY julie_before_2019_12_11.substringannotation
    ADD CONSTRAINT pk_annotation PRIMARY KEY (idsubstringannotation);


--
-- Name: poemannotation pk_poemannotation; Type: CONSTRAINT; Schema: julie_before_2019_12_11; Owner: -
--

ALTER TABLE ONLY julie_before_2019_12_11.poemannotation
    ADD CONSTRAINT pk_poemannotation PRIMARY KEY (idoccurrence);


--
-- Name: feedback feedback_pkey; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.feedback
    ADD CONSTRAINT feedback_pkey PRIMARY KEY (id);


--
-- Name: fos_user fos_user_pkey; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.fos_user
    ADD CONSTRAINT fos_user_pkey PRIMARY KEY (id);


--
-- Name: contributor_of pk_contributor_of; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.contributor_of
    ADD CONSTRAINT pk_contributor_of PRIMARY KEY (iduser, iddocument);


--
-- Name: news_event pk_news_event; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.news_event
    ADD CONSTRAINT pk_news_event PRIMARY KEY (id);


--
-- Name: page pk_page; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.page
    ADD CONSTRAINT pk_page PRIMARY KEY (id);


--
-- Name: revision pk_revision; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.revision
    ADD CONSTRAINT pk_revision PRIMARY KEY (idrevision);


--
-- Name: user_old pk_user; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.user_old
    ADD CONSTRAINT pk_user PRIMARY KEY (identity);


--
-- Name: working_on pk_working_on; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.working_on
    ADD CONSTRAINT pk_working_on PRIMARY KEY (iduser, iddocument);


--
-- Name: user user_pkey; Type: CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic."user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (id);


--
-- Name: manuscript_statuses_status pk_mss; Type: CONSTRAINT; Schema: migration; Owner: -
--

ALTER TABLE ONLY migration.manuscript_statuses_status
    ADD CONSTRAINT pk_mss PRIMARY KEY (old_id, idstatus);


--
-- Name: manuscripts_to_manuscript pk_mtm; Type: CONSTRAINT; Schema: migration; Owner: -
--

ALTER TABLE ONLY migration.manuscripts_to_manuscript
    ADD CONSTRAINT pk_mtm PRIMARY KEY (old_id, identity);


--
-- Name: audit_log audit_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_log
    ADD CONSTRAINT audit_log_pkey PRIMARY KEY (id);


--
-- Name: biblio_articles biblio_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_articles
    ADD CONSTRAINT biblio_articles_pkey PRIMARY KEY (id);


--
-- Name: biblio_books biblio_books_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_books
    ADD CONSTRAINT biblio_books_pkey PRIMARY KEY (id);


--
-- Name: biblio_contributions biblio_contributions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_contributions
    ADD CONSTRAINT biblio_contributions_pkey PRIMARY KEY (id);


--
-- Name: biblio_objects biblio_objects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_objects
    ADD CONSTRAINT biblio_objects_pkey PRIMARY KEY (id);


--
-- Name: biblio_online_sources biblio_online_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biblio_online_sources
    ADD CONSTRAINT biblio_online_sources_pkey PRIMARY KEY (id);


--
-- Name: chars chars_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chars
    ADD CONSTRAINT chars_pkey PRIMARY KEY (id);


--
-- Name: content content_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content
    ADD CONSTRAINT content_pkey PRIMARY KEY (id);


--
-- Name: genres genres_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.genres
    ADD CONSTRAINT genres_pkey PRIMARY KEY (id);


--
-- Name: keywords keywords_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.keywords
    ADD CONSTRAINT keywords_pkey PRIMARY KEY (id);


--
-- Name: lists lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lists
    ADD CONSTRAINT lists_pkey PRIMARY KEY (id);


--
-- Name: location_funds location_funds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.location_funds
    ADD CONSTRAINT location_funds_pkey PRIMARY KEY (id);


--
-- Name: location_libraries location_libraries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.location_libraries
    ADD CONSTRAINT location_libraries_pkey PRIMARY KEY (id);


--
-- Name: location_places location_places_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.location_places
    ADD CONSTRAINT location_places_pkey PRIMARY KEY (id);


--
-- Name: manuscripts_bibliography manuscripts_bibliography_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manuscripts_bibliography
    ADD CONSTRAINT manuscripts_bibliography_pkey PRIMARY KEY (manuscript_id, bibliography_object_id);


--
-- Name: manuscripts_link_persons manuscripts_link_persons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manuscripts_link_persons
    ADD CONSTRAINT manuscripts_link_persons_pkey PRIMARY KEY (manuscript_id, person_id);


--
-- Name: manuscripts manuscripts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manuscripts
    ADD CONSTRAINT manuscripts_pkey PRIMARY KEY (id);


--
-- Name: manuscripts_statuses manuscripts_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manuscripts_statuses
    ADD CONSTRAINT manuscripts_statuses_pkey PRIMARY KEY (id);


--
-- Name: meters meters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meters
    ADD CONSTRAINT meters_pkey PRIMARY KEY (id);


--
-- Name: occasions occasions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occasions
    ADD CONSTRAINT occasions_pkey PRIMARY KEY (id);


--
-- Name: occurrences_bibliography occurrences_bibliography_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_bibliography
    ADD CONSTRAINT occurrences_bibliography_pkey PRIMARY KEY (bibliography_object_id, occurrence_id);


--
-- Name: occurrences_genres occurrences_genres_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_genres
    ADD CONSTRAINT occurrences_genres_pkey PRIMARY KEY (occurrence_id, genre_id);


--
-- Name: occurrences_keywords occurrences_keywords_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_keywords
    ADD CONSTRAINT occurrences_keywords_pkey PRIMARY KEY (keyword_id, occurrence_id);


--
-- Name: occurrences_person_patron occurrences_person_patron_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_person_patron
    ADD CONSTRAINT occurrences_person_patron_pkey PRIMARY KEY (person_production_id, occurrence_id);


--
-- Name: occurrences_person_production_backlog occurrences_person_production_backlog_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_person_production_backlog
    ADD CONSTRAINT occurrences_person_production_backlog_pkey PRIMARY KEY (pk);


--
-- Name: occurrences_person_scribe_backlog occurrences_person_scribe_backlog_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_person_scribe_backlog
    ADD CONSTRAINT occurrences_person_scribe_backlog_pkey PRIMARY KEY (pk);


--
-- Name: occurrences_person_scribe occurrences_person_scribe_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_person_scribe
    ADD CONSTRAINT occurrences_person_scribe_pkey PRIMARY KEY (person_scribe_id, occurrence_id);


--
-- Name: occurrences occurrences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences
    ADD CONSTRAINT occurrences_pkey PRIMARY KEY (id);


--
-- Name: occurrences_record_statuses occurrences_record_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_record_statuses
    ADD CONSTRAINT occurrences_record_statuses_pkey PRIMARY KEY (id);


--
-- Name: occurrences_relationships occurrences_relationships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_relationships
    ADD CONSTRAINT occurrences_relationships_pkey PRIMARY KEY (occurrence_id, related_occurrence_id);


--
-- Name: occurrences_subjects occurrences_subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_subjects
    ADD CONSTRAINT occurrences_subjects_pkey PRIMARY KEY (subject_id, occurrence_id);


--
-- Name: occurrences_text_statuses occurrences_text_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.occurrences_text_statuses
    ADD CONSTRAINT occurrences_text_statuses_pkey PRIMARY KEY (id);


--
-- Name: origin_location origin_location_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.origin_location
    ADD CONSTRAINT origin_location_pkey PRIMARY KEY (id);


--
-- Name: origin_region origin_region_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.origin_region
    ADD CONSTRAINT origin_region_pkey PRIMARY KEY (id);


--
-- Name: pages pages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_pkey PRIMARY KEY (id);


--
-- Name: persons_functions persons_functions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons_functions
    ADD CONSTRAINT persons_functions_pkey PRIMARY KEY (id);


--
-- Name: persons_link_functions persons_link_functions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons_link_functions
    ADD CONSTRAINT persons_link_functions_pkey PRIMARY KEY (function_id, person_id);


--
-- Name: persons_link_types persons_link_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons_link_types
    ADD CONSTRAINT persons_link_types_pkey PRIMARY KEY (type_id, person_id);


--
-- Name: persons persons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons
    ADD CONSTRAINT persons_pkey PRIMARY KEY (id);


--
-- Name: persons_types persons_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.persons_types
    ADD CONSTRAINT persons_types_pkey PRIMARY KEY (id);


--
-- Name: relationships_description_types relationships_description_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.relationships_description_types
    ADD CONSTRAINT relationships_description_types_pkey PRIMARY KEY (id);


--
-- Name: subjects subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subjects
    ADD CONSTRAINT subjects_pkey PRIMARY KEY (id);


--
-- Name: types_bibliography types_bibliography_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_bibliography
    ADD CONSTRAINT types_bibliography_pkey PRIMARY KEY (bibliography_object_id, type_id);


--
-- Name: types_genres types_genres_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_genres
    ADD CONSTRAINT types_genres_pkey PRIMARY KEY (type_id, genre_id);


--
-- Name: types_keywords types_keywords_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_keywords
    ADD CONSTRAINT types_keywords_pkey PRIMARY KEY (keyword_id, type_id);


--
-- Name: types_occurrences_text_source types_occurrences_text_source_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_occurrences_text_source
    ADD CONSTRAINT types_occurrences_text_source_pkey PRIMARY KEY (type_id, occurrence_id);


--
-- Name: types_person_poet types_person_poet_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_person_poet
    ADD CONSTRAINT types_person_poet_pkey PRIMARY KEY (poet_id, type_id);


--
-- Name: types types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types
    ADD CONSTRAINT types_pkey PRIMARY KEY (id);


--
-- Name: types_relationships types_relationships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_relationships
    ADD CONSTRAINT types_relationships_pkey PRIMARY KEY (related_type_id, type_id);


--
-- Name: types_sources types_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_sources
    ADD CONSTRAINT types_sources_pkey PRIMARY KEY (id);


--
-- Name: types_subjects types_subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_subjects
    ADD CONSTRAINT types_subjects_pkey PRIMARY KEY (subject_id, type_id);


--
-- Name: types_text_statuses types_text_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.types_text_statuses
    ADD CONSTRAINT types_text_statuses_pkey PRIMARY KEY (id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: fki_factoid_factoid_type_new; Type: INDEX; Schema: data; Owner: -
--

CREATE INDEX fki_factoid_factoid_type_new ON data.factoid USING btree (idfactoid_type);


--
-- Name: uniq_a9f818c092fc23a8; Type: INDEX; Schema: logic; Owner: -
--

CREATE UNIQUE INDEX uniq_a9f818c092fc23a8 ON logic.fos_user USING btree (username_canonical);


--
-- Name: uniq_a9f818c0a0d96fbf; Type: INDEX; Schema: logic; Owner: -
--

CREATE UNIQUE INDEX uniq_a9f818c0a0d96fbf ON logic.fos_user USING btree (email_canonical);


--
-- Name: uniq_a9f818c0c05fb297; Type: INDEX; Schema: logic; Owner: -
--

CREATE UNIQUE INDEX uniq_a9f818c0c05fb297 ON logic.fos_user USING btree (confirmation_token);


--
-- Name: bib_varia delete_entity_instead_of_bib_varia; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_bib_varia AFTER DELETE ON data.bib_varia FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: blog delete_entity_instead_of_blog; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_blog AFTER DELETE ON data.blog FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: blog_post delete_entity_instead_of_blog_post; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_blog_post AFTER DELETE ON data.blog_post FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: book delete_entity_instead_of_book; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_book AFTER DELETE ON data.book FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: book_cluster delete_entity_instead_of_book_cluster; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_book_cluster AFTER DELETE ON data.book_cluster FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: book_series delete_entity_instead_of_book_series; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_book_series AFTER DELETE ON data.book_series FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: bookchapter delete_entity_instead_of_bookchapter; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_bookchapter AFTER DELETE ON data.bookchapter FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: document delete_entity_instead_of_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_document AFTER DELETE ON data.document FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: library delete_entity_instead_of_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_document AFTER DELETE ON data.library FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: institution delete_entity_instead_of_institution; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_institution AFTER DELETE ON data.institution FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: journal delete_entity_instead_of_journal; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_journal AFTER DELETE ON data.journal FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: journal_issue delete_entity_instead_of_journal_issue; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_journal_issue AFTER DELETE ON data.journal_issue FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: manuscript delete_entity_instead_of_manuscript; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_manuscript AFTER DELETE ON data.manuscript FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: monastery delete_entity_instead_of_monastery; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_monastery AFTER DELETE ON data.monastery FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: node delete_entity_instead_of_node; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_node AFTER DELETE ON data.node FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: person delete_entity_instead_of_person; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_person AFTER DELETE ON data.person FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: phd delete_entity_instead_of_phd; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_phd AFTER DELETE ON data.phd FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: reconstructed_poem delete_entity_instead_of_reconstructed_poem; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_reconstructed_poem AFTER DELETE ON data.reconstructed_poem FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: region delete_entity_instead_of_region; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER delete_entity_instead_of_region AFTER DELETE ON data.region FOR EACH ROW EXECUTE FUNCTION data.delete_entity();


--
-- Name: article ensure_article_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_article_has_document BEFORE INSERT ON data.article FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: bib_varia ensure_bib_varia_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_bib_varia_has_document BEFORE INSERT ON data.bib_varia FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: blog ensure_blog_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_blog_has_document BEFORE INSERT ON data.blog FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: blog_post ensure_blog_post_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_blog_post_has_document BEFORE INSERT ON data.blog_post FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: book_cluster ensure_book_cluster_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_book_cluster_has_document BEFORE INSERT ON data.book_cluster FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: book ensure_book_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_book_has_document BEFORE INSERT ON data.book FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: book_series ensure_book_series_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_book_series_has_document BEFORE INSERT ON data.book_series FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: bookchapter ensure_bookchapter_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_bookchapter_has_document BEFORE INSERT ON data.bookchapter FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: document ensure_document_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_document_has_identity BEFORE INSERT ON data.document FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();


--
-- Name: fund ensure_fund_has_location; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_fund_has_location AFTER INSERT ON data.fund FOR EACH ROW EXECUTE FUNCTION data.ensure_fund_has_location();


--
-- Name: institution ensure_institution_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_institution_has_identity BEFORE INSERT ON data.institution FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();


--
-- Name: institution ensure_institution_has_location; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_institution_has_location AFTER INSERT ON data.institution FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_has_location();


--
-- Name: journal ensure_journal_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_journal_has_document BEFORE INSERT ON data.journal FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: journal_issue ensure_journal_issue_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_journal_issue_has_document BEFORE INSERT ON data.journal_issue FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: keyword ensure_keyword_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_keyword_has_identity BEFORE INSERT ON data.keyword FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();


--
-- Name: library ensure_library_has_institution; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_library_has_institution BEFORE INSERT ON data.library FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_presence();


--
-- Name: manuscript ensure_manuscript_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_manuscript_has_document BEFORE INSERT ON data.manuscript FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: monastery ensure_monastery_has_institution; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_monastery_has_institution BEFORE INSERT ON data.monastery FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_presence();


--
-- Name: node ensure_node_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_node_has_identity BEFORE INSERT ON data.node FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();


--
-- Name: online_source ensure_online_source_has_institution; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_online_source_has_institution BEFORE INSERT ON data.online_source FOR EACH ROW EXECUTE FUNCTION data.ensure_institution_presence();


--
-- Name: original_poem ensure_original_poem_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_original_poem_has_identity BEFORE INSERT ON data.original_poem FOR EACH ROW EXECUTE FUNCTION data.ensure_poem_presence();


--
-- Name: person ensure_person_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_person_has_identity BEFORE INSERT ON data.person FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();


--
-- Name: phd ensure_phd_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_phd_has_document BEFORE INSERT ON data.phd FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: poem ensure_poem_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_poem_has_identity BEFORE INSERT ON data.poem FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: reconstructed_poem ensure_reconstructed_poem_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_reconstructed_poem_has_identity BEFORE INSERT ON data.reconstructed_poem FOR EACH ROW EXECUTE FUNCTION data.ensure_poem_presence();


--
-- Name: region ensure_region_has_identity; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_region_has_identity BEFORE INSERT ON data.region FOR EACH ROW EXECUTE FUNCTION data.ensure_entity_presence();


--
-- Name: region ensure_region_has_location; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_region_has_location AFTER INSERT ON data.region FOR EACH ROW EXECUTE FUNCTION data.ensure_region_has_location();


--
-- Name: translation ensure_translation_has_document; Type: TRIGGER; Schema: data; Owner: -
--

CREATE TRIGGER ensure_translation_has_document BEFORE INSERT ON data.translation FOR EACH ROW EXECUTE FUNCTION data.ensure_document_presence();


--
-- Name: article fk_article_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.article
    ADD CONSTRAINT fk_article_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: bib_varia fk_bib_varia_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bib_varia
    ADD CONSTRAINT fk_bib_varia_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: bibrole fk_bibrole_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bibrole
    ADD CONSTRAINT fk_bibrole_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: bibrole fk_bibrole_person; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bibrole
    ADD CONSTRAINT fk_bibrole_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: bibrole fk_bibrole_role; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bibrole
    ADD CONSTRAINT fk_bibrole_role FOREIGN KEY (idrole) REFERENCES data.role(idrole) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: blog fk_blog_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.blog
    ADD CONSTRAINT fk_blog_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: blog_post fk_blog_post_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.blog_post
    ADD CONSTRAINT fk_blog_post_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: book fk_book_book_cluster; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book
    ADD CONSTRAINT fk_book_book_cluster FOREIGN KEY (idcluster) REFERENCES data.book_cluster(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: book fk_book_book_series; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book
    ADD CONSTRAINT fk_book_book_series FOREIGN KEY (idseries) REFERENCES data.book_series(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: book_cluster fk_book_cluster_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book_cluster
    ADD CONSTRAINT fk_book_cluster_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: book fk_book_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book
    ADD CONSTRAINT fk_book_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: book_series fk_book_series_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.book_series
    ADD CONSTRAINT fk_book_series_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: bookchapter fk_bookchapter_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.bookchapter
    ADD CONSTRAINT fk_bookchapter_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_acknowledgement fk_document_acknowledgement_acknowledgement; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_acknowledgement
    ADD CONSTRAINT fk_document_acknowledgement_acknowledgement FOREIGN KEY (idacknowledgement) REFERENCES data.acknowledgement(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: document_acknowledgement fk_document_acknowledgement_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_acknowledgement
    ADD CONSTRAINT fk_document_acknowledgement_document FOREIGN KEY (iddocument) REFERENCES data.entity(identity);


--
-- Name: document_contains fk_document_contains_container; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_contains
    ADD CONSTRAINT fk_document_contains_container FOREIGN KEY (idcontainer) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_contains fk_document_contains_content; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_contains
    ADD CONSTRAINT fk_document_contains_content FOREIGN KEY (idcontent) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document fk_document_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document
    ADD CONSTRAINT fk_document_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_genre fk_document_genre_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_genre
    ADD CONSTRAINT fk_document_genre_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_genre fk_document_genre_genre; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_genre
    ADD CONSTRAINT fk_document_genre_genre FOREIGN KEY (idgenre) REFERENCES data.genre(idgenre) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_group fk_document_group_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_group
    ADD CONSTRAINT fk_document_group_document FOREIGN KEY (iddocument) REFERENCES data.document(identity);


--
-- Name: document_group fk_document_group_group; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_group
    ADD CONSTRAINT fk_document_group_group FOREIGN KEY (idgroup) REFERENCES data.node(identity);


--
-- Name: document_image fk_document_image_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_image
    ADD CONSTRAINT fk_document_image_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_image fk_document_image_image; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_image
    ADD CONSTRAINT fk_document_image_image FOREIGN KEY (idimage) REFERENCES data.image(idimage) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_keyword fk_document_keyword_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_keyword
    ADD CONSTRAINT fk_document_keyword_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_keyword fk_document_keyword_keyword; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_keyword
    ADD CONSTRAINT fk_document_keyword_keyword FOREIGN KEY (idkeyword) REFERENCES data.keyword(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_status fk_document_status_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_status
    ADD CONSTRAINT fk_document_status_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_status fk_document_status_status; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_status
    ADD CONSTRAINT fk_document_status_status FOREIGN KEY (idstatus) REFERENCES data.status(idstatus) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_title fk_document_title_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_title
    ADD CONSTRAINT fk_document_title_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_title fk_document_title_language; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.document_title
    ADD CONSTRAINT fk_document_title_language FOREIGN KEY (idlanguage) REFERENCES data.language(idlanguage);


--
-- Name: entity_management fk_entity_management_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity_management
    ADD CONSTRAINT fk_entity_management_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: entity_management fk_entity_management_management; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity_management
    ADD CONSTRAINT fk_entity_management_management FOREIGN KEY (idmanagement) REFERENCES data.management(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: entity_url fk_entity_url_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.entity_url
    ADD CONSTRAINT fk_entity_url_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: evidence_factoid fk_evidence_factoid_evidence; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.evidence_factoid
    ADD CONSTRAINT fk_evidence_factoid_evidence FOREIGN KEY (idevidence) REFERENCES data.evidence(idevidence);


--
-- Name: evidence_factoid fk_evidence_factoid_factoid; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.evidence_factoid
    ADD CONSTRAINT fk_evidence_factoid_factoid FOREIGN KEY (idfactoid) REFERENCES data.factoid(idfactoid);


--
-- Name: evidence fk_evidence_reference; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.evidence
    ADD CONSTRAINT fk_evidence_reference FOREIGN KEY (idreference) REFERENCES data.reference(idreference);


--
-- Name: factoid fk_factoid_factoid_type_new; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_factoid_type_new FOREIGN KEY (idfactoid_type) REFERENCES data.factoid_type(idfactoid_type);


--
-- Name: factoid fk_factoid_location; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_location FOREIGN KEY (idlocation) REFERENCES data.location(idlocation);


--
-- Name: factoid fk_factoid_object; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_object FOREIGN KEY (object_identity) REFERENCES data.entity(identity);


--
-- Name: factoid fk_factoid_subject; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid
    ADD CONSTRAINT fk_factoid_subject FOREIGN KEY (subject_identity) REFERENCES data.entity(identity);


--
-- Name: factoid_type fk_factoid_type_inverse; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.factoid_type
    ADD CONSTRAINT fk_factoid_type_inverse FOREIGN KEY (idinverse) REFERENCES data.factoid_type(idfactoid_type) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: fund fk_fund_library; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.fund
    ADD CONSTRAINT fk_fund_library FOREIGN KEY (idlibrary) REFERENCES data.library(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: genre fk_genre_genre; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.genre
    ADD CONSTRAINT fk_genre_genre FOREIGN KEY (idparentgenre) REFERENCES data.genre(idgenre) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: genre fk_genre_person; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.genre
    ADD CONSTRAINT fk_genre_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: global_id fk_global_id_authority; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.global_id
    ADD CONSTRAINT fk_global_id_authority FOREIGN KEY (idauthority) REFERENCES data.entity(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: global_id fk_global_id_subject; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.global_id
    ADD CONSTRAINT fk_global_id_subject FOREIGN KEY (idsubject) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: institution fk_institution_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.institution
    ADD CONSTRAINT fk_institution_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: journal fk_journal_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.journal
    ADD CONSTRAINT fk_journal_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: journal_issue fk_journal_issue_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.journal_issue
    ADD CONSTRAINT fk_journal_issue_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: journal_issue fk_journal_journal_issue; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.journal_issue
    ADD CONSTRAINT fk_journal_journal_issue FOREIGN KEY (idjournal) REFERENCES data.journal(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: keyword fk_keyword_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.keyword
    ADD CONSTRAINT fk_keyword_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reconstructed_poem_lemma fk_lemma_reconstructed_poem; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reconstructed_poem_lemma
    ADD CONSTRAINT fk_lemma_reconstructed_poem FOREIGN KEY (id_reconstructed_poem) REFERENCES data.reconstructed_poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: library fk_library_institution; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.library
    ADD CONSTRAINT fk_library_institution FOREIGN KEY (identity) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: located_at fk_located_at_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.located_at
    ADD CONSTRAINT fk_located_at_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: located_at fk_located_at_location; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.located_at
    ADD CONSTRAINT fk_located_at_location FOREIGN KEY (idlocation) REFERENCES data.location(idlocation) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: location fk_location_fund; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.location
    ADD CONSTRAINT fk_location_fund FOREIGN KEY (idfund) REFERENCES data.fund(idfund) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: location fk_location_institution; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.location
    ADD CONSTRAINT fk_location_institution FOREIGN KEY (idinstitution) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: location fk_location_region; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.location
    ADD CONSTRAINT fk_location_region FOREIGN KEY (idregion) REFERENCES data.region(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: manuscript fk_manuscript_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.manuscript
    ADD CONSTRAINT fk_manuscript_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: monastery fk_monastery_institution; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.monastery
    ADD CONSTRAINT fk_monastery_institution FOREIGN KEY (identity) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: name fk_name_person; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.name
    ADD CONSTRAINT fk_name_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: name fk_name_transliterationsystem; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.name
    ADD CONSTRAINT fk_name_transliterationsystem FOREIGN KEY (idtransliterationsystem) REFERENCES data.transliterationsystem(idtransliterationsystem);


--
-- Name: node fk_node_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.node
    ADD CONSTRAINT fk_node_document FOREIGN KEY (iddocument) REFERENCES data.document(identity);


--
-- Name: node fk_node_parent_node; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.node
    ADD CONSTRAINT fk_node_parent_node FOREIGN KEY (idparentnode) REFERENCES data.node(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: occupation fk_occupation_region; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.occupation
    ADD CONSTRAINT fk_occupation_region FOREIGN KEY (idregion) REFERENCES data.region(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: online_source fk_online_source_institution; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.online_source
    ADD CONSTRAINT fk_online_source_institution FOREIGN KEY (identity) REFERENCES data.institution(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: original_poem fk_original__poem_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.original_poem
    ADD CONSTRAINT fk_original__poem_document FOREIGN KEY (identity) REFERENCES data.poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: original_poem_verse fk_original_poem_verse_original_poem; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.original_poem_verse
    ADD CONSTRAINT fk_original_poem_verse_original_poem FOREIGN KEY (idoriginal_poem) REFERENCES data.original_poem(identity) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: person_email fk_person_email_person; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_email
    ADD CONSTRAINT fk_person_email_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: person fk_person_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person
    ADD CONSTRAINT fk_person_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: person_occupation fk_person_occupation_occupation; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_occupation
    ADD CONSTRAINT fk_person_occupation_occupation FOREIGN KEY (idoccupation) REFERENCES data.occupation(idoccupation) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: person_occupation fk_person_occupation_person; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_occupation
    ADD CONSTRAINT fk_person_occupation_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: person_self_designation fk_person_self_designation_person; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_self_designation
    ADD CONSTRAINT fk_person_self_designation_person FOREIGN KEY (idperson) REFERENCES data.person(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: person_self_designation fk_person_self_designation_self_designation; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.person_self_designation
    ADD CONSTRAINT fk_person_self_designation_self_designation FOREIGN KEY (idself_designation) REFERENCES data.self_designation(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: phd fk_phd_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.phd
    ADD CONSTRAINT fk_phd_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: poem fk_poem_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.poem
    ADD CONSTRAINT fk_poem_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: poem_meter fk_poem_meter_meter; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.poem_meter
    ADD CONSTRAINT fk_poem_meter_meter FOREIGN KEY (idmeter) REFERENCES data.meter(idmeter) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: poem_meter fk_poem_meter_poem; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.poem_meter
    ADD CONSTRAINT fk_poem_meter_poem FOREIGN KEY (idpoem) REFERENCES data.poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reconstructed_poem fk_reconstructed_poem_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reconstructed_poem
    ADD CONSTRAINT fk_reconstructed_poem_document FOREIGN KEY (identity) REFERENCES data.poem(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reference fk_reference_reference_type; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference
    ADD CONSTRAINT fk_reference_reference_type FOREIGN KEY (idreference_type) REFERENCES data.reference_type(idreference_type) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: reference fk_reference_source; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference
    ADD CONSTRAINT fk_reference_source FOREIGN KEY (idsource) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reference fk_reference_target; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.reference
    ADD CONSTRAINT fk_reference_target FOREIGN KEY (idtarget) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: region fk_region_entity; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.region
    ADD CONSTRAINT fk_region_entity FOREIGN KEY (identity) REFERENCES data.entity(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: translation fk_translation_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.translation
    ADD CONSTRAINT fk_translation_document FOREIGN KEY (identity) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: translation fk_translation_language; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.translation
    ADD CONSTRAINT fk_translation_language FOREIGN KEY (idlanguage) REFERENCES data.language(idlanguage) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: translation_of fk_translation_of_document; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.translation_of
    ADD CONSTRAINT fk_translation_of_document FOREIGN KEY (iddocument) REFERENCES data.document(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: translation_of fk_translation_of_translation; Type: FK CONSTRAINT; Schema: data; Owner: -
--

ALTER TABLE ONLY data.translation_of
    ADD CONSTRAINT fk_translation_of_translation FOREIGN KEY (idtranslation) REFERENCES data.translation(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: contributor_of fk_contributor_of_user; Type: FK CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.contributor_of
    ADD CONSTRAINT fk_contributor_of_user FOREIGN KEY (iduser) REFERENCES logic.user_old(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: revision_old fk_revision_fos_user; Type: FK CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.revision_old
    ADD CONSTRAINT fk_revision_fos_user FOREIGN KEY (iduser) REFERENCES logic.fos_user(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: working_on fk_working_on_user; Type: FK CONSTRAINT; Schema: logic; Owner: -
--

ALTER TABLE ONLY logic.working_on
    ADD CONSTRAINT fk_working_on_user FOREIGN KEY (iduser) REFERENCES logic.user_old(identity) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--


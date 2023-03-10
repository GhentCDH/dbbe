ALTER SCHEMA data OWNER TO dbbe;
ALTER SCHEMA julie OWNER TO dbbe;
ALTER SCHEMA julie_before_2019_12_11 OWNER TO dbbe;
ALTER SCHEMA logic OWNER TO dbbe;
ALTER SCHEMA migration OWNER TO dbbe;
ALTER SCHEMA public OWNER TO dbbe;

ALTER TABLE data."acknowledgement" OWNER TO dbbe;
ALTER TABLE data."article" OWNER TO dbbe;
ALTER TABLE data."bib_varia" OWNER TO dbbe;
ALTER TABLE data."bibrole" OWNER TO dbbe;
ALTER TABLE data."blog" OWNER TO dbbe;
ALTER TABLE data."blog_post" OWNER TO dbbe;
ALTER TABLE data."book" OWNER TO dbbe;
ALTER TABLE data."book_cluster" OWNER TO dbbe;
ALTER TABLE data."book_series" OWNER TO dbbe;
ALTER TABLE data."bookchapter" OWNER TO dbbe;
ALTER TABLE data."document" OWNER TO dbbe;
ALTER TABLE data."document_acknowledgement" OWNER TO dbbe;
ALTER TABLE data."document_contains" OWNER TO dbbe;
ALTER TABLE data."document_genre" OWNER TO dbbe;
ALTER TABLE data."document_group" OWNER TO dbbe;
ALTER TABLE data."document_image" OWNER TO dbbe;
ALTER TABLE data."document_keyword" OWNER TO dbbe;
ALTER TABLE data."document_status" OWNER TO dbbe;
ALTER TABLE data."document_title" OWNER TO dbbe;
ALTER TABLE data."entity" OWNER TO dbbe;
ALTER TABLE data."entity_management" OWNER TO dbbe;
ALTER TABLE data."entity_url" OWNER TO dbbe;
ALTER TABLE data."evidence" OWNER TO dbbe;
ALTER TABLE data."evidence_factoid" OWNER TO dbbe;
ALTER TABLE data."factoid" OWNER TO dbbe;
ALTER TABLE data."factoid_type" OWNER TO dbbe;
ALTER TABLE data."fund" OWNER TO dbbe;
ALTER TABLE data."genre" OWNER TO dbbe;
ALTER TABLE data."global_id" OWNER TO dbbe;
ALTER TABLE data."identifier" OWNER TO dbbe;
ALTER TABLE data."image" OWNER TO dbbe;
ALTER TABLE data."institution" OWNER TO dbbe;
ALTER TABLE data."journal" OWNER TO dbbe;
ALTER TABLE data."journal_issue" OWNER TO dbbe;
ALTER TABLE data."keyword" OWNER TO dbbe;
ALTER TABLE data."language" OWNER TO dbbe;
ALTER TABLE data."library" OWNER TO dbbe;
ALTER TABLE data."located_at" OWNER TO dbbe;
ALTER TABLE data."location" OWNER TO dbbe;
ALTER TABLE data."management" OWNER TO dbbe;
ALTER TABLE data."manuscript" OWNER TO dbbe;
ALTER TABLE data."meter" OWNER TO dbbe;
ALTER TABLE data."monastery" OWNER TO dbbe;
ALTER TABLE data."name" OWNER TO dbbe;
ALTER TABLE data."node" OWNER TO dbbe;
ALTER TABLE data."occupation" OWNER TO dbbe;
ALTER TABLE data."online_source" OWNER TO dbbe;
ALTER TABLE data."original_poem" OWNER TO dbbe;
ALTER TABLE data."original_poem_verse" OWNER TO dbbe;
ALTER TABLE data."person" OWNER TO dbbe;
ALTER TABLE data."person_email" OWNER TO dbbe;
ALTER TABLE data."person_occupation" OWNER TO dbbe;
ALTER TABLE data."person_self_designation" OWNER TO dbbe;
ALTER TABLE data."phd" OWNER TO dbbe;
ALTER TABLE data."poem" OWNER TO dbbe;
ALTER TABLE data."poem_meter" OWNER TO dbbe;
ALTER TABLE data."reconstructed_poem" OWNER TO dbbe;
ALTER TABLE data."reference" OWNER TO dbbe;
ALTER TABLE data."reference_type" OWNER TO dbbe;
ALTER TABLE data."region" OWNER TO dbbe;
ALTER TABLE data."role" OWNER TO dbbe;
ALTER TABLE data."self_designation" OWNER TO dbbe;
ALTER TABLE data."status" OWNER TO dbbe;
ALTER TABLE data."translation" OWNER TO dbbe;
ALTER TABLE data."translation_of" OWNER TO dbbe;
ALTER TABLE data."transliterationsystem" OWNER TO dbbe;
ALTER TABLE julie."poemannotation" OWNER TO dbbe;
ALTER TABLE julie."substringannotation" OWNER TO dbbe;
ALTER TABLE julie_before_2019_12_11."poemannotation" OWNER TO dbbe;
ALTER TABLE julie_before_2019_12_11."substringannotation" OWNER TO dbbe;
ALTER TABLE logic."contributor_of" OWNER TO dbbe;
ALTER TABLE logic."feedback" OWNER TO dbbe;
ALTER TABLE logic."fos_user" OWNER TO dbbe;
ALTER TABLE logic."news_event" OWNER TO dbbe;
ALTER TABLE logic."page" OWNER TO dbbe;
ALTER TABLE logic."revision" OWNER TO dbbe;
ALTER TABLE logic."revision_2019_05_15" OWNER TO dbbe;
ALTER TABLE logic."revision_old" OWNER TO dbbe;
ALTER TABLE logic."user" OWNER TO dbbe;
ALTER TABLE logic."user_old" OWNER TO dbbe;
ALTER TABLE logic."working_on" OWNER TO dbbe;
ALTER TABLE migration."biblio_article_to_entity" OWNER TO dbbe;
ALTER TABLE migration."biblio_book_to_entity" OWNER TO dbbe;
ALTER TABLE migration."biblio_contribution_to_entity" OWNER TO dbbe;
ALTER TABLE migration."biblio_online_source_to_entity" OWNER TO dbbe;
ALTER TABLE migration."content_to_genre" OWNER TO dbbe;
ALTER TABLE migration."genres_to_genre" OWNER TO dbbe;
ALTER TABLE migration."keywords_to_keyword" OWNER TO dbbe;
ALTER TABLE migration."location_funds_to_fund" OWNER TO dbbe;
ALTER TABLE migration."location_library_to_library" OWNER TO dbbe;
ALTER TABLE migration."location_places_to_region" OWNER TO dbbe;
ALTER TABLE migration."manuscript_statuses_status" OWNER TO dbbe;
ALTER TABLE migration."manuscripts_to_manuscript" OWNER TO dbbe;
ALTER TABLE migration."occurrence_text_status_to_status" OWNER TO dbbe;
ALTER TABLE migration."occurrence_to_entity" OWNER TO dbbe;
ALTER TABLE migration."occurrences_record_statuses_to_status" OWNER TO dbbe;
ALTER TABLE migration."origin_location_to_monastery" OWNER TO dbbe;
ALTER TABLE migration."origin_region_to_region" OWNER TO dbbe;
ALTER TABLE migration."persons_functions_occupation" OWNER TO dbbe;
ALTER TABLE migration."persons_to_person" OWNER TO dbbe;
ALTER TABLE migration."persons_types" OWNER TO dbbe;
ALTER TABLE migration."relationships_description_factoidtypes" OWNER TO dbbe;
ALTER TABLE migration."subjects_to_keyword" OWNER TO dbbe;
ALTER TABLE migration."type_to_reconstructed_poem" OWNER TO dbbe;
ALTER TABLE migration."types_text_statuses_to_status" OWNER TO dbbe;
ALTER TABLE migration."users_to_user" OWNER TO dbbe;
ALTER TABLE public."audit_log" OWNER TO dbbe;
ALTER TABLE public."biblio_articles" OWNER TO dbbe;
ALTER TABLE public."biblio_books" OWNER TO dbbe;
ALTER TABLE public."biblio_contributions" OWNER TO dbbe;
ALTER TABLE public."biblio_objects" OWNER TO dbbe;
ALTER TABLE public."biblio_online_sources" OWNER TO dbbe;
ALTER TABLE public."chars" OWNER TO dbbe;
ALTER TABLE public."content" OWNER TO dbbe;
ALTER TABLE public."genres" OWNER TO dbbe;
ALTER TABLE public."keywords" OWNER TO dbbe;
ALTER TABLE public."lists" OWNER TO dbbe;
ALTER TABLE public."location_funds" OWNER TO dbbe;
ALTER TABLE public."location_libraries" OWNER TO dbbe;
ALTER TABLE public."location_places" OWNER TO dbbe;
ALTER TABLE public."manuscripts" OWNER TO dbbe;
ALTER TABLE public."manuscripts_bibliography" OWNER TO dbbe;
ALTER TABLE public."manuscripts_link_persons" OWNER TO dbbe;
ALTER TABLE public."manuscripts_statuses" OWNER TO dbbe;
ALTER TABLE public."meters" OWNER TO dbbe;
ALTER TABLE public."occasions" OWNER TO dbbe;
ALTER TABLE public."occurrences" OWNER TO dbbe;
ALTER TABLE public."occurrences_bibliography" OWNER TO dbbe;
ALTER TABLE public."occurrences_genres" OWNER TO dbbe;
ALTER TABLE public."occurrences_keywords" OWNER TO dbbe;
ALTER TABLE public."occurrences_person_patron" OWNER TO dbbe;
ALTER TABLE public."occurrences_person_production_backlog" OWNER TO dbbe;
ALTER TABLE public."occurrences_person_scribe" OWNER TO dbbe;
ALTER TABLE public."occurrences_person_scribe_backlog" OWNER TO dbbe;
ALTER TABLE public."occurrences_record_statuses" OWNER TO dbbe;
ALTER TABLE public."occurrences_relationships" OWNER TO dbbe;
ALTER TABLE public."occurrences_subjects" OWNER TO dbbe;
ALTER TABLE public."occurrences_text_statuses" OWNER TO dbbe;
ALTER TABLE public."origin_location" OWNER TO dbbe;
ALTER TABLE public."origin_region" OWNER TO dbbe;
ALTER TABLE public."pages" OWNER TO dbbe;
ALTER TABLE public."persons" OWNER TO dbbe;
ALTER TABLE public."persons_functions" OWNER TO dbbe;
ALTER TABLE public."persons_link_functions" OWNER TO dbbe;
ALTER TABLE public."persons_link_types" OWNER TO dbbe;
ALTER TABLE public."persons_types" OWNER TO dbbe;
ALTER TABLE public."relationships_description_types" OWNER TO dbbe;
ALTER TABLE public."subjects" OWNER TO dbbe;
ALTER TABLE public."types" OWNER TO dbbe;
ALTER TABLE public."types_bibliography" OWNER TO dbbe;
ALTER TABLE public."types_genres" OWNER TO dbbe;
ALTER TABLE public."types_keywords" OWNER TO dbbe;
ALTER TABLE public."types_occurrences_text_source" OWNER TO dbbe;
ALTER TABLE public."types_person_poet" OWNER TO dbbe;
ALTER TABLE public."types_relationships" OWNER TO dbbe;
ALTER TABLE public."types_sources" OWNER TO dbbe;
ALTER TABLE public."types_subjects" OWNER TO dbbe;
ALTER TABLE public."types_text_statuses" OWNER TO dbbe;
ALTER TABLE public."users" OWNER TO dbbe;
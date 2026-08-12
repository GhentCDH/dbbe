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
-- Data for Name: factoid_type; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.factoid_type (description, "group", idfactoid_type, type, idinverse) FROM stdin;
\N	\N	1	completed at	\N
\N	\N	2	reconstruction of	\N
\N	\N	3	located at	\N
\N	\N	4	written	\N
\N	\N	8	related to	\N
\N	\N	9	subject of	\N
\N	\N	10	origination	\N
\N	\N	11	based on	\N
\N	reconstructed_poem_related_to_reconstructed_poem	13	Variant (permutation of words)	\N
\N	reconstructed_poem_related_to_reconstructed_poem	14	Variant (other metre)	\N
\N	reconstructed_poem_related_to_reconstructed_poem	15	Variant (other subject)	\N
\N	reconstructed_poem_related_to_reconstructed_poem	17	Same cycle	\N
\N	reconstructed_poem_related_to_reconstructed_poem	18	Variant (other wordings)	\N
\N	reconstructed_poem_related_to_reconstructed_poem	19	Unknown	\N
\N	\N	20	Appears immediately after	\N
\N	\N	21	died	\N
\N	\N	22	born	\N
\N	\N	23	attested	\N
\N	reconstructed_poem_related_to_reconstructed_poem	12	Is part of	16
\N	reconstructed_poem_related_to_reconstructed_poem	16	Consists of	12
\.


--
-- Data for Name: genre; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.genre (idgenre, idparentgenre, genre, description, is_content, idperson) FROM stdin;
1	\N	DBBE system	This genre was created to group all original "poem genres".	\N	\N
2	1	Scribe-related epigram	Colophon	f	\N
3	1	Patron-related epigram	Dedicatory	f	\N
4	1	Author-related epigram	Laudatory	f	\N
5	1	Reader-related epigram	Paraenetic	f	\N
6	1	Text-related epigram	Title	f	\N
7	1	Image-related epigram	Miniature	f	\N
8	\N	Biblica	\N	t	\N
9	14	Pentateuchus	\N	t	\N
10	14	Octateuchus	\N	t	\N
11	14	Psalterium	\N	t	\N
12	14	Prophetae	\N	t	\N
13	\N	Proverbia	\N	t	\N
14	8	Vetus Testamentum	\N	t	\N
15	18	Evangeliarium	\N	t	\N
16	18	Acta Apostolorum	\N	t	\N
17	\N	Epistulae	\N	t	\N
18	8	Novum Testamentum	\N	t	\N
19	\N	Theologica	\N	t	\N
35	19	Miscellanea	\N	t	\N
36	\N	Liturgica	\N	t	\N
37	36	Lectionarium	\N	t	\N
38	36	Synaxarium	\N	t	\N
39	36	Menaea	\N	t	\N
40	36	Euchologium	\N	t	\N
41	36	Hymnica	\N	t	\N
42	\N	Hagiographica	\N	t	\N
43	42	Vitae Sanctorum	\N	t	\N
44	42	Menologium	\N	t	\N
45	\N	Philosophica	\N	t	\N
48	45	Neo-platonici	\N	t	\N
49	45	Miscellanea	\N	t	\N
50	\N	Scientia	\N	t	\N
51	50	Medica	\N	t	\N
52	50	Physica	\N	t	\N
53	50	Astrologica, Arithmetica	\N	t	\N
55	50	Miscellanea	\N	t	\N
56	\N	Rhetorica	\N	t	\N
58	56	Rhetores Attici	\N	t	\N
59	56	Miscellanea	\N	t	\N
60	\N	Grammatica	\N	t	\N
62	60	Historica	\N	t	\N
63	\N	Epistolographica	\N	t	\N
67	63	Miscellanea	\N	t	\N
68	\N	Poetica	\N	t	\N
69	\N	Ilias	\N	t	\N
70	\N	Odyssea	\N	t	\N
71	68	Tragici	\N	t	\N
73	68	Miscellanea	\N	t	\N
74	\N	Juridica	\N	t	\N
75	74	Jurisprudentia	\N	t	\N
76	74	Canones	\N	t	\N
77	\N	Varia	\N	t	\N
78	\N	Gnomologia	\N	t	\N
79	\N	Lexica	\N	t	\N
80	\N	Miscellanea	\N	t	\N
81	19	Alia	\N	t	\N
82	\N	Historiographica	\N	t	\N
83	\N	Erotica	\N	t	\N
84	68	Alia	\N	t	\N
85	56	Alia	\N	t	\N
86	50	Geographica	\N	t	\N
87	36	Sticherarium	\N	t	\N
88	\N	Didactica	\N	t	\N
89	88	Dioptra	\N	t	\N
90	36	Typikon	\N	t	\N
91	19	Catenae	\N	t	\N
94	68	Digenes Akritas	\N	t	\N
95	56	Rhetorici	\N	t	\N
97	45	Neo-aristotelici	\N	t	\N
102	88	Schedographia	\N	t	\N
106	82	Alia	\N	t	\N
109	8	Pandectes	\N	t	\N
111	88	Hypomnema	\N	t	\N
112	36	Divinae Liturgiae	\N	t	\N
114	36	Octoechus	\N	t	\N
117	36	Horologium	\N	t	\N
118	36	Theotokarion	\N	t	\N
119	36	Heirmologium	\N	t	\N
123	36	Pentecostarium	\N	t	\N
126	36	Paracletice	\N	t	\N
131	36	Triodion	\N	t	\N
133	50	Musica	\N	t	\N
136	36	Musicae Anthologiae	\N	t	\N
138	36	Praxapostolus	\N	t	\N
204	18	Epistulae catholicae	\N	t	\N
205	18	Apocalypsis	\N	t	\N
207	\N	Canticum Canticorum	\N	t	\N
208	211	Regnorum	\N	t	\N
209	14	Libri sapientiae	\N	t	\N
210	36	Miscellanea	\N	t	\N
211	14	Libri historici	\N	t	\N
213	50	Arithmetica	\N	t	\N
215	50	Astrologica, Astronomica	\N	t	\N
222	50	Geometria	\N	t	\N
225	\N	Florilegia	\N	t	\N
244	50	Alchemica	\N	t	\N
276	36	Psaltikon	\N	t	\N
\.


--
-- Data for Name: identifier; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.identifier (ididentifier, type, system_name, name, ids, regex, description, is_primary, "order", link, created, modified, extra, link_type, extra_required) FROM stdin;
9	{type}	ptb	Paratexts of the Bible (Pinakes)	{4}	^[\\d]+$	E.g., "17761" (without quotes)	t	2	https://pinakes.irht.cnrs.fr/notices/oeuvre/	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	online_source	f
16	{manuscript}	manuscripta_biblica	Paratexts of the Bible (manuscripta biblica)	{31754}	^[\\d]+$	E.g., "16302" (without quotes)	f	1	https://www.manuscripta-biblica.org/manuscript/?diktyon=	2020-09-01 12:02:08.88674+00	2020-09-01 12:02:08.88674+00	f	online_source	f
2	{person}	vgh	VGH	{10107}	^[\\d]+[.][A-Z]$	E.g., "158.D" (without quotes)	t	1	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	book	f
7	{type}	vassis_2005	Vassis ICB 2005	{10723}	^(?:[\\d]+|[\\d]+[-][\\d]+)$	E.g., "46" or "313-314" (without quotes)	t	3	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	t	book	t
8	{type}	vassis_2011	Vassis ICB 2011	{8290}	^(?:[\\d]+|[\\d]+[-][\\d]+)$	E.g., "222" or "227-228" (without quotes)	t	4	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	t	article	t
13	{book,article}	olivier_1995	Olivier 1995	{11520}	^[\\d]{1,4}$	E.g., "327" (without quotes)	f	0	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	book	f
14	{book,article}	olivier_2018	Olivier 2018	{11521,11522}	^[\\d]{1,4}[a-z]{0,2}$	E.g., "903" or "2115na" (without quotes)	f	0	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	book	f
12	{type}	rhoby	BEiÜ IV	{11460}	^[A-ZÄÖÜ]{1,3}[\\d]{1,3}(?:\\([\\d]+[a-z]?\\))?$	E.g., "FR15(8a)" (without quotes)	t	1	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	book	f
1	{person}	rgk	RGK	{10821,10824,10213}	^[0-9]+[a-z]*$	E.g., "191" (without quotes)	t	0	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	book	f
10	{type}	ap	Anthologia Graeca	{10335,10335,10335,10335,10335,10335,10761,10761,10067,10067,10067,10763,10763,10763,10763,10763}	^[0-9]+[a-z]?(?: [(][\\d\\s\\w,-.]+[)])?$	E.g., "1 (vv.372-376)" (without quotes)	t	0	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	book	f
4	{person}	pbe	PBE	{5}	^[\\d]+$	E.g., "7013" (without quotes)	t	2	http://www.pbe.kcl.ac.uk/person/p	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	online_source	f
3	{person}	pbw	PBW	{3}	^[\\w]+[/][\\d]+[/]$	E.g., "Alexios/1/" (without quotes)	t	3	https://pbw2016.kdl.kcl.ac.uk/person/	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	online_source	f
15	{person}	plp	PLP	{29909,29910,29911,29914,29912,29913,29916,29917,29918,29920,29921,29922}	^[\\d]+$	E.g., "91128" (without quotes)	t	4	\N	2020-02-25 13:18:43.582736+00	2020-02-25 13:18:43.582736+00	f	book	f
5	{person}	pmbz	PMBZ	{6}	^[\\d]+$	E.g., "16302" (without quotes)	t	5	https://www.degruyter.com/view/PMBZ/PMBZ	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	t	online_source	f
11	{book,bookChapter,article,phd}	vassis	Vassis	{8291}	^[\\d\\s\\w,\\/.]+$	E.g., "Dipt 3, 1982/1983" (without quotes)	f	0	\N	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	person	f
6	{manuscript}	diktyon	Diktyon (Pinakes)	{1}	^[\\d]+$	E.g., "16302" (without quotes)	t	0	https://pinakes.irht.cnrs.fr/notices/cote/	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	online_source	f
17	{person}	pinakes_person	Pinakes (Person)	{11538}	^(CPA|A|S)\\.\\d{1,5}$	Use <b>CPA.111</b> for copiste, possesseur et autre, use <b>A.111</b> for auteur and use <b>S.111</b> for saints.	t	6	\N	2025-04-01 09:21:02.820344+00	2025-04-01 09:21:02.820344+00	f	online_source	f
\.


--
-- Data for Name: language; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.language (idlanguage, name, code, description) FROM stdin;
1	Unknown	?	This should not be present but is used during migration / data cleanup.
2	Greek	GR	\N
3	English	EN	\N
4	Latin	LA	
5	French	FR	
6	Italian	IT	
7	German	DE	
8	Modern Greek	EL	
9	Spanish	ES	
10	Russian	RU	
11	Bulgarian	BG	
12	Dutch	NL	
13	Danish	DA	
14	Portuguese	PT	
15	Polish	PL	
16	Swedish	SV	
17	Hungarian	HU	\N
\.


--
-- Data for Name: meter; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.meter (idmeter, name) FROM stdin;
1	Dodecasyllable
2	Dactylic hexameter
3	Elegiacs
5	Other
6	Mixture
7	Hymnography
8	Iambic trimeter
9	Rhythmical prose
4	Decapentasyllable
10	Octosyllable
11	Heptasyllable
\.


--
-- Data for Name: occupation; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.occupation (idoccupation, occupation, created, modified, idparentoccupation, idregion) FROM stdin;
57781	anagnostes	2010-11-08 11:24:48+00	2019-05-15 11:49:44.269431+00	\N	\N
57782	monachos	2010-11-08 13:40:08+00	2019-05-15 11:49:44.269431+00	\N	\N
57783	hamartolos	2010-11-08 14:04:08+00	2019-05-15 11:49:44.269431+00	\N	\N
57784	proximos scholes parthenou	2010-11-09 11:41:47+00	2019-05-15 11:49:44.269431+00	\N	\N
\.


--
-- Data for Name: reference_type; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.reference_type (idreference_type, type) FROM stdin;
1	Text source
2	Primary source
3	Secondary source
4	To be revised
\.


--
-- Data for Name: role; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.role (idrole, type, system_name, name, created, modified, is_contributor_role, has_rank, "order") FROM stdin;
3	{manuscript,occurrence}	patron	Patron	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	f	\N
4	{manuscript,occurrence}	scribe	Scribe	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	f	\N
5	{manuscript}	related	Related	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	f	\N
6	{type}	poet	Poet	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	f	\N
8	{occurrence,type,manuscript}	creator	Creator	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	t	f	0
9	{occurrence}	transcriber	Transcriber	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	t	f	1
10	{occurrence,type,manuscript}	contributor	Contributor	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	t	f	2
1	{book,bookChapter,article,blogPost,phd,bibVaria}	author	Author	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	t	\N
11	{phd,bibVaria}	supervisor	Supervisor	2020-09-18 14:20:11.412389+00	2020-09-18 14:20:11.412389+00	f	t	\N
7	{book,bookChapter}	editor	Editor	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	t	\N
2	{book,translation}	translator	Translator	2019-05-15 11:49:44.269431+00	2019-05-15 11:49:44.269431+00	f	t	\N
12	{manuscript}	owner	Owner	2024-12-09 08:43:39.087796+00	2024-12-09 08:43:39.087796+00	f	f	\N
13	{manuscript}	illuminator	Illuminator	2024-12-09 08:45:37.796397+00	2024-12-09 08:45:37.796397+00	f	f	\N
\.


--
-- Data for Name: self_designation; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.self_designation (id, name) FROM stdin;
3	μέγας δούξ
5	διάκονος
1	μοναχός
4	ἅναξ
6	ἀναγνώστης
7	ἀρχιεπίσκοπος
8	ἀρχηγέτης
9	ἀρχιθύτης
10	ἄρχων
\.


--
-- Data for Name: status; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.status (idstatus, status, type) FROM stdin;
1	Autopsy of the manuscript	occurrence_source
2	Inspection of a reproduction of the manuscript	occurrence_source
3	Inspection of a microfilm of the manuscript	occurrence_source
4	Bibliography	occurrence_source
5	Critical text	type_critical
6	Not a critical text	type_critical
7	Text completely known	type_text
8	Text partially unknown	type_text
12	Text completely unknown	type_text
19	Text partially unknown	occurrence_text
20	Text completely unknown	occurrence_text
21	Text completely known	occurrence_text
22	Information from catalogue has been entered	occurrence_record
23	Information has been checked against other sources	occurrence_record
24	Manuscript has been viewed	occurrence_record
\.


--
-- Data for Name: transliterationsystem; Type: TABLE DATA; Schema: data; Owner: -
--

COPY data.transliterationsystem (idtransliterationsystem, name) FROM stdin;
\.


--
-- Name: factoid_type_idfactoid_type_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.factoid_type_idfactoid_type_seq', 23, true);


--
-- Name: genre_idgenre_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.genre_idgenre_seq', 297, true);


--
-- Name: identifier_ididentifier_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.identifier_ididentifier_seq', 17, true);


--
-- Name: language_idlanguage_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.language_idlanguage_seq', 17, true);


--
-- Name: meter_idmeter_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.meter_idmeter_seq', 13, true);


--
-- Name: occupation_idoccupation_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.occupation_idoccupation_seq', 58229, true);


--
-- Name: reference_type_idreference_type_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.reference_type_idreference_type_seq', 4, true);


--
-- Name: role_idrole_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.role_idrole_seq', 13, true);


--
-- Name: self_designation_id_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.self_designation_id_seq', 331, true);


--
-- Name: status_idstatus_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.status_idstatus_seq', 28, true);


--
-- Name: transliterationsystem_idtransliterationsystem_seq; Type: SEQUENCE SET; Schema: data; Owner: -
--

SELECT pg_catalog.setval('data.transliterationsystem_idtransliterationsystem_seq', 1, false);


--
-- Synthetic data: acknowledgement, management
-- (the real tables carry real contributors' names / internal workflow
-- labels, so these are hand-written placeholders instead of a data dump)
--

COPY data.acknowledgement (id, acknowledgement) FROM stdin;
1	Information on the type courtesy of Test Scholar.
2	Inspection of the manuscript image was possible by courtesy of the Test Institute.
\.

SELECT pg_catalog.setval('data.acknowledgement_id_seq', 2, true);

COPY data.management (id, name) FROM stdin;
1	Test collection
2	Needs review
\.

SELECT pg_catalog.setval('data.management_id_seq', 2, true);


--
-- PostgreSQL database dump complete
--


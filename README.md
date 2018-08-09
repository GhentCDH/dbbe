DBBE
====

A Symfony / Elasticsearch project.

An SQL query for the creation of the fos_user table can be obtained with the command
```
php bin/console doctrine:schema:create --dump-sql
```

Data structure
--------------
### Manuscript
Level|Fields|Dependent on
---|---|---
Mini|<ul><li>id</li><li>locatedAt</li><li>public (EntityManager)</li></ul>|<ul><li>Location (locatedAt)</li></ul>
Short|<ul><li>content</li><li>personRoles (DocumentManager)</li><li>occurrencePersonRole</li><li>dates (DocumentManager)</li><li>origin</li><li>comments (EntityManager)</li></ul>|<ul><li>Content</li><li>Person short (personRole (DocumentManager))</li><li>Occurrence mini (occurrencePersonRole)</li><li>Person short (occurrencePersonRole)</li><li>Location (origin)</li></ul>
Full|<ul><li>identification</li><li>bibliographies (DocumentManager)</li><li>occurrences</li><li>status</li><li>illustrated</li></ul>|<ul><li>Identifier (identification (EntityManager))</li><li>Bibliography</li><li>Occurrence mini</li><li>Status</li></ul>

### Occurrence
Level|Fields|Dependent on
---|---|---
Mini|<ul><li>id</li><li>foliumStart</li><li>foliumStartRecto</li><li>foliumEnd</li><li>foliumEndRecto</li><li>incipit</li><li>public (EntityManager)</li></ul>|
Short|<ul><li>manuscript</li><li>title</li><li>text</li><li>meter</li><li>subject</li><li>personRoles (DocumentManager)</li><li>dates (DocumentManager)</li><li>genre</li><li>comments (EntityManager)</li><li>textStatus</li><li>recordStatus</li><li>bibliographies</li></ul>|<ul><li>Manuscript short</li><li>Person short (subject)</li><li>Keyword (subject)</li><li>Person short (personRoles (DocumentManager))</li><li>Genre</li><li>Status</li><li>Bibliography</li></ul>
Full|<ul><li>types</li><li>paleographicalInfo</li><li>contextualInfo</li><li>verses</li><li>imagelink</li><li>image</li></ul>|<ul><li>Type mini</li></ul>

### Person
Level|Fields|Dependent on
---|---|---
Mini|<ul><li>id</li><li>firstName</li><li>lastName</li><li>extra</li><li>unprocessed</li><li>bornDate</li><li>deathDate</li><li>historical</li><li>modern</li><li>identification (EntityManager)</li><li>public (EntityManager)</li></ul>|<ul><li>Identifier (identification (EntityManager))</li></ul>
Short|<ul><li>roles</li><li>offices</li><li>comments (EntityManager)</li></ul>|<ul><li>Role</li><li>Office</li></ul>
Full|<ul><li>manuscriptRole</li><li>occurrenceManuscriptrole</li></ul>|<ul><li>Manuscript mini (manuscriptRole)</li><li>Manuscript mini (occurrenceManuscriptrole)</li><li>Occurrence mini (occurrenceManuscriptrole)</li></ul>

# Steps necessary to switch from user id to user email in revision table

## Revision

### Add user email column to revision table

```sql
ALTER TABLE logic.revision
ADD user_email VARCHAR(254);
```

### Add user email addresses to revision table

```sql
UPDATE logic.revision
SET user_email = "user".username
FROM
logic.user
WHERE revision.iduser = "user".id;
```

### Set user_email in revision table as not null

```sql
ALTER TABLE logic.revision
ALTER COLUMN user_email SET NOT NULL;
```

### Remove user id column from revision table

```sql
ALTER TABLE logic.revision
DROP COLUMN iduser;
```

## page

### Add user email column to page table

```sql
ALTER TABLE logic.page
ADD user_email VARCHAR(254);
```

### Add user email addresses to page table

```sql
UPDATE logic.page
SET user_email = "user".username
FROM
logic.user
WHERE page.iduser = "user".id;
```

### Set user_email in page table as not null

```sql
ALTER TABLE logic.page
ALTER COLUMN user_email SET NOT NULL;
```

### Remove user id column from page table

```sql
ALTER TABLE logic.page
DROP COLUMN iduser;
```

## news_event

### Add user email column to news_event table

```sql
ALTER TABLE logic.news_event
ADD user_email VARCHAR(254);
```

### Add user email addresses to news_event table

```sql
UPDATE logic.news_event
SET user_email = "user".username
FROM
logic.user
WHERE news_event.iduser = "user".id;
```

### Set user_email in news_event table as not null

```sql
ALTER TABLE logic.news_event
ALTER COLUMN user_email SET NOT NULL;
```

### Remove user id column from news_event table

```sql
ALTER TABLE logic.news_event
DROP COLUMN iduser;
```

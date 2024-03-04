# Steps necessary to switch from user id to user email in revision table

## Add user email column to revision table

```sql
ALTER TABLE logic.revision
ADD user_email VARCHAR(254);
```

## Add user email addresses to revision table

```sql
UPDATE logic.revision
SET user_email = "user".username
FROM
logic.user
WHERE revision.iduser = "user".id;
```

## Set user_email as not null

```sql
ALTER TABLE logic.revision
ALTER COLUMN user_email SET NOT NULL;
```

## Remove user id column from revision table

```sql
ALTER TABLE logic.revision
DROP COLUMN iduser;
```

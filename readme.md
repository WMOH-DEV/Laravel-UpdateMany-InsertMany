# Laravel Update Many

Laravel batch update a collection of eloquent models.
Perform single query for batch update collection or arrays.
It will also update `created_at` and `updated_at` column of the models

## Installation

```
composer require waelmoh/laravel-update-many
```

## Usage

### Update Many

Update array or collection of models. This perform a single update query for all the passed models.
Only the dirty or changed attributes will be included in the update.
This updates the `updated_at` column of the models and the tables.


```php
$users = [
   [
      'id'         => 31,
      'first_name' => 'John',
      'last_name'  => 'Doe',
      'email'      => 'John@Doe.com'

   ],
   [
      'id'         => 32,
      'first_name' => 'Hubert',
      'last_name'  => 'Wiza',
      'email'      => 'Hubert@Wiza.org'
   ],
   [
      'id'         => 33,
      'first_name' => 'Mikayla',
      'last_name'  => 'Keeling',
      'email'      => 'Mikayla.hyatt@example.com'
      ]
]

```

```php
User::updateMany($users); // update many models using id as the default key
User::updateMany($users, 'id'); // same as above
User::updateMany($users, 'username'); // use username as key instead of id

```

#### Specifying which columns to be updated

```php
User::updateMany($users, 'id', ['email', 'first_name', 'last_name']);
```

#### How it works

This will produce a query like this:

```sql
UPDATE
   `users`
SET
   `email` =
   CASE
      WHEN
         `id` = '31'
      THEN
         'John@Doe.com'
      WHEN
         `id` = '32'
      THEN
         'Hubert@Wiza.org'
      WHEN
         `id` = '33'
      THEN
         'Mikayla.hyatt@example.com'
      ELSE
         `email`
   END
, `first_name` =
   CASE
      WHEN
         `id` = '31'
      THEN
         'John'
      WHEN
         `id` = '32'
      THEN
         'Hubert'
      WHEN
         `id` = '33'
      THEN
         'Mikayla'
      ELSE
         `first_name`
   END
, `last_name` =
   CASE
      WHEN
         `id` = '31'
      THEN
         'Doe'
      WHEN
         `id` = '32'
      THEN
         'Wiza'
      WHEN
         `id` = '33'
      THEN
         'Keeling'
      ELSE
         `last_name`
   END
WHERE
   `id` IN
   (
      31, 32, 33
   );
```


#### Rights

##### This package inspired, forked from [ajcastro] and developed by [waelmoh].
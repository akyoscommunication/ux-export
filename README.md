Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
composer require akyos/ux-export
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require akyos/ux-export
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Akyos\UXExportBundle\UXExportBundle::class => ['all' => true],
];
```

Configuration
-------------

The bundle writes generated files under `ux_export.path`. The default path is
`%kernel.project_dir%/var/export/`. It can be overridden in
`config/packages/ux_export.yaml`:

```yaml
ux_export:
    path: '%kernel.project_dir%/var/export/'
```

Defining Exportable Entities
----------------------------

Mark your entities with `#[Exportable]` and use `#[ExportableProperty]` to
control what is exported. Properties or methods tagged with these attributes
are listed in the export when their `groups` option matches the group passed to
the exporter. You may also rely on Symfony's `#[Groups]` attribute as a
fallback.

Usage of Exportable Attributes
------------------------------

`ExportableProperty` exposes several options:

- `groups`: serializer groups allowed for this column.
- `name`: override the column header.
- `position`: integer used to order columns.
- `fields`: extract sub-fields from a related entity.
 - `fields`: extract sub-fields from a related entity. When omitted, fields having the same group on the related entity are exported automatically.
- `manyToMany`: set to `lines` to duplicate rows for each relation or to
  `sheet` to create an additional worksheet listing the intermediate table.

Example:

```php
#[ExportableProperty(groups: ['export'], fields: ['name', 'email'], manyToMany: ExportableProperty::MODE_SHEET)]
private Collection $users;
```

### Exporting Nested Fields

The `fields` option allows you to export specific properties from a related entity. If omitted, the exporter will automatically include every property of the child entity that belongs to the selected group:

```php
#[Exportable]
class Order
{
    #[ExportableProperty(groups: ['export'], fields: ['firstName', 'lastName'])]
    private ?Customer $customer = null;
}
```

### Many-to-many Relations

Use the `manyToMany` option when dealing with collections:

```php
// duplicate one row per related entity
#[ExportableProperty(groups: ['export'], manyToMany: ExportableProperty::MODE_LINES)]
private Collection $tags;

// or create a dedicated worksheet listing the relations
#[ExportableProperty(groups: ['export'], manyToMany: ExportableProperty::MODE_SHEET)]
private Collection $roles;
```

### Export Values from Methods

Methods can also be exported:

```php
#[Exportable]
class User
{
    #[ExportableProperty(groups: ['export'], name: 'Full name', position: 1)]
    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }
}
```

Live Component Integration
--------------------------

Include `ComponentWithExportTrait` in a Symfony UX Live Component. Implement
`getData()` to provide the dataset (an array, a Doctrine `QueryBuilder` or a
`Query`). Set the `$class` property so the trait can read your entity metadata:

```php
use Akyos\UXExportBundle\Trait\ComponentWithExportTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent]
class UserTableComponent
{
    use ComponentWithExportTrait;

    public string $class = User::class;
    public ?string $exportGroup = 'default';

    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getData(): iterable
    {
        return $this->repository->createQueryBuilder('u')->getQuery();
    }
}
```

Calling the `export` action generates the file and redirects the browser to the
download route provided by the bundle.

### Export Formats

The trait supports two formats controlled by the `$exportType` property:

- `xlsx` (default)
- `csv`

When exporting to CSV, a file is created for each worksheet. If some
`ExportableProperty` fields use the `manyToMany` option set to `sheet`, an
additional CSV is generated for that relation and all files are zipped together.

### Using CSV Export

Set the trait's `$exportType` property to `csv` and optionally customize
`$exportFileName` when you prefer CSV instead of XLSX:

```php
#[AsLiveComponent]
class UserTableComponent
{
    use ComponentWithExportTrait;

    public string $class = User::class;
    public string $exportType = 'csv';
    public string $exportFileName = 'users';
    public ?string $exportGroup = 'default';

    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getData(): iterable
    {
        return $this->repository->createQueryBuilder('u')->getQuery();
    }
}
```

The bundle will generate a single CSV by default. If one of your
`ExportableProperty` definitions uses `manyToMany: ExportableProperty::MODE_SHEET`,
multiple CSV files will be produced and automatically zipped.


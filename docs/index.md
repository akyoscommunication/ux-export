
# UX Export Bundle

This bundle provides utilities to export data from Symfony UX Live Components
using PhpSpreadsheet. Refer to the project README for installation details.

Key features:

* `ExporterService` for creating XLSX files
* `CsvExporterService` for exporting CSV or zipped CSV files
* `ComponentWithExportTrait` to easily add an `export` action
* Attributes to configure which entity properties are exported
* Set `$exportType` to `csv` for plain CSV output or zipped files when a relation uses the `sheet` mode
* Choose the serializer group at runtime with the `$exportGroup` property

See the README for detailed examples of `Exportable` and `ExportableProperty`.

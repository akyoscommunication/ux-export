<?php

namespace Akyos\UXExportBundle\Service;

use Akyos\UXExportBundle\Attribute\ExportableProperty;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\BaseWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ExporterService
{
    public function getWriter(string $format, Spreadsheet $spreadsheet = null): BaseWriter
    {
        return match ($format) {
            'xlsx' => new Xlsx($spreadsheet ?? new Spreadsheet()),
            default => throw new \InvalidArgumentException("Unsupported format: $format"),
        };
    }

    public function generateMatrix(Spreadsheet $spreadsheet, array $properties): void
    {
        foreach ($properties as $index => $property) {
            $name = $this->getPropertyName($property);
            $spreadsheet->getActiveSheet()->setCellValue([$index + 1, 1], $name);
        }
    }

    public function populateData(Spreadsheet $spreadsheet, array $data, array $properties): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($data as $rowIndex => $item) {
            foreach ($properties as $colIndex => $property) {
                $value = $propertyAccessor->getValue($item, $property->getName());
                $spreadsheet->getActiveSheet()->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
            }
        }
    }

    public function manyToManyLines(iterable $collection, string $property): string
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $values = [];

        foreach ($collection as $item) {
            $values[] = $accessor->getValue($item, $property);
        }

        return implode("\n", $values);
    }

    public function manyToManySheet(Spreadsheet $spreadsheet, iterable $collection, string $property, string $sheetName): void
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $sheet = new Worksheet($spreadsheet, $sheetName);
        $spreadsheet->addSheet($sheet);

        foreach ($collection as $rowIndex => $item) {
            $value = $accessor->getValue($item, $property);
            $sheet->setCellValue([1, $rowIndex + 1], $value);
        }
    }

    private function getPropertyName(\ReflectionProperty|\ReflectionMethod $property): string
    {
        $attributeClass = ExportableProperty::class;
        $attributes = $property->getAttributes($attributeClass);

        if (!empty($attributes) && $attributes[0]->newInstance()->name) {
            return $attributes[0]->newInstance()->name;
        }

        return $property->getName();
    }
}

<?php

namespace Akyos\UXExportBundle\Service;

use ReflectionProperty;
use Doctrine\ORM\Mapping\ManyToMany;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\BaseWriter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Akyos\UXExportBundle\Attribute\ExportableProperty;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\ManyToOne;

class ExporterService
{
    public function getWriter(string $format, Spreadsheet $spreadsheet = null): BaseWriter
    {
        return match ($format) {
            'xlsx' => new Xlsx($spreadsheet ?? new Spreadsheet()),
            default => throw new \InvalidArgumentException("Unsupported format: $format"),
        };
    }

    public function generateMatrix(Spreadsheet $spreadsheet, array $properties, array $data = [], ?string $group = null): void
    {
        dump('=== DÉBUT generateMatrix ===');
        dump('Nombre de propriétés:', count($properties));
        dump('Group:', $group);
        
        $columnIndex = 1;
        foreach ($properties as $property) {
            $attribute = $this->getAttribute($property);
            dump('Propriété dans generateMatrix:', $property->getName());
            dump('Attribute dans generateMatrix:', $attribute);

            if ($attribute && $attribute->manyToMany === ExportableProperty::MODE_SHEET) {
                dump('Propriété SHEET ignorée dans generateMatrix');
                continue;
            }

            if ($class = $this->getRelationTargetClassFromAttributes($property)) {
                dump('Classe cible détectée:', $class);
                $fields = $this->getFieldsFromEntity($class, $group);
                dump('Champs de la classe cible:', $fields);
                if (empty($fields)) {
                    $fields = [null];
                }
            } else {
                dump('Pas de classe cible détectée, utilisation de getRelatedFields');
                $fields = $this->getRelatedFields($property, $data, $group);
                if (empty($fields)) {
                    $fields = [null];
                }
            }

            foreach ($fields as $field) {
                $name = $this->getPropertyName($property, $field);
                dump('Nom de colonne généré:', $name);
                $spreadsheet->getActiveSheet()->setCellValue([$columnIndex, 1], $name);
                $columnIndex++;
            }
        }
        
        dump('=== FIN generateMatrix ===');
    }

    public function populateData(Spreadsheet $spreadsheet, array $data, array $properties, ?string $group = null): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $rowIndex = 2;
        foreach ($data as $item) {
            $lines = $this->getLinesCount($item, $properties, $propertyAccessor);
            for ($line = 0; $line < $lines; $line++) {
                $columnIndex = 1;
                foreach ($properties as $property) {
                    $attribute = $this->getAttribute($property);

                    if ($attribute && $attribute->manyToMany === ExportableProperty::MODE_SHEET) {
                        continue;
                    }

                    if ($property instanceof \ReflectionMethod) {
                        $value = $property->invoke($item);
                    } else {
                        $value = $propertyAccessor->getValue($item, $property->getName());
                    }
                    
                    if ($attribute && $attribute->manyToMany === ExportableProperty::MODE_LINES) {
                        $value = ($value[$line] ?? null);
                    }

                    $fields = $this->getRelatedFields($property, [$value], $group);
                    
                    $values = $this->extractValues($value, $fields, $propertyAccessor, $group);


                    foreach ($values as $val) {
                        $spreadsheet->getActiveSheet()->setCellValue([$columnIndex, $rowIndex], $val);
                        $columnIndex++;
                    }
                }
                $rowIndex++;
            }
        }

        $this->populateManyToManySheets($spreadsheet, $data, $properties, $propertyAccessor, $group);
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

    private function getPropertyName(\ReflectionProperty|\ReflectionMethod $property, ?string $subField = null): string
    {
        $attribute = $this->getAttribute($property);

        $name = $attribute?->name ?? $property->getName();

        if ($subField) {
            return sprintf('%s_%s', $name, $subField);
        }

        return $name;
    }

    private function getAttribute(\ReflectionProperty|\ReflectionMethod $property): ?ExportableProperty
    {
        $attributes = $property->getAttributes(ExportableProperty::class);

        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }

    private function extractValues(mixed $value, ?array $fields, $accessor, ?string $group): array
    {
        if ($fields) {
            $vals = [];
            foreach ($fields as $field) {
                $vals[] = $value ? $accessor->getValue($value, $field) : null;
            }

            return $vals;
        }

        if (is_object($value)) {
            $fields = $this->getFieldsFromEntity(get_class($value), $group);
            if ($fields) {
                $vals = [];
                foreach ($fields as $field) {
                    $vals[] = $accessor->getValue($value, $field);
                }

                return $vals;
            }
        }

        return [$value];
    }

    private function getLinesCount(mixed $item, array $properties, $accessor): int
    {
        $max = 1;
        foreach ($properties as $property) {
            $attribute = $this->getAttribute($property);
            if ($attribute && $attribute->manyToMany === ExportableProperty::MODE_LINES) {
                $collection = $accessor->getValue($item, $property->getName());
                $count = 0;
                if (is_iterable($collection)) {
                    foreach ($collection as $_) {
                        $count++;
                    }
                }
                $max = max($max, $count);
            }
        }

        return $max;
    }

    private function populateManyToManySheets(Spreadsheet $spreadsheet, array $data, array $properties, $accessor, ?string $group): void
    {
        dump('=== DÉBUT populateManyToManySheets ===');
        dump('Nombre de propriétés à traiter:', count($properties));
        
        foreach ($properties as $property) {
            $attribute = $this->getAttribute($property);
            dump('Propriété:', $property->getName());
            dump('Attribute:', $attribute);
            
            if (!$attribute || $attribute->manyToMany !== ExportableProperty::MODE_SHEET) {
                dump('Propriété ignorée - pas MODE_SHEET ou pas d\'attribute');
                continue;
            }

            dump('Propriété MODE_SHEET trouvée!');
            $sheet = new Worksheet($spreadsheet, $this->getPropertyName($property));
            $spreadsheet->addSheet($sheet);

            $headerIndex = 1;
            $sheet->setCellValue([1, 1], 'row');
            
            $targetClass = $this->getRelationTargetClassFromAttributes($property);
            if ($targetClass) {
                $fields = $this->getFieldsFromEntity($targetClass, $group);
            } else {
                $fields = $this->getRelatedFields($property, $data, $group);
            }
            
            dump('Champs détectés pour la feuille:', $fields);
            
            foreach ($fields as $field) {
                $sheet->setCellValue([$headerIndex + 1, 1], $field);
                $headerIndex++;
            }

            $rowIndex = 2;
            foreach ($data as $i => $item) {
                $collection = $accessor->getValue($item, $property->getName());
                dump('Collection pour item', $i, ':', $collection);
                dump('Type de collection:', gettype($collection));
                dump('Est itérable:', is_iterable($collection));
                
                if (!is_iterable($collection)) {
                    dump('Collection non itérable, on passe');
                    continue;
                }
                
                $collectionCount = 0;
                foreach ($collection as $element) {
                    dump('Élément de la collection:', $element);
                    $sheet->setCellValue([1, $rowIndex], $i + 1);
                    $colIndex = 2;
                    foreach ($fields as $field) {
                        $fieldValue = $accessor->getValue($element, $field);
                        dump('Valeur du champ', $field, ':', $fieldValue);
                        $sheet->setCellValue([$colIndex, $rowIndex], $fieldValue);
                        $colIndex++;
                    }
                    $rowIndex++;
                    $collectionCount++;
                }
                dump('Nombre d\'éléments traités pour cet item:', $collectionCount);
            }
        }
        
        dump('=== FIN populateManyToManySheets ===');
    }

    private function getRelatedFields(\ReflectionProperty|\ReflectionMethod $property, array $data, ?string $group): array
    {
        $class = $this->detectClass($property, $data);
        if (!$class) {
            return [];
        }

        return $this->getFieldsFromEntity($class, $group);
    }

    private function detectClass(\ReflectionProperty|\ReflectionMethod $property, array $data): ?string
    {
        if ($property instanceof \ReflectionProperty) {
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $property = $this->getAttribute($property, ManyToMany::class);
                return $type->getName();
            }
        } elseif ($property instanceof \ReflectionMethod) {
            $type = $property->getReturnType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                return $type->getName();
            }
        }
        foreach ($data as $item) {
            if (!\is_object($item) && !\is_array($item)) {
                continue;
            }

            if ($property instanceof \ReflectionProperty) {
                try {
                    $val = PropertyAccess::createPropertyAccessor()->getValue($item, $property->getName());
                } catch (\Throwable) {
                    continue;
                }
            } else {
                if (!\is_object($item)) {
                    continue;
                }

                try {
                    $val = $property->invoke($item);
                } catch (\Throwable) {
                    continue;
                }
            }

            if (is_iterable($val)) {
                foreach ($val as $element) {
                    if (is_object($element)) {
                        return get_class($element);
                    }
                }
            } elseif (is_object($val)) {
                return get_class($val);
            }
        }

        return null;
    }

    private function getFieldsFromEntity(string $class, ?string $group): array
    {
        $reflection = new \ReflectionClass($class);
        $fields = [];
        foreach ($reflection->getProperties() as $prop) {
            $attr = $prop->getAttributes(ExportableProperty::class);
            if (!$attr) {
                continue;
            }
            $inst = $attr[0]->newInstance();
            if ($group === null || in_array($group, $inst->groups)) {
                $fields[] = $prop->getName();
            }
        }

        return $fields;
    }

    function getRelationTargetClassFromAttributes(ReflectionProperty $property): ?string
    {
        $relationAttributes = [
            OneToOne::class,
            OneToMany::class,
            ManyToOne::class,
            ManyToMany::class,
        ];

        foreach ($relationAttributes as $relationClass) {
            $attributes = $property->getAttributes($relationClass);
            if (!empty($attributes)) {
                $instance = $attributes[0]->newInstance();
                return $instance->targetEntity ?? null;
            }
        }

        return null;
    }
}

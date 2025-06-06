<?php

namespace Akyos\UXExportBundle\Trait;

use Akyos\UXExportBundle\Attribute\Exportable;
use Akyos\UXExportBundle\Attribute\ExportableProperty;
use Akyos\UXExportBundle\Service\ExporterService;
use Akyos\UXExportBundle\Service\CsvExporterService;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

trait ComponentWithExportTrait
{
    #[LiveProp(writable: true)]
    public string $exportType = 'xlsx';

    #[LiveProp(writable: true)]
    public string $exportFileName = 'export';

    #[LiveProp(writable: true)]
    public ?string $exportGroup = null;

    #[LiveProp(writable: true)]
    public ?string $class = '';

    #[LiveAction]
    public function export(ExporterService $exporterService, CsvExporterService $csvExporterService, UrlGeneratorInterface $urlGenerator, ContainerInterface $container): RedirectResponse
    {
        $this->validateExportClass();

        $properties = $this->getProperties();
        $data = $this->getExportData();

        $filesystem = new Filesystem();
        $path = $container->getParameter('ux_export.path');

        try {
            if (!$filesystem->exists($path)) {
                $filesystem->mkdir($path, 0777, true);
            }

            if (!is_writable($path)) {
                throw new \RuntimeException(sprintf('The directory "%s" is not writable.', $path));
            }
        } catch (IOExceptionInterface $exception) {
            throw new \RuntimeException(sprintf('An error occurred while creating your directory at "%s"', $exception->getPath()), 0, $exception);
        }

        if ($this->exportType === 'csv') {
            $fileName = $csvExporterService->export($data, $properties, $path, $this->exportFileName, $this->exportGroup);
        } else {
            $writer = $exporterService->getWriter($this->exportType);
            $exporterService->generateMatrix($writer->getSpreadsheet(), $properties, $data, $this->exportGroup);
            $exporterService->populateData($writer->getSpreadsheet(), $data, $properties, $this->exportGroup);
            $fileName = rtrim($path, '/') . '/' . $this->exportFileName . '.' . $this->exportType;
            $writer->save($fileName);
        }

        $url = $urlGenerator->generate('ux_export.download', ['path' => $fileName]);

        return new RedirectResponse($url);
    }

    private function validateExportClass(): void
    {
        $this->validateClass(Exportable::class, 'Exportable');
    }

    private function validateClass(string $attributeClass, string $attributeName): void
    {
        if ($this->class === '') {
            throw new \Exception('You must set the class property');
        }

        $reflectionClass = new ReflectionClass($this->class);
        if (!$reflectionClass->getAttributes($attributeClass)) {
            throw new \Exception("The class must have the $attributeName attribute");
        }
    }

    private function getProperties(): array
    {
        $reflectionClass = new ReflectionClass($this->class);
        $group = $this->exportGroup;

        $properties = $this->extractProperties($reflectionClass, $group);
        $methods = $this->extractMethods($reflectionClass, $group);

        $combined = array_merge($properties, $methods);
        usort($combined, fn($a, $b) => $a['position'] <=> $b['position']);

        return array_map(fn($item) => $item['property'], $combined);
    }

    private function extractProperties(ReflectionClass $reflectionClass, ?string $group): array
    {
        $properties = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $attributeClass = ExportableProperty::class;
            $exportableProperty = $property->getAttributes($attributeClass);

            if ($this->shouldIncludeProperty($property, $group, $exportableProperty)) {
                $position = !empty($exportableProperty) ? $exportableProperty[0]->newInstance()->position : null;
                $properties[] = ['property' => $property, 'position' => $position];
            }
        }
        return $properties;
    }

    private function extractMethods(ReflectionClass $reflectionClass, ?string $group): array
    {
        $methods = [];
        foreach ($reflectionClass->getMethods() as $method) {
            $attributeClass = ExportableProperty::class;
            $exportableProperty = $method->getAttributes($attributeClass);

            if ($this->shouldIncludeProperty($method, $group, $exportableProperty)) {
                $position = !empty($exportableProperty) ? $exportableProperty[0]->newInstance()->position : null;
                $methods[] = ['property' => $method, 'position' => $position];
            }
        }
        return $methods;
    }

    private function shouldIncludeProperty($property, ?string $group, array $exportableProperty): bool
    {
        if (!empty($exportableProperty)) {
            if ($group === null) {
                return true;
            }

            return in_array($group, $exportableProperty[0]->newInstance()->groups);
        }

        $groupsProperty = $property->getAttributes(Groups::class);
        if (!empty($groupsProperty)) {
            if ($group === null) {
                return true;
            }

            return in_array($group, $groupsProperty[0]->newInstance()->getGroups());
        }

        return false;
    }

    private function getExportData(): array
    {
        $data = method_exists($this, 'getData') ? $this->getData() : [];

        if ($data instanceof QueryBuilder) {
            return $data->getQuery()->getResult();
        } elseif ($data instanceof Query) {
            return $data->getResult();
        }

        return $data;
    }
}

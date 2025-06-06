<?php

namespace Akyos\UXExportBundle\Tests\Service;

use Akyos\UXExportBundle\Service\CsvExporterService;
use Akyos\UXExportBundle\Service\ExporterService;
use Akyos\UXExportBundle\Attribute\ExportableProperty;
use Akyos\UXExportBundle\Attribute\Exportable;
use PHPUnit\Framework\TestCase;

class CsvExporterServiceTest extends TestCase
{
    private CsvExporterService $service;

    protected function setUp(): void
    {
        $this->service = new CsvExporterService(new ExporterService());
    }

    public function testExportReturnsCsv(): void
    {
        $data = [new SimpleUser('john', 'john@example.com')];
        $reflection = new \ReflectionClass(SimpleUser::class);
        $properties = $reflection->getProperties();
        $file = $this->service->export($data, $properties, sys_get_temp_dir().'/', 'csv_test', 'default');

        $this->assertFileExists($file);
        $this->assertStringEndsWith('.csv', $file);
        @unlink($file);
    }

    public function testExportWithSheetReturnsZip(): void
    {
        $data = [new UserWithRoles([new Role('admin'), new Role('user')])];
        $reflection = new \ReflectionClass(UserWithRoles::class);
        $properties = $reflection->getProperties();
        $file = $this->service->export($data, $properties, sys_get_temp_dir().'/', 'csv_zip', 'default');

        $this->assertFileExists($file);
        $this->assertStringEndsWith('.zip', $file);
        @unlink($file);
    }

    public function testZipContainsAllCsvs(): void
    {
        $data = [new UserWithRoles([new Role('admin'), new Role('user')])];
        $reflection = new \ReflectionClass(UserWithRoles::class);
        $properties = $reflection->getProperties();
        $file = $this->service->export($data, $properties, sys_get_temp_dir().'/', 'content', 'default');

        $zip = new \ZipArchive();
        $zip->open($file);
        $this->assertSame(2, $zip->numFiles);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        sort($names);
        $this->assertSame(['content.csv', 'content_roles.csv'], $names);
        $zip->close();

        @unlink($file);
    }
}

#[Exportable]
class SimpleUser
{
    #[ExportableProperty(groups: ['default'])]
    public string $username;

    #[ExportableProperty(groups: ['default'])]
    public string $email;

    public function __construct(string $username, string $email)
    {
        $this->username = $username;
        $this->email = $email;
    }
}

#[Exportable]
class UserWithRoles
{
    #[ExportableProperty(groups: ['default'], manyToMany: ExportableProperty::MODE_SHEET)]
    public array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }
}

#[Exportable]
class Role
{
    #[ExportableProperty(groups: ['default'])]
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

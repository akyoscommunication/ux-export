<?php

namespace Akyos\UXExportBundle\Tests\Service;

use Akyos\UXExportBundle\Service\ExporterService;
use Akyos\UXExportBundle\Attribute\ExportableProperty;
use Akyos\UXExportBundle\Attribute\Exportable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class ExporterServiceTest extends TestCase
{
    private ExporterService $service;

    protected function setUp(): void
    {
        $this->service = new ExporterService();
    }

    public function testGenerateMatrixUsesPropertyNames(): void
    {
        $spreadsheet = new Spreadsheet();
        $reflection = new \ReflectionClass(Dummy::class);
        $properties = $reflection->getProperties();

        $this->service->generateMatrix($spreadsheet, $properties, [], 'default');

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('username', $sheet->getCell([1,1])->getValue());
        $this->assertSame('Email', $sheet->getCell([2,1])->getValue());
    }

    public function testManyToManyLines(): void
    {
        $collection = [new Role('admin'), new Role('user')];
        $result = $this->service->manyToManyLines($collection, 'name');
        $this->assertSame("admin\nuser", $result);
    }

    public function testManyToManySheet(): void
    {
        $spreadsheet = new Spreadsheet();
        $collection = [new Role('admin'), new Role('user')];
        $this->service->manyToManySheet($spreadsheet, $collection, 'name', 'roles');

        $sheet = $spreadsheet->getSheetByName('roles');
        $this->assertNotNull($sheet);
        $this->assertSame('admin', $sheet->getCell([1,1])->getValue());
        $this->assertSame('user', $sheet->getCell([1,2])->getValue());
    }

    public function testPopulateDataWithMethod(): void
    {
        $spreadsheet = new Spreadsheet();
        $reflection = new \ReflectionClass(DummyWithMethod::class);
        $properties = array_merge(
            $reflection->getProperties(),
            [$reflection->getMethod('getFullName')]
        );
        $this->service->generateMatrix($spreadsheet, $properties, [], 'default');

        $data = [new DummyWithMethod('John', 'Doe')];
        $this->service->populateData($spreadsheet, $data, $properties, 'default');


        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Full name', $sheet->getCell([3,1])->getValue());
        $this->assertSame('John Doe', $sheet->getCell([3,2])->getValue());
    }

    public function testPopulateDataManyToManyLines(): void
    {
        $spreadsheet = new Spreadsheet();
        $reflection = new \ReflectionClass(UserWithRolesLines::class);
        $properties = $reflection->getProperties();
        $this->service->generateMatrix($spreadsheet, $properties, [], 'default');

        $data = [new UserWithRolesLines([new Role('admin'), new Role('user')])];
        $this->service->populateData($spreadsheet, $data, $properties, 'default');

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame("admin\nuser", $sheet->getCell([1,2])->getValue());
    }

    public function testPopulateDataManyToManySheet(): void
    {
        $spreadsheet = new Spreadsheet();
        $reflection = new \ReflectionClass(UserWithRolesSheet::class);
        $properties = $reflection->getProperties();
        $this->service->generateMatrix($spreadsheet, $properties, [], 'default');

        $data = [new UserWithRolesSheet([new Role('admin'), new Role('user')])];
        $this->service->populateData($spreadsheet, $data, $properties, 'default');


        $sheet = $spreadsheet->getSheetByName('roles');
        $this->assertNotNull($sheet);
        $this->assertSame('row', $sheet->getCell([1,1])->getValue());
        $this->assertSame('name', $sheet->getCell([2,1])->getValue());
        $this->assertSame(1, $sheet->getCell([1,2])->getValue());
        $this->assertSame('admin', $sheet->getCell([2,2])->getValue());
        $this->assertSame(1, $sheet->getCell([1,3])->getValue());
        $this->assertSame('user', $sheet->getCell([2,3])->getValue());
    }
}

#[Exportable]
class Dummy
{
    #[ExportableProperty(groups: ['default'])]
    public string $username;

    #[ExportableProperty(name: 'Email', groups: ['default'])]
    public string $email;
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

#[Exportable]
class DummyWithMethod
{
    #[ExportableProperty(groups: ['default'])]
    public string $firstName;

    #[ExportableProperty(groups: ['default'])]
    public string $lastName;

    #[ExportableProperty(groups: ['default'])]
    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function __construct(string $firstName, string $lastName)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }
}

#[Exportable]
class UserWithRolesLines
{
    #[ExportableProperty(groups: ['default'], manyToMany: ExportableProperty::MODE_LINES)]
    public array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }
}

#[Exportable]
class UserWithRolesSheet
{
    #[ExportableProperty(groups: ['default'], manyToMany: ExportableProperty::MODE_SHEET)]
    public array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }
}

#[Exportable]
class DummyWithMethod
{
    #[ExportableProperty(groups: ['default'])]
    public string $firstName;

    #[ExportableProperty(groups: ['default'])]
    public string $lastName;

    #[ExportableProperty(groups: ['default'])]
    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function __construct(string $firstName, string $lastName)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }
}

#[Exportable]
class UserWithRolesLines
{
    #[ExportableProperty(groups: ['default'], fields: ['name'], manyToMany: ExportableProperty::MODE_LINES)]
    public array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }
}

#[Exportable]
class UserWithRolesSheet
{
    #[ExportableProperty(groups: ['default'], fields: ['name'], manyToMany: ExportableProperty::MODE_SHEET)]
    public array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }
}

<?php

namespace Akyos\UXExportBundle\Attribute;

use Attribute;
use Symfony\Component\Serializer\Attribute\Groups;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class ExportableProperty extends Groups
{
    public function __construct(
        public ?string $name = null,
        public ?array $groups = null,
        public ?int $position = null,
    ) {
        parent::__construct($groups);
    }
}

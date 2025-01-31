<?php

namespace Akyos\UXExportBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Exportable
{
    public function __construct(
        public ?string $group = null
    ) {}
}

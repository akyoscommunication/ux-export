<?php

namespace Akyos\UXExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/ux_export/download', name: 'ux_export.download', requirements: ['path' => '.+'])]
    public function file_response(#[MapQueryParameter] string $path): BinaryFileResponse
    {
        return new BinaryFileResponse($path);
    }
}

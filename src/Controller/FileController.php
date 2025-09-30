<?php

namespace Akyos\UXExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/ux_export/download', name: 'ux_export.download', requirements: ['path' => '.+'])]
    public function file_response(#[MapQueryParameter] string $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($path)
        );

        return $response;
    }
}

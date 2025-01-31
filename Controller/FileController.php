<?php

namespace Akyos\UXExport\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/ux_export/download', name: 'ux_export.download')]
    public function file_response(string $path): BinaryFileResponse
    {
        return new BinaryFileResponse($path);
    }
}

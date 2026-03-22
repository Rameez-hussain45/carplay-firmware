<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SoftwareDownloadController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->redirectToRoute('software_download');
    }

    #[Route('/carplay/software-download', name: 'software_download')]
    public function index(): Response
    {
        return $this->render('carplay/software_download.html.twig');
    }
}

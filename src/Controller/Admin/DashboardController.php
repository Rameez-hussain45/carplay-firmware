<?php

namespace App\Controller\Admin;

use App\Entity\SoftwareVersion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/logo.png" alt="BimmerTech" style="height:28px"> Firmware Admin')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('🏠 Dashboard', 'fa fa-home');

        yield MenuItem::section('Firmware Versions');
        yield MenuItem::linkToCrud('All Versions', 'fa fa-list', SoftwareVersion::class);
        yield MenuItem::linkToCrud('➕ Add New Version', 'fa fa-plus', SoftwareVersion::class)
            ->setAction('new');

        yield MenuItem::section('Tools');
        yield MenuItem::linkToUrl('🔗 Customer Download Page', 'fa fa-external-link', '/carplay/software-download')
            ->setLinkTarget('_blank');
        yield MenuItem::linkToUrl('🚪 Logout', 'fa fa-sign-out', '/admin/logout');
    }
}

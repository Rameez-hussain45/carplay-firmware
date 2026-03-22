<?php

namespace App\Controller\Admin;

use App\Entity\SoftwareVersion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class SoftwareVersionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SoftwareVersion::class;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product names exactly as they appear in the original softwareversions.json
    // ─────────────────────────────────────────────────────────────────────────
    private const PRODUCT_NAMES = [
        // Standard (non-LCI) ──────────────────────
        'MMI Prime CIC'     => 'MMI Prime CIC',
        'MMI Prime NBT'     => 'MMI Prime NBT',
        'MMI Prime EVO'     => 'MMI Prime EVO',
        'MMI PRO CIC'       => 'MMI PRO CIC',
        'MMI PRO NBT'       => 'MMI PRO NBT',
        'MMI PRO EVO'       => 'MMI PRO EVO',
        // LCI (facelift, post-2017) ───────────────
        'LCI MMI Prime CIC' => 'LCI MMI Prime CIC',
        'LCI MMI Prime NBT' => 'LCI MMI Prime NBT',
        'LCI MMI Prime EVO' => 'LCI MMI Prime EVO',
        'LCI MMI PRO CIC'   => 'LCI MMI PRO CIC',
        'LCI MMI PRO NBT'   => 'LCI MMI PRO NBT',
        'LCI MMI PRO EVO'   => 'LCI MMI PRO EVO',
    ];

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Firmware Version')
            ->setEntityLabelInPlural('Firmware Versions')
            ->setPageTitle('index', 'All Firmware Versions')
            ->setPageTitle('new',   'Add New Firmware Version')
            ->setPageTitle('edit',  'Edit Firmware Version')
            ->setPageTitle('detail','Firmware Version Detail')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->setSearchFields(['name', 'systemVersion'])
            ->setPaginatorPageSize(30)
            ->setHelp('new',
                '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:6px;padding:14px 18px;margin-bottom:16px;">'
                . '<strong>How to add a new firmware release:</strong><br>'
                . '1. Select the correct <strong>Product Name</strong> from the dropdown.<br>'
                . '2. Enter the <strong>Software Version</strong> exactly as customers see it in their MMI — <em>no leading "v"</em> (e.g. <code>3.3.8.mmipri.c</code>).<br>'
                . '3. Paste the Google Drive download links. CIC products: ST Link only. NBT/EVO products: both ST and GD links.<br>'
                . '4. <strong>Do NOT tick "Is Latest"</strong> yet — save first.<br>'
                . '5. Then find the <em>previous</em> latest entry for this product, edit it: add its download links and <em>untick</em> "Is Latest".<br>'
                . '6. Come back here and tick <strong>Is Latest</strong>. Save.<br>'
                . '<br><strong style="color:#cf1322">⚠ Wrong links destroy customer hardware. Double-check before saving.</strong>'
                . '</div>'
            );
    }

    public function configureFields(string $pageName): iterable
    {
        // ── List view columns ─────────────────────────────────────────────────
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield ChoiceField::new('name', 'Product')
            ->setChoices(self::PRODUCT_NAMES)
            ->renderExpanded(false)
            ->setHelp(
                'Select the product this firmware belongs to.<br>'
                . '<small>'
                . '<b>CIC</b> = older iDrive with CD drive &nbsp;|&nbsp; '
                . '<b>NBT</b> = mid-gen iDrive &nbsp;|&nbsp; '
                . '<b>EVO</b> = latest iDrive<br>'
                . '<b>LCI</b> = post-2017 facelift vehicles (separate from standard)'
                . '</small>'
            );

        yield TextField::new('systemVersion', 'Software Version')
            ->setHelp(
                'Exact version string from the customer\'s MMI — <strong>without a leading "v"</strong>.<br>'
                . '<small>Example: <code>3.3.7.mmipri.c</code></small>'
            );

        yield BooleanField::new('isLatest', 'Is Latest')
            ->renderAsSwitch(true)
            ->setHelp(
                '<strong>ON</strong> = this is the newest released version. '
                . 'Customers on this version see "Your system is up to date!".<br>'
                . '<strong>Only one entry per product should be ON at any time.</strong>'
            );

        // ── Download links — hidden from list to save space ───────────────────
        yield UrlField::new('stLink', 'ST Download Link')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp(
                'Google Drive link for <strong>ST (SanDisk)</strong> flash chip devices.<br>'
                . 'Required for <strong>CIC</strong> products. Leave empty for NBT/EVO-only products.<br>'
                . 'Leave empty when this is the latest version (no upgrade needed).'
            );

        yield UrlField::new('gdLink', 'GD Download Link')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp(
                'Google Drive link for <strong>GD (GigaDevice)</strong> flash chip devices.<br>'
                . 'Required for <strong>NBT and EVO</strong> products. Leave empty for CIC-only products.<br>'
                . 'Leave empty when this is the latest version (no upgrade needed).'
            );

        yield UrlField::new('link', 'Legacy Link (optional)')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp(
                'Older combined download folder used by some legacy entries. '
                . 'Leave empty for all new versions.'
            );

        // ── Summary indicators shown on the list ──────────────────────────────
        yield TextField::new('stLink', 'ST Link')
            ->onlyOnIndex()
            ->formatValue(static fn ($v) => $v ? '✅' : '—');

        yield TextField::new('gdLink', 'GD Link')
            ->onlyOnIndex()
            ->formatValue(static fn ($v) => $v ? '✅' : '—');

        // ── Sort order — detail / form only ───────────────────────────────────
        yield IntegerField::new('sortOrder', 'Sort Order')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('Lower numbers appear first in this admin list. Has no effect on the customer page.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('name', 'Product')
                    ->setChoices(self::PRODUCT_NAMES)
            )
            ->add(BooleanFilter::new('isLatest', 'Is Latest'));
    }
}
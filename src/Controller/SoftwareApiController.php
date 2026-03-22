<?php

namespace App\Controller;

use App\Repository\SoftwareVersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SoftwareApiController extends AbstractController
{
    /**
     * POST /api/carplay/software/version
     *
     * This is a faithful port of the original ConnectedSiteController::softwareDownload().
     * All regex patterns, matching logic, error messages, and response keys are identical.
     */
    #[Route('/api/carplay/software/version', name: 'api_software_version', methods: ['POST'])]
    public function version(
        Request $request,
        SoftwareVersionRepository $repo
    ): JsonResponse {

        $version    = $request->request->get('version', '');
        $hwVersion  = $request->request->get('hwVersion', '');
        // mcuVersion is accepted but intentionally unused (same as original)

        // ── Validate required fields ──────────────────────────────────────────
        if (empty($version)) {
            return new JsonResponse(['msg' => 'Version is required']);
        }

        if (empty($hwVersion)) {
            return new JsonResponse(['msg' => 'HW Version is required']);
        }

        // ── Detect hardware family from the HW version string ─────────────────
        //
        // Standard (non-LCI) hardware:
        //   CPAA_YYYY.MM.DD[_SUFFIX]  → ST flash chip  → CIC products
        //   CPAA_G_YYYY.MM.DD[_SUFFIX]→ GD flash chip  → NBT / EVO products
        //
        // LCI (facelift) hardware:
        //   B_C_YYYY.MM.DD            → CIC  (ST flash)
        //   B_N_G_YYYY.MM.DD          → NBT  (GD flash)
        //   B_E_G_YYYY.MM.DD          → EVO  (GD flash)
        //
        // These patterns are identical to the originals.

        $patternST      = '/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
        $patternGD      = '/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
        $patternLCI_CIC = '/^B_C_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
        $patternLCI_NBT = '/^B_N_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
        $patternLCI_EVO = '/^B_E_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';

        $hwValid   = false;
        $stBool    = false;   // should we return the ST link?
        $gdBool    = false;   // should we return the GD link?
        $isLCI     = false;
        $lciHwType = '';      // 'CIC', 'NBT', or 'EVO' for LCI hardware

        if (preg_match($patternST, $hwVersion)) {
            $hwValid = true;
            $stBool  = true;
        }

        if (preg_match($patternGD, $hwVersion)) {
            $hwValid = true;
            $gdBool  = true;
        }

        // LCI patterns are checked with elseif so only one LCI type is set
        if (preg_match($patternLCI_CIC, $hwVersion)) {
            $hwValid   = true;
            $isLCI     = true;
            $lciHwType = 'CIC';
            $stBool    = true;
        } elseif (preg_match($patternLCI_NBT, $hwVersion)) {
            $hwValid   = true;
            $isLCI     = true;
            $lciHwType = 'NBT';
            $gdBool    = true;
        } elseif (preg_match($patternLCI_EVO, $hwVersion)) {
            $hwValid   = true;
            $isLCI     = true;
            $lciHwType = 'EVO';
            $gdBool    = true;
        }

        if (!$hwValid) {
            return new JsonResponse([
                'msg' => 'There was a problem identifying your software. Contact us for help.',
            ]);
        }

        // ── Strip leading 'v' / 'V' from the software version (same as original) ──
        if (str_starts_with($version, 'v') || str_starts_with($version, 'V')) {
            $version = substr($version, 1);
        }

        // ── Look up the version in the database ───────────────────────────────
        $rows = $repo->findByVersionString($version);

        foreach ($rows as $row) {

            // Standard HW must match a standard (non-LCI) entry, and vice versa
            if ($isLCI !== $row->isLci()) {
                continue;
            }

            // For LCI hardware, the entry's product name must contain the HW type
            // (CIC / NBT / EVO) — e.g. "LCI MMI Prime CIC" only matches LCI CIC hardware
            if ($isLCI && stripos($row->getName(), $lciHwType) === false) {
                continue;
            }

            // ── Matched — build the response ─────────────────────────────────
            if ($row->isLatest()) {
                return new JsonResponse([
                    'versionExist' => true,
                    'msg'          => 'Your system is upto date!',
                    'link'         => '',
                    'st'           => '',
                    'gd'           => '',
                ]);
            }

            $latestLabel = \App\Entity\SoftwareVersion::latestLabel($isLCI);

            return new JsonResponse([
                'versionExist' => true,
                'msg'          => 'The latest version of software is ' . $latestLabel . ' ',
                'link'         => (string) ($row->getLink() ?? ''),
                'st'           => $stBool ? (string) ($row->getStLink() ?? '') : '',
                'gd'           => $gdBool ? (string) ($row->getGdLink() ?? '') : '',
            ]);
        }

        // ── No matching entry found ───────────────────────────────────────────
        return new JsonResponse([
            'versionExist' => false,
            'msg'          => 'There was a problem identifying your software. Contact us for help.',
            'link'         => '',
            'st'           => '',
            'gd'           => '',
        ]);
    }
}

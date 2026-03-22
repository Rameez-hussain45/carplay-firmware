<?php

namespace App\Entity;

use App\Repository\SoftwareVersionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SoftwareVersionRepository::class)]
#[ORM\Table(name: 'software_version')]
class SoftwareVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Product line name, e.g. "MMI Prime CIC" or "LCI MMI PRO NBT"
     * LCI entries MUST start with the word "LCI".
     */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Product name is required.')]
    private ?string $name = null;

    /**
     * Version string as shown in the customer's MMI, WITHOUT leading "v".
     * Example: 3.3.7.mmipri.c
     */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Software version string is required.')]
    private ?string $systemVersion = null;

    /**
     * Google Drive folder link for ST (SanDisk) flash devices.
     * Returned when the customer's HW version matches the CPAA_ or B_C_ patterns.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $stLink = null;

    /**
     * Google Drive folder link for GD (GigaDevice) flash devices.
     * Returned when the customer's HW version matches the CPAA_G_ or B_N_G_ / B_E_G_ patterns.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $gdLink = null;

    /**
     * Legacy combined folder link (some older entries use this).
     * Included in the API response as "link" for backwards compatibility.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    /**
     * TRUE = this is the current latest version for this product line.
     * Customers already on the latest version receive "Your system is up to date!".
     * Only ONE row per product line should have this set to TRUE.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $isLatest = false;

    /**
     * Display order in the admin panel. Lower = shown first.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sortOrder = null;

    // --------------- Getters / Setters ---------------

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSystemVersion(): ?string { return $this->systemVersion; }
    public function setSystemVersion(string $v): static { $this->systemVersion = $v; return $this; }

    public function getStLink(): ?string { return $this->stLink; }
    public function setStLink(?string $v): static { $this->stLink = $v; return $this; }

    public function getGdLink(): ?string { return $this->gdLink; }
    public function setGdLink(?string $v): static { $this->gdLink = $v; return $this; }

    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $v): static { $this->link = $v; return $this; }

    public function isLatest(): bool { return $this->isLatest; }
    public function setIsLatest(bool $v): static { $this->isLatest = $v; return $this; }

    public function getSortOrder(): ?int { return $this->sortOrder; }
    public function setSortOrder(?int $v): static { $this->sortOrder = $v; return $this; }

    // --------------- Derived helpers ---------------

    /** LCI entries have names that start with "LCI" (case-insensitive). */
    public function isLci(): bool
    {
        return stripos($this->name ?? '', 'LCI') === 0;
    }

    /**
     * The "latest version" label shown in the upgrade message differs between
     * standard and LCI product lines.
     */
    public static function latestLabel(bool $isLci): string
    {
        return $isLci ? 'v3.4.4' : 'v3.3.7';
    }

    public function __toString(): string
    {
        return sprintf(
            '[%s] %s%s',
            $this->name,
            $this->systemVersion,
            $this->isLatest ? ' ✓ latest' : ''
        );
    }
}

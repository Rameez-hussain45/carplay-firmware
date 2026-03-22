<?php

namespace App\Repository;

use App\Entity\SoftwareVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SoftwareVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SoftwareVersion::class);
    }

    /**
     * Find all rows whose systemVersion matches the given string (case-insensitive).
     * Multiple rows can match because the same version string may appear for
     * CIC, NBT, and EVO variants — the controller applies further filtering.
     *
     * @return SoftwareVersion[]
     */
    public function findByVersionString(string $version): array
    {
        return $this->createQueryBuilder('sv')
            ->where('LOWER(sv.systemVersion) = LOWER(:ver)')
            ->setParameter('ver', $version)
            ->orderBy('sv.sortOrder', 'ASC')
            ->addOrderBy('sv.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

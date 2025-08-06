<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop\CustomerCsc;
use App\Entity\User\ShopUser;
use Doctrine\ORM\EntityManagerInterface;

class CustomerCscService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function updateUserCsc(ShopUser $user, array $cscData): CustomerCsc
    {
        $customerCsc = $this->entityManager->getRepository(CustomerCsc::class)
            ->findOneBy(['user' => $user]);

        if (!$customerCsc) {
            $customerCsc = new CustomerCsc();
            $customerCsc->setUser($user);
        }

        $customerCsc->setCscs($cscData);
        
        $this->entityManager->persist($customerCsc);
        $this->entityManager->flush();

        return $customerCsc;
    }

    public function getUserCsc(ShopUser $user): ?CustomerCsc
    {
        return $this->entityManager->getRepository(CustomerCsc::class)
            ->findOneBy(['user' => $user]);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop\CustomerCsc;
use App\Entity\User\ShopUser;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des CSC (Compensations Éditeurs) des utilisateurs.
 * 
 * Centralise la logique métier pour :
 * - Récup données CSC d'un utilisateur BDD
 * - Créer ou mettre à jour les CSC d'un utilisateur
 * - Maintenir la cohérence des données entre fichiers JSON et BDD
 * 
 * Utilisé par :
 * - CscController : pour sauvegarder les modifications utilisateur
 * - CustomerCscController (API) : pour exposer les données via REST
 */
class CustomerCscService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Met à jour ou crée les données CSC d'un utilisateur en base de données.
     * 
     * Cette méthode :
     * - Recherche les CSC existantes pour l'utilisateur
     * - Crée un nouvel enregistrement si aucun n'existe
     * - Met à jour les données CSC (format JSON)
     * - Persiste les changements en BDD
     * 
     * @param ShopUser $user L'utilisateur concerné
     * @param array $cscData Les données CSC à sauvegarder (format JSON)
     * @return CustomerCsc L'entité CustomerCsc mise à jour
     */
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

    /**
     * Récupère les données CSC d'un utilisateur depuis la base de données.
     * 
     * @param ShopUser $user L'utilisateur dont on veut récupérer les CSC
     * @return CustomerCsc|null L'entité CustomerCsc ou null si aucune donnée n'existe
     */
    public function getUserCsc(ShopUser $user): ?CustomerCsc
    {
        return $this->entityManager->getRepository(CustomerCsc::class)
            ->findOneBy(['user' => $user]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Shop\CustomerCsc;
use App\Service\CustomerCscService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-compte')]
class CscController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/csc', name: 'shop_account_csc')]
    #[IsGranted('ROLE_USER')]
    public function list(): Response
    {
        // Charger les données JSON de test
        $jsonFile = __DIR__ . '/../../DataFixtures/data/customer_csc.json';
        if (file_exists($jsonFile)) {
            $cscData = json_decode(file_get_contents($jsonFile), true);
            
            // Formater les données pour le template
            $formattedCscs = [];
            foreach ($cscData as $reference => $csc) {
                $formattedCscs[] = [
                    'reference' => $reference,
                    'dateDebut' => \DateTime::createFromFormat('Ymd', $csc['Datedebut']),
                    'dateFin' => \DateTime::createFromFormat('Ymd', $csc['DateFin']),
                    'statut' => $csc['statutClient']
                ];
            }
        } else {
            $formattedCscs = [];
        }

        return $this->render('shop/csc/list.html.twig', [
            'cscs' => $formattedCscs,
        ]);
    }

    #[Route('/csc/{reference}', name: 'shop_account_csc_detail')]
    #[IsGranted('ROLE_USER')]
    public function detail(string $reference): Response
    {
        $jsonFile = __DIR__ . '/../../DataFixtures/data/customer_csc.json';
        if (!file_exists($jsonFile)) {
            throw $this->createNotFoundException('Données CSC non trouvées');
        }

        $cscData = json_decode(file_get_contents($jsonFile), true);
        if (!isset($cscData[$reference])) {
            throw $this->createNotFoundException('CSC non trouvée');
        }

        $csc = $cscData[$reference];
        $formattedCsc = [
            'reference' => $reference,
            'dateDebut' => \DateTime::createFromFormat('Ymd', $csc['Datedebut']),
            'dateFin' => \DateTime::createFromFormat('Ymd', $csc['DateFin']),
            'statut' => $csc['statutClient'],
            'produits' => array_map(function($ref, $produit) {
                return [
                    'reference' => $ref,
                    'libprod' => $produit['libprod'],
                    'prixAvant' => $produit['prixAvant'],
                    'prixApres' => $produit['prixApres'],
                    'qteClient' => $produit['qteClient']
                ];
            }, array_keys($csc['tabproduits']), $csc['tabproduits'])
        ];

        return $this->render('shop/csc/detail.html.twig', [
            'csc' => $formattedCsc,
        ]);
    }
}

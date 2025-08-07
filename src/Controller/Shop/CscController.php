<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Shop\CustomerCsc;
use App\Service\CustomerCscService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\Shop\CscFilterType;
use App\Form\Shop\CscSearchType;
use App\Form\Shop\CscFileUploadType;
use App\Service\CscFileUploadService;

#[Route('/mon-compte')]
class CscController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CscFileUploadService $fileUploadService,
    ) {
    }

    #[Route('/compensations-editeurs', name: 'shop_account_csc')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        // Créer le formulaire de recherche
        $searchForm = $this->createForm(CscSearchType::class, null, [
            'method' => 'GET'
        ]);
        $searchForm->handleRequest($request);

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

            // Appliquer les filtres si le formulaire est soumis
            if ($searchForm->isSubmitted() && $searchForm->isValid()) {
                $criteria = $searchForm->getData();
                
                if (!empty($criteria['statut'])) {
                    $formattedCscs = array_filter($formattedCscs, function($csc) use ($criteria) {
                        return $csc['statut'] === $criteria['statut'];
                    });
                }
                
                if (!empty($criteria['referenceProduit'])) {
                    $formattedCscs = array_filter($formattedCscs, function($csc) use ($criteria, $cscData) {
                        $reference = $csc['reference'];
                        if (isset($cscData[$reference]['tabproduits'])) {
                            $produits = $cscData[$reference]['tabproduits'];
                            foreach (array_keys($produits) as $refProduit) {
                                // Convertir en chaîne pour éviter l'erreur stripos
                                $refProduitStr = (string) $refProduit;
                                if (stripos($refProduitStr, $criteria['referenceProduit']) !== false) {
                                    return true;
                                }
                            }
                        }
                        return false;
                    });
                }
            }

            // Gérer le tri
            $sort = $request->query->get('sort', 'dateDebut');
            $direction = $request->query->get('direction', 'asc');

            usort($formattedCscs, function($a, $b) use ($sort, $direction) {
                $valueA = $a[$sort] instanceof \DateTime ? $a[$sort]->getTimestamp() : $a[$sort];
                $valueB = $b[$sort] instanceof \DateTime ? $b[$sort]->getTimestamp() : $b[$sort];
                
                return $direction === 'asc' 
                    ? ($valueA <=> $valueB)
                    : ($valueB <=> $valueA);
            });
        } else {
            $formattedCscs = [];
            $sort = 'dateDebut';
            $direction = 'asc';
        }

        // Formulaire d'upload de fichiers
        $uploadForm = $this->createForm(CscFileUploadType::class);
        $uploadForm->handleRequest($request);
        
        $uploadedFiles = [];
        $uploadMessage = null;
        
        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $files = $uploadForm->get('files')->getData();
            
            if ($files) {
                try {
                    $uploadedFiles = $this->fileUploadService->uploadFiles($files);
                    $uploadPath = $this->getParameter('csc_upload_directory');
                    $uploadMessage = sprintf(
                        '%d fichier(s) téléchargé(s) avec succès dans le dossier : %s',
                        count($uploadedFiles),
                        $uploadPath
                    );
                } catch (\Exception $e) {
                    $uploadMessage = 'Erreur lors du téléchargement : ' . $e->getMessage();
                }
            }
        }

        return $this->render('shop/csc/listeCsc.html.twig', [
            'cscs' => $formattedCscs,
            'form' => $searchForm->createView(),
            'uploadForm' => $uploadForm->createView(),
            'uploadedFiles' => $uploadedFiles,
            'uploadMessage' => $uploadMessage,
            'current_sort' => $sort,
            'current_direction' => $direction
        ]);
    }

    #[Route('/compensations-editeurs/{reference}', name: 'shop_account_csc_detail')]
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

        return $this->render('shop/csc/detailCsc.html.twig', [
            'csc' => $formattedCsc,
        ]);
    }
}

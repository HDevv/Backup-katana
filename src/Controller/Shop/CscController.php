<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\Shop\CscSearchType;
use App\Form\Shop\CscFileUploadType;
use App\Service\CscFileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CustomerCscService;


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
        $user = $this->getUser();
        $userId = $user->getId();

        $baseFile = __DIR__ . '/../../DataFixtures/data/csc_base.json';
        $userFile = __DIR__ . "/../../DataFixtures/data/csc_user_{$userId}.json";

        // Si le fichier utilisateur n'existe pas, on le crée automatiquement
        if (!file_exists($userFile) && file_exists($baseFile)) {
            $this->generateUserCscFile($baseFile, $userFile);
        }

        $baseData = file_exists($baseFile) ? json_decode(file_get_contents($baseFile), true) : [];
        $userData = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];

        $formattedCscs = [];

        foreach ($baseData as $ref => $baseCsc) {
            $userCsc = $userData[$ref] ?? [];

            $statut = $userCsc['statutClient'] ?? 'Non défini';
            $produits = [];

            foreach ($baseCsc['tabproduits'] as $prodRef => $prod) {
                $qteClient = $userCsc['tabproduits'][$prodRef]['qteClient'] ?? 0;
                $produits[$prodRef] = array_merge($prod, ['qteClient' => $qteClient]);
            }

            $formattedCscs[] = [
                'reference' => $ref,
                'dateDebut' => \DateTime::createFromFormat('Ymd', $baseCsc['Datedebut']),
                'dateFin' => \DateTime::createFromFormat('Ymd', $baseCsc['DateFin']),
                'statut' => $statut,
                'produits' => $produits
            ];
        }

        // Formulaire de recherche
        $searchForm = $this->createForm(CscSearchType::class, null, ['method' => 'GET']);
        $searchForm->handleRequest($request);

        // Appliquer les filtres
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $criteria = $searchForm->getData();

            if (!empty($criteria['statut'])) {
                $formattedCscs = array_filter($formattedCscs, fn($csc) => $csc['statut'] === $criteria['statut']);
            }

            if (!empty($criteria['referenceProduit'])) {
                $formattedCscs = array_filter($formattedCscs, function ($csc) use ($criteria) {
                    foreach ($csc['produits'] as $ref => $prod) {
                        if (stripos((string) $ref, $criteria['referenceProduit']) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
            }
        }

        // Tri
        $sort = $request->query->get('sort', 'dateDebut');
        $direction = $request->query->get('direction', 'asc');

        usort($formattedCscs, function ($a, $b) use ($sort, $direction) {
            $valueA = $a[$sort] instanceof \DateTime ? $a[$sort]->getTimestamp() : $a[$sort];
            $valueB = $b[$sort] instanceof \DateTime ? $b[$sort]->getTimestamp() : $b[$sort];
            return $direction === 'asc' ? $valueA <=> $valueB : $valueB <=> $valueA;
        });

        // Formulaire d'upload
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
                    $uploadMessage = sprintf('%d fichier(s) téléchargé(s) avec succès dans : %s', count($uploadedFiles), $uploadPath);
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

    // ajout des quantités
    #[Route('/api/csc/update-quantity', name: 'shop_account_csc_update_quantity', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function updateQuantity(Request $request): Response
{
    $user = $this->getUser();
    $userId = $user->getId();

    $data = json_decode($request->getContent(), true);

    $referenceCSC = $data['referenceCSC'] ?? null;
    $referenceProduit = $data['referenceProduit'] ?? null;
    $quantite = isset($data['quantite']) ? (int)$data['quantite'] : null;

    if (!$referenceCSC || !$referenceProduit || $quantite === null || $quantite < 0) {
        return $this->json(['error' => 'Paramètres invalides'], 400);
    }

    $userFile = __DIR__ . "/../../DataFixtures/data/csc_user_{$userId}.json";
    if (!file_exists($userFile)) {
        return $this->json(['error' => 'Fichier utilisateur introuvable'], 404);
    }

    $userData = json_decode(file_get_contents($userFile), true);

    if (!isset($userData[$referenceCSC]['tabproduits'][$referenceProduit])) {
        return $this->json(['error' => 'Produit introuvable dans la CSC'], 404);
    }

    $userData[$referenceCSC]['tabproduits'][$referenceProduit]['qteClient'] = $quantite;

    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $this->json(['success' => true, 'quantite' => $quantite]);
}

#[Route('/compensations-editeurs/{reference}/enregistrer', name: 'shop_account_csc_save_quantity', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function saveQuantity(Request $request, string $reference, CustomerCscService $customerCscService): Response
{
    $user = $this->getUser();
    $userId = $user->getId();

    $quantites = $request->request->all('quantites');

    $userFile = __DIR__ . "/../../DataFixtures/data/csc_user_{$userId}.json";
    if (!file_exists($userFile)) {
        throw $this->createNotFoundException('Fichier utilisateur non trouvé');
    }

    $userData = json_decode(file_get_contents($userFile), true);

    if (!isset($userData[$reference])) {
        throw $this->createNotFoundException('CSC inconnue');
    }

    foreach ($quantites as $refProduit => $qte) {
        $qte = max(0, (int) $qte);
        if (isset($userData[$reference]['tabproduits'][$refProduit])) {
            $userData[$reference]['tabproduits'][$refProduit]['qteClient'] = $qte;
        }
    }

    // 🔽 Optionnel : mise à jour en BDD
    $customerCscService->updateUserCsc($user, $userData);

    // 🔽 Optionnel : écriture dans le fichier JSON
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->addFlash('success', 'Les quantités ont bien été enregistrées.');

    return $this->redirectToRoute('shop_account_csc_detail', ['reference' => $reference]);
}


    #[Route('/compensations-editeurs/{reference}', name: 'shop_account_csc_detail')]
    #[IsGranted('ROLE_USER')]
    public function detail(string $reference): Response
    {
        $user = $this->getUser();
        $userId = $user->getId();

        $baseFile = __DIR__ . '/../../DataFixtures/data/csc_base.json';
        $userFile = __DIR__ . "/../../DataFixtures/data/csc_user_{$userId}.json";

        if (!file_exists($baseFile)) {
            throw $this->createNotFoundException('Données CSC non trouvées');
        }

        $baseData = json_decode(file_get_contents($baseFile), true);
        $userData = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];

        if (!isset($baseData[$reference])) {
            throw $this->createNotFoundException('CSC non trouvée');
        }

        $baseCsc = $baseData[$reference];
        $userCsc = $userData[$reference] ?? [];

        $statut = $userCsc['statutClient'] ?? 'Non défini';
        $produits = [];

        foreach ($baseCsc['tabproduits'] as $refProd => $prod) {
            $qte = $userCsc['tabproduits'][$refProd]['qteClient'] ?? 0;
            $produits[] = array_merge($prod, [
                'reference' => $refProd,
                'qteClient' => $qte
            ]);
        }

        $formattedCsc = [
            'reference' => $reference,
            'dateDebut' => \DateTime::createFromFormat('Ymd', $baseCsc['Datedebut']),
            'dateFin' => \DateTime::createFromFormat('Ymd', $baseCsc['DateFin']),
            'statut' => $statut,
            'produits' => $produits
        ];

        return $this->render('shop/csc/detailCsc.html.twig', [
            'csc' => $formattedCsc,
        ]);
    }

    /**
     * Génère un fichier utilisateur vide à partir de la base
     */
    private function generateUserCscFile(string $basePath, string $userPath): void
    {
        $baseData = json_decode(file_get_contents($basePath), true);
        $userData = [];

        foreach ($baseData as $ref => $csc) {
            $userData[$ref] = [
                'statutClient' => 'Ouverte',
                'tabproduits' => []
            ];

            foreach ($csc['tabproduits'] as $prodRef => $_) {
                $userData[$ref]['tabproduits'][$prodRef] = [
                    'qteClient' => 0
                ];
            }
        }

        file_put_contents($userPath, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

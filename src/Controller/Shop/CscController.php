<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Form\Shop\CscSearchType;
use App\Form\Shop\CscFileUploadType;
use App\Service\CscFileUploadService;
use App\Service\CustomerCscService;
use App\Service\CommandoApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur principal de gestion des CSC (Compensations Éditeurs)
 * - Affichage de la liste
 * - Consultation des détails
 * - Modification des quantités (formulaire et AJAX)
 * - Upload de fichiers CSC
 * 
 * Les données sont stockées en JSON (base + par utilisateur) + en BDD via CustomerCscService
 */

#[Route('/mon-compte')]
class CscController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CscFileUploadService $fileUploadService,
        private CommandoApiService $commandoApi,
        private ParameterBagInterface $params,
    ) {
    }

    // =======================================
    //  LISTE DES CSC
    // =======================================
    #[Route('/compensations-editeurs', name: 'shop_account_csc')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        $userId = $user->getId();

        // Fichiers JSON : base commune + fichier utilisateur
        $baseFile = __DIR__ . '/../../DataFixtures/data/csc_base.json';
        $userFile = $this->getUserFilePath($userId);

        // Création du fichier utilisateur si absent
        if (!file_exists($userFile) && file_exists($baseFile)) {
            $this->generateUserCscFile($baseFile, $userFile);
        }

        // Lecture des données
        $baseData = $this->readJsonFile($baseFile);
        $userData = $this->readJsonFile($userFile);

        // Fusion JSON base + JSON données utilisateur
        $formattedCscs = $this->mergeBaseAndUserData($baseData, $userData);

        // Formulaire de recherche (GET)
        $searchForm = $this->createForm(CscSearchType::class, null, ['method' => 'GET']);
        $searchForm->handleRequest($request);

        // Application des filtres
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $formattedCscs = $this->applyFilters($formattedCscs, $searchForm->getData());
        }

        // Tri
        $sort = $request->query->get('sort', 'dateDebut');
        $direction = $request->query->get('direction', 'asc');
        $this->sortCscs($formattedCscs, $sort, $direction);

        // Formulaire d'upload
        $uploadForm = $this->createForm(CscFileUploadType::class);
        $uploadForm->handleRequest($request);

        $uploadedFiles = [];
        $uploadMessage = null;

        // Si un fichier est uploadé, on le traite
        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            [$uploadedFiles, $uploadMessage] = $this->handleFileUpload($uploadForm);
        }

        // Appel au webservice Commando pour récupérer la liste des CSC
        $cscFromApi = null;
        $apiError = null;
        $codeClient = $this->params->get('commando_default_client');
        $formattedCscsFromApi = [];
        
        // Construction de l'URL pour affichage
        $cscListeUrl = $this->commandoApi->buildUrl([
            'act' => 'cscliste',
            'cli' => $codeClient,
        ]);
        
        try {
            // Récupération de la liste des CSC depuis l'API
            $cscFromApi = $this->commandoApi->getCscListe($codeClient);
            
            // Transformation des données API vers le format de la vue
            if (isset($cscFromApi['listeCsc']) && is_array($cscFromApi['listeCsc'])) {
                // Les quantités sont déjà dans le JSON de cscliste (qteClient)
                // Formatage des CSC pour la vue
                $formattedCscsFromApi = $this->formatCscsFromApi($cscFromApi['listeCsc']);
            }
        } catch (\Exception $e) {
            $apiError = $e->getMessage();
        }

        // Utiliser les données de l'API si disponibles, sinon les fichiers JSON
        $cscsToDisplay = !empty($formattedCscsFromApi) ? $formattedCscsFromApi : $formattedCscs;

        // Affichage de la liste (vue)
        return $this->render('shop/csc/listeCsc.html.twig', [
            'cscs' => $cscsToDisplay,
            'form' => $searchForm->createView(),
            'uploadForm' => $uploadForm->createView(),
            'uploadedFiles' => $uploadedFiles,
            'uploadMessage' => $uploadMessage,
            'current_sort' => $sort,
            'current_direction' => $direction,
            'csc_from_api' => $cscFromApi,
            'api_error' => $apiError,
            'code_client' => $codeClient,
            'using_api_data' => !empty($formattedCscsFromApi),
            'csc_liste_url' => $cscListeUrl,
        ]);
    }

    // =======================================
    //  DÉTAIL D'UNE CSC (GET/POST)
    // =======================================
    #[Route('/compensations-editeurs/{reference}', name: 'shop_account_csc_detail', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function detail(Request $request, string $reference, CustomerCscService $customerCscService): Response
    {
        // récupération des fichiers JSON
        $user = $this->getUser();
        $userId = $user->getId();
        $baseFile = __DIR__ . '/../../DataFixtures/data/csc_base.json';
        $userFile = $this->getUserFilePath($userId);

        $baseData = $this->readJsonFile($baseFile);
        $userData = $this->readJsonFile($userFile);

        // vérification de l'existence de la CSC
        if (!isset($baseData[$reference])) {
            throw $this->createNotFoundException('CSC non trouvée');
        }

        // Traitement POST : enregistrement des quantités
        if ($request->isMethod('POST')) {
            // Récupère toutes les quantités depuis le formulaire 
            $quantites = $request->request->all('quantites');

            if (!isset($userData[$reference])) {
                throw $this->createNotFoundException('CSC inconnue');
            }

            // Met à jour les quantités de chaque produit
            foreach ($quantites as $refProduit => $qte) {
                $qte = max(0, (int) $qte);
                if (isset($userData[$reference]['tabproduits'][$refProduit])) {
                    $userData[$reference]['tabproduits'][$refProduit]['qteClient'] = $qte;
                }
            }

            // Sauvegarde en BDD et dans fichier JSON
            $customerCscService->updateUserCsc($user, $userData);
            file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->addFlash('success', 'Les quantités ont bien été enregistrées.');

            return $this->redirectToRoute('shop_account_csc_detail', ['reference' => $reference]);
        }

        // Traitement GET : affichage du détail
        $baseCsc = $baseData[$reference];
        $userCsc = $userData[$reference] ?? [];

        // construction de la liste des produits avec les quantités utilisateur
        $produits = [];
        foreach ($baseCsc['tabproduits'] as $refProd => $prod) {
            $qte = $userCsc['tabproduits'][$refProd]['qteClient'] ?? 0;
            $produits[] = array_merge($prod, [
                'reference' => $refProd,
                'qteClient' => $qte
            ]);
        }

        // format final pour la vue 
        $formattedCsc = [
            'reference' => $reference,
            'dateDebut' => \DateTime::createFromFormat('Ymd', $baseCsc['Datedebut']),
            'dateFin' => \DateTime::createFromFormat('Ymd', $baseCsc['DateFin']),
            'statut' => $userCsc['statutClient'] ?? 'Non défini',
            'produits' => $produits
        ];

        return $this->render('shop/csc/detailCsc.html.twig', [
            'csc' => $formattedCsc,
        ]);
    }


    // =======================================
    //  ENREGISTREMENT DES QUANTITÉS (AJAX)
    // =======================================
    #[Route('/api/csc/update-quantity', name: 'shop_account_csc_update_quantity', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateQuantity(Request $request): Response
    {
        $user = $this->getUser();
        $userId = $user->getId();

        // Lecture et validation des paramètres JSON    
        $data = json_decode($request->getContent(), true);
        $referenceCSC = $data['referenceCSC'] ?? null;
        $referenceProduit = $data['referenceProduit'] ?? null;
        $quantite = isset($data['quantite']) ? (int)$data['quantite'] : null;

        if (!$referenceCSC || !$referenceProduit || $quantite === null || $quantite < 0) {
            return $this->json(['error' => 'Paramètres invalides'], 400);
        }

        $userFile = $this->getUserFilePath($userId);
        $userData = $this->readJsonFile($userFile);

        // Vérification de l'existence du produit dans la CSC
        if (!isset($userData[$referenceCSC]['tabproduits'][$referenceProduit])) {
            return $this->json(['error' => 'Produit introuvable dans la CSC'], 404);
        }

        // Mise à jour et sauvegarde de la quantité
        $userData[$referenceCSC]['tabproduits'][$referenceProduit]['qteClient'] = $quantite;
        file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->json(['success' => true, 'quantite' => $quantite]);
    }

    // =======================================
    //  MÉTHODES PRIVÉES UTILES
    // =======================================

    // Retourne le chemin du fichier utilisateur JSON
    private function getUserFilePath(int $userId): string
    {
        return __DIR__ . "/../../DataFixtures/data/csc_user_{$userId}.json";
    }

    // Lit un fichier JSON et retourne un tableau
    private function readJsonFile(string $path): array
    {
        return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
    }

    /**
     * Transforme les données de l'API Commando vers le format attendu par la vue
     * 
     * @param array $listeCsc Liste des CSC depuis l'API
     * @return array Format compatible avec la vue
     */
    private function formatCscsFromApi(array $listeCsc): array
    {
        $result = [];
        
        foreach ($listeCsc as $csc) {
            // Formatage des produits
            $produits = [];
            if (isset($csc['tabProduits']) && is_array($csc['tabProduits'])) {
                foreach ($csc['tabProduits'] as $produit) {
                    $ref = $produit['reference'] ?? '';
                    if ($ref) {
                        $produits[$ref] = [
                            'desig' => $produit['desig'] ?? '',
                            'gencode' => $produit['gencode'] ?? '',
                            'oldPVHT' => (float)($produit['oldPVHT'] ?? 0),
                            'newPVHT' => (float)($produit['newPVHT'] ?? 0),
                            'oldPVTTC' => (float)($produit['oldPVTTC'] ?? 0),
                            'newPVTTC' => (float)($produit['newPVTTC'] ?? 0),
                            'montantCSC' => (float)($produit['montantCSC'] ?? 0),
                            'qteClient' => (int)($produit['qteClient'] ?? 0),
                        ];
                    }
                }
            }
            
            // Formatage de la CSC
            $result[] = [
                'reference' => $csc['numCsc'] ?? '',
                'dateDebut' => isset($csc['dateDebut']) ? \DateTime::createFromFormat('Ymd', $csc['dateDebut']) : null,
                'dateFin' => isset($csc['dateFin']) ? \DateTime::createFromFormat('Ymd', $csc['dateFin']) : null,
                'statut' => $csc['statutClient'] ?? 'Non défini',
                'titre' => $csc['titreCSC'] ?? '',
                'produits' => $produits
            ];
        }
        
        return $result;
    }

    // Fusionne données de base et données utilisateur 
    private function mergeBaseAndUserData(array $baseData, array $userData): array
    {
        $result = [];
        foreach ($baseData as $ref => $baseCsc) {
            $userCsc = $userData[$ref] ?? [];
            $produits = [];
            foreach ($baseCsc['tabproduits'] as $prodRef => $prod) {
                $qteClient = $userCsc['tabproduits'][$prodRef]['qteClient'] ?? 0;
                $produits[$prodRef] = array_merge($prod, ['qteClient' => $qteClient]);
            }
            $result[] = [
                'reference' => $ref,
                'dateDebut' => \DateTime::createFromFormat('Ymd', $baseCsc['Datedebut']),
                'dateFin' => \DateTime::createFromFormat('Ymd', $baseCsc['DateFin']),
                'statut' => $userCsc['statutClient'] ?? 'Non défini',
                'produits' => $produits
            ];
        }
        return $result;
    }

    // Applique les filtres au tableau des CSC
    private function applyFilters(array $cscs, array $criteria): array
    {
        if (!empty($criteria['statut'])) {
            $cscs = array_filter($cscs, fn($csc) => $csc['statut'] === $criteria['statut']);
        }
        if (!empty($criteria['referenceProduit'])) {
            $cscs = array_filter($cscs, function ($csc) use ($criteria) {
                foreach ($csc['produits'] as $ref => $prod) {
                    if (stripos((string) $ref, $criteria['referenceProduit']) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }
        return $cscs;
    }

    // Trie le tableau des CSC
    private function sortCscs(array &$cscs, string $sort, string $direction): void
    {
        usort($cscs, function ($a, $b) use ($sort, $direction) {
            $valueA = $a[$sort] instanceof \DateTime ? $a[$sort]->getTimestamp() : $a[$sort];
            $valueB = $b[$sort] instanceof \DateTime ? $b[$sort]->getTimestamp() : $b[$sort];
            return $direction === 'asc' ? $valueA <=> $valueB : $valueB <=> $valueA;
        });
    }

    // Gère l'upload des fichiers
    private function handleFileUpload($uploadForm): array
    {
        $uploadedFiles = [];
        $uploadMessage = null;
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
        return [$uploadedFiles, $uploadMessage];
    }

    // Génère un fichier JSON utilisateur vide à partir de la base
    private function generateUserCscFile(string $basePath, string $userPath): void
    {
        $baseData = $this->readJsonFile($basePath);
        $userData = [];
        foreach ($baseData as $ref => $csc) {
            $userData[$ref] = [
                'statutClient' => 'Ouverte',
                'tabproduits' => []
            ];
            foreach ($csc['tabproduits'] as $prodRef => $_) {
                $userData[$ref]['tabproduits'][$prodRef] = ['qteClient' => 0];
            }
        }
        file_put_contents($userPath, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CustomerCscService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API pour gérer les CSC d'un utilisateur.
 * Fournit des endpoints pour :
 *  - Récupérer les CSC d'un utilisateur
 *  - Mettre à jour les CSC d'un utilisateur
 */
#[Route('/api')]
class CustomerCscController extends AbstractController
{
    public function __construct(
        private CustomerCscService $customerCscService,
    ) {
    }

    // =======================================
    //  GET : Récupération des CSC utilisateur
    // =======================================
    #[Route('/user/csc', name: 'api_user_csc_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCsc(): JsonResponse
    {
        // Récupère l'utilisateur connecté
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Récupère les CSC en base via le service
        $customerCsc = $this->customerCscService->getUserCsc($user);
        if (!$customerCsc) {
            return new JsonResponse(['cscs' => []]); // Aucun CSC trouvé
        }

        return new JsonResponse(['cscs' => $customerCsc->getCscs()]);
    }

    // =======================================
    //  POST : Mise à jour des CSC utilisateur
    // =======================================
    #[Route('/user/csc', name: 'api_user_csc_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateCsc(Request $request): JsonResponse
    {
        // Récupère le contenu brut de la requête et le décode
        $content = $request->getContent();
        $data = json_decode($content, true);

        // Validation des données reçues
        if (!$data || !isset($data['email']) || !isset($data['cscs'])) {
            return new JsonResponse(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Récupère l'utilisateur (dans ce cas, il faudrait probablement le trouver par email)
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Mise à jour des CSC en base via le service
            $customerCsc = $this->customerCscService->updateUserCsc($user, $data['cscs']);

            return new JsonResponse([
                'message'   => 'CSC mises à jour avec succès',
                'reference' => array_keys($customerCsc->getCscs()), // Liste des références modifiées
            ]);
        } catch (\Exception $e) {
            // Gestion d'erreur générique
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

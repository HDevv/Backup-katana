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

#[Route('/api')]
class CustomerCscController extends AbstractController
{
    public function __construct(
        private CustomerCscService $customerCscService,
    ) {
    }

    #[Route('/user/csc', name: 'api_user_csc_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateCsc(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (!$data || !isset($data['email']) || !isset($data['cscs'])) {
            return new JsonResponse(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $customerCsc = $this->customerCscService->updateUserCsc($user, $data['cscs']);
            return new JsonResponse([
                'message' => 'CSC mises à jour avec succès',
                'reference' => array_keys($customerCsc->getCscs()),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/user/csc', name: 'api_user_csc_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCsc(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $customerCsc = $this->customerCscService->getUserCsc($user);
        if (!$customerCsc) {
            return new JsonResponse(['cscs' => []]);
        }

        return new JsonResponse(['cscs' => $customerCsc->getCscs()]);
    }
}

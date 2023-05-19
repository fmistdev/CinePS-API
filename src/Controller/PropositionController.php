<?php

namespace App\Controller;

use App\Entity\Proposition;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PropositionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PropositionController extends AbstractController
{

    //Retourne une proposition selon l'id
    #[Route('/api/propositions/{id}', name: 'detailProposition', methods: ['GET'])]
    public function getDetailFilm(int $id, SerializerInterface $serializer, PropositionRepository $propostionRepository): JsonResponse
    {

        $proposition = $propostionRepository->find($id);
        if($proposition) {
            $jsonProposition = $serializer->serialize($proposition, 'json');
            return new JsonResponse($jsonProposition, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(["error" => "Not Found"], 404);
    }

}

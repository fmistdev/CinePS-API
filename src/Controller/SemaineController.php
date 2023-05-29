<?php

namespace App\Controller;

use DateTime;
use App\Entity\Vote;
use App\Entity\Semaine;
use App\Service\CurrentSemaine;
use App\Repository\MembreRepository;
use App\Repository\SemaineRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PropositionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SemaineController extends AbstractController
{
    #[Route('/api/semaine/{id}', name: 'detailSemaine', methods: ['GET'])]
    public function getDetailSemaine(int $id, SerializerInterface $serializer, SemaineRepository $semaineRepository): JsonResponse
    {
        $semaine = $semaineRepository->find($id);
        if($semaine) {
            $jsonSemaine = $serializer->serialize($semaine, 'json', ['groups' => 'getPropositions']);
            return new JsonResponse($jsonSemaine, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);    
    }

    // Retourne l'id base de données de la semaine en cours. 0 si la semaine en cours n'existe pas encore dans la base de données
    #[Route('/api/currentSemaine', name: 'currentSemaine', methods: ['GET'])]
    public function currentSemaine(SerializerInterface $serializer, SemaineRepository $semaineRepository): JsonResponse
    {
        // Date du jour
        $curdate=new DateTime();

        // calcul de la date de fin de la période de vote
        $fin_periode_vote = new DateTime("Fri 14:00");
        $fin_periode_vote = $fin_periode_vote->format('Y-m-d H:i:s');

        // conversion de la date de fin en timestamp
        $deadline_vote = strtotime($fin_periode_vote);
        $deadline_vote = $deadline_vote*1000;

        // Get vendredi id_current_semaine
        if ($curdate->format('D')=="Fri"){ // Si nous sommes vendredi, alors id_current_semaine est défini par ce vendredi
            $friday_current_semaine = $curdate;
        } else { // Sinon id_current_semaine est défini par vendredi prochain
            $friday_current_semaine = $curdate->modify('next friday');
        }

        //Récupère la propositionTerminé de id_semaine
        $currentSemaine = $semaineRepository->findByJour($friday_current_semaine);

        if($currentSemaine) {
            $jsonFilmProposes = $serializer->serialize($currentSemaine, 'json', ['groups' => 'getPropositions']);
            return new JsonResponse ($jsonFilmProposes, Response::HTTP_OK, [], true);
        } else {
            return new JsonResponse(["error" => "Not Found"], 404);
        }
    }

    // Retourne l'id base de données de la semaine en cours. 0 si la semaine en cours n'existe pas encore dans la base de données
    #[Route('/api/idCurrentSemaine', name: 'idCurrentSemaine', methods: ['GET'])]
    public function getIdCurrentSemaine(SerializerInterface $serializer, CurrentSemaine $currentSemaine, EntityManagerInterface $entityManager): JsonResponse
    {
        $friday_current_semaine = $currentSemaine->getFridayCurrentSemaine();

        //Récupère la propositionTerminé de id_semaine
        $queryBuilder_get_id_current_semaine = $entityManager->createQueryBuilder();
        $queryBuilder_get_id_current_semaine->select('s.id')
        ->from(Semaine::class, 's')
        ->where('s.jour = :jour')
        ->setParameter('jour', $friday_current_semaine);

        $result_id_current_semaine = $queryBuilder_get_id_current_semaine->getQuery()->getResult();
        
        if ($result_id_current_semaine){
            $id_current_semaine = $result_id_current_semaine[0]['id'];
        } else { // la semaine courrant n'exite pas encore dans la base de données
            $id_current_semaine = 0;
        }
        $array_id_current_semaine = array("id_current_semaine" => $id_current_semaine);
        $json_id_current_semaine = $serializer->serialize($array_id_current_semaine, 'json');
        return new JsonResponse ($json_id_current_semaine, Response::HTTP_OK, [], true);
    }

    #[Route('/api/currentSemaine2', name: 'currentSemaine2', methods: ['GET'])]
    public function currentSemaine2(SerializerInterface $serializer, CurrentSemaine $currentSemaine, SemaineRepository $semaineRepository): JsonResponse
    {
        $jsonFilmProposes = $serializer->serialize($currentSemaine->getCurrentSemaine($semaineRepository), 'json', ['groups' => 'getPropositions']);
        return new JsonResponse ($jsonFilmProposes, Response::HTTP_OK, [], true);
    }

    // Retourne l'onjet de la semaine en cours
    #[Route('/api/anciennesSemaines', name: 'anciennesSemaines', methods: ['GET'])]
    public function getAnciennesSemaines(CurrentSemaine $currentSemaine, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $friday_current_semaine = $currentSemaine->getFridayCurrentSemaine();

        //Récupère les semaines plus anciennes que $friday_current_semaine
        $queryBuilder_get_id_current_semaine = $entityManager->createQueryBuilder();
        $queryBuilder_get_id_current_semaine->select('s')
        ->from(Semaine::class, 's')
        ->where('s.jour < :jour')
        ->orderBy('s.jour', 'DESC')
        ->setParameter('jour', $friday_current_semaine);

        $result_current_semaine = $queryBuilder_get_id_current_semaine->getQuery()->getResult();
        
        if($result_current_semaine) {
            $jsonProposition = $serializer->serialize($result_current_semaine, 'json', ['groups' => 'getPropositions']);
            return new JsonResponse($jsonProposition, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(["error" => "Not Found"], 404);
    }
    

    #[Route('/filmsProposes/{id_semaine}', name: 'filmsProposes', methods: ['GET'])]
    public function filmsProposes(int $id_semaine, PropositionRepository $propositionRepository, SerializerInterface $serializer): JsonResponse
    {
        $filmsProposes = $propositionRepository->findBySemaine($id_semaine);
        $jsonFilmProposes = $serializer->serialize($filmsProposes, 'json', ['groups' => 'getPropositions']);
        return new JsonResponse ($jsonFilmProposes, Response::HTTP_OK, [], true);

    }

    #[Route('/nextProposeurs/{id_semaine}', name:'nextProposeurs', methods: ['GET'])]
    public function nextProposeurs(int $id_semaine, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        //Récuperer le jour de la semaine $id_semaine
        $queryBuilder_get_jour = $entityManager->createQueryBuilder();
        $queryBuilder_get_jour->select('s.jour')
        ->from(Semaine::class, 's')
        ->where('s.id = :id')
        ->setParameter('id', $id_semaine);

        $resultats_jour = $queryBuilder_get_jour->getQuery()->getResult();

        //Récuperer les proposeurs des semaines postérieurs au jour précédent récupéré

        $queryBuilder_get_proposeurs = $entityManager->createQueryBuilder();
        $queryBuilder_get_proposeurs->select('s')
        ->from(Semaine::class, 's')
        ->where('s.jour >= :jour')
        ->setParameter('jour', $resultats_jour[0]['jour']);

        $resultats_proposeurs = $queryBuilder_get_proposeurs->getQuery()->getResult();
        $jsonResultatsProposeurs = $serializer->serialize($resultats_proposeurs, 'json', ['groups' => 'getPropositions']);

        return new JsonResponse ($jsonResultatsProposeurs, Response::HTTP_OK, [], true);

    }

    // Votes de la semaine
    #[Route('/votes/{id_semaine}', name:'votes', methods: ['GET'])]
    public function votes(int $id_semaine, MembreRepository $membreRepository, PropositionRepository $propositionRepository, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        // Récupération des propositions de la semaine
        $propositions = $propositionRepository->findBySemaine($id_semaine);
        $jsonPropositions = $serializer->serialize($propositions, 'json');
        $arrayPropositions = json_decode($jsonPropositions, true);

        $array_propositions_avec_votes = array();
        foreach($arrayPropositions as $proposition){

            $membres = $membreRepository->findAll();
            $jsonMembres = $serializer->serialize($membres, 'json');
            $arrayMembres = json_decode($jsonMembres, true);

            $proposition_votes = array(); // tableau dans lequel on stocke les votes de cette proposition
            foreach($arrayMembres as $membre){
                // Résupérer le vote de l'utilisateur pour cette proposition
                $queryBuilder_get_vote = $entityManager->createQueryBuilder();
                $queryBuilder_get_vote->select('v.vote')
                ->from(Vote::class, 'v')
                ->where('v.proposition = :id_proposition')
                ->andWhere('v.membre = :id_membre')
                ->setParameters(array('id_proposition' => $proposition['id'], 'id_membre' => $membre['id']));
        
                $resultat_vote = $queryBuilder_get_vote->getQuery()->getResult();
                $jsonResultatVote = $serializer->serialize($resultat_vote, 'json');
                $arrayVote = json_decode($jsonResultatVote, true);

                if (empty($arrayVote)){
                    $proposition_votes[] = array("membre" => $membre['Prenom'], "vote" => '');;
                } else {
                    $proposition_votes[] = array("membre" => $membre['Prenom'], "vote" => $arrayVote[0]['vote']);
                }
            }
            $proposition['vote'] = $proposition_votes;
            $array_propositions_avec_votes[] = $proposition;
        } // fin du parcours des propositions

        $jsonResultatsPropositiuonsAvecVotes = $serializer->serialize($array_propositions_avec_votes, 'json');
        return new JsonResponse ($jsonResultatsPropositiuonsAvecVotes, Response::HTTP_OK, [], true);

    }

    // Met à jour une semaine
    #[Route('/api/semaine/{id_semaine}', name: 'updateSemaine', methods: ['PATCH'])]
    public function createProposition($id_semaine, Request $request, SemaineRepository $semaineRepository, SerializerInterface $serializer, EntityManagerInterface $em): JsonResponse
    {
        $array_request = json_decode($request->getContent(), true);

        $semaine = $semaineRepository->findOneById($id_semaine);

        if (isset($array_request['proposition_terminee'])){
            $semaine->setPropositionTermine($array_request['proposition_terminee']);
        }
        if (isset($array_request['theme'])){
            $semaine->setTheme($array_request['theme']);
        }

        $em->persist($semaine);
        $em->flush();

        $jsonProposition = $serializer->serialize($semaine, 'json', ['groups' => 'getPropositions']); 
        return new JsonResponse($jsonProposition, Response::HTTP_OK, [], true);
    }

}
?>
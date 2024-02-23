<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Room;
use App\Repository\CharacterRepository;
use App\Repository\RoomRepository;
use App\Service\GeneratorWord;
use App\Service\SetRound;
use App\Service\VictoryVerifier;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Validator\Constraints\Date;

class GameController extends AbstractController
{
    /**
     * start the game
     *
     * @return JsonResponse
     */
    public function start(Request $request, EntityManagerInterface $entityManager,
     RoomRepository $roomRepository, SetRound $setRound, SerializerInterface $serializer,
      HubInterface $hub, GeneratorWord $generator): JsonResponse
    {   
        //vérification que l'utilisateur est bien le créateur de la partie
        /**@var \App\Entity\User */
        $user=$this->getUser();

        $data=json_decode($request->getContent(),true);
        $room=$roomRepository->find($data['roomId']);

        if($room->getOwner()!==$user){
            return $this->json([
                'message'=>'seul le créateur de la partie peut la faire débuter'],
                 Response::HTTP_FORBIDDEN);
        }

        //Récupération des characters de la room et de ses paramètres
        $characters = $room->getCharacters();
        $nbPlayers = $room->getCharacters()->count();

        //Vérification qu'il y'a au moins 3 joueurs dans la partie
        if($nbPlayers<3){
            return $this->json([
                'message'=>'il faut être au moins 3 pour jouer'],
                 Response::HTTP_BAD_REQUEST);
        }

        //paramètrage du nombre de joueurs en fonction du nombre de présent
        // pour empécher une arrivée après le début de la game
        $room->setNbPlayer($nbPlayers);
        //Paramétrage du nombre d'agent en fonction du nombre de joueur
        $room->setNbAgent(ceil($nbPlayers/5));

        $entityManager->flush();
        
        //On convertit la collection de participants en array PHP 
        // pour pouvoir le manipuler plus facilement
        $charactersArray=[];
        foreach($characters as $character){
            $charactersArray[]=$character;
        }

        // récupération au hasard de deux mots par le service
        $words= $generator->genererMots();

        //randomisation de l'ordre des joueurs
        shuffle($charactersArray);

        for($i=0; $i<$nbPlayers; $i++){

            $character=$charactersArray[$i];
            if ($i<$room->getNbAgent()){
                $character->setRole('agent');
                $character->setWord($words[0]);
                
            }else{
                $character->setRole('civil');
                $character->setWord($words[1]);
                
            }
        }

        $entityManager->flush();

        //transmission du mot pour chaque joueur
        foreach ($characters as $character){
                    $update= new Update(
                        'character/'.$character->getId(),
                        $serializer->serialize([
                            'type'=>'GAME_YOUR-WORD',
                             'word'=>$character->getWord()],
                             'json',['groups'=>['get_room_data']]),
                    );
            
                    $hub->publish($update);                
        }

        //préparation du premier tour de jeu (service)
        $setRound->set($room);

        return $this->json([
            'message' => 'partie lancée',
            Response::HTTP_OK]);
    }

    
    /**
     * receive the hint given by a user , store it in bdd and send it to the others players.
     *
     */
    public function sendHint(CharacterRepository $characterRepository, Request $request,
     RoomRepository $roomRepository, SerializerInterface $serializer,
      EntityManagerInterface $entityManager, HubInterface $hub){

        // récupération de l'indice envoyé
        $data=json_decode($request->getContent(),true);
        $room=$roomRepository->find($data['roomId']);
        if($room===null){
            return $this->json('cette partie n\'existe pas', Response::HTTP_NOT_FOUND );
        }
        $characterWhoSend=$characterRepository->find($data['characterId']);
        if($characterWhoSend===null){
            return $this->json('personnage introuvable', Response::HTTP_NOT_FOUND );
        }
        
        //vérification que l'indice viens bien d'un utilisateur de la partie
        $characters = $room->getCharacters();
        
        $exists = $characters->exists(function($key, $value) {
            $user=$this->getUser();
            return $value->getUser() === $user;
        });

        if($exists===false){
            return $this->json(['erreur'=>'vous ne pouvez envoyer d\'indice dans une partie à laquelle vous ne participez pas'], Response::HTTP_FORBIDDEN);
        }

        $hint=$data['hint'];

        // enregistrement en bdd 
        $characterWhoSend->setHint($hint);
        $entityManager->flush();

        //on transforme le hint en message pour affichage dans le chat plus simple coté front et sauvegarde en bdd
        $message = new Message;
        $message->setCreatedAt(new DateTime('now'));
        $message->setUser($characterWhoSend->getUser());
        $message->setRoom($room);
        $message->setContent($hint);
        $entityManager->persist($message);
        $entityManager->flush();

        // transmissions à tous les membres de la partie
        $update=new Update(
            'room/'.$room->getId(),
            $serializer->serialize(['type'=>'GAME_HINT-SEND','data'=>$message],'json',['groups'=>['get_room_data']]),
        );

        $hub->publish($update);

        //ping du joueur suivant pour démarrer son tour si il en reste qui n'ont pas donné d'indice / sinon ping de l'ensemble de la partie pour phase de vote
        //recherche du joueur suivant devant donné son indice ( = celui ayant hint =null avec le roundOrder le plus faible)
        $players=$characterRepository->findBy(['Room'=>$room->getId(),'is_eliminated'=>false,'hint'=>null],['roundOrder'=>'ASC']);
        
        //si tout le monde a donné son indice on passe au vote
        if(empty($players)){
            $update=new Update(
                'room/'.$room->getId(),
                $serializer->serialize(['type'=>'GAME_VOTE-TIME','room'=>$room],'json',['groups'=>['get_room_data']]),
            );
    
            $hub->publish($update);

            return $this->json('indice enregistré , passons au vote', Response::HTTP_CREATED);
        }

        //si il reste des joueurs sans indice on déclenche le tour de celui dont le roundOrder est le plus faible
        $update= new Update(
            'character/'.$players[0]->getId(),
            $serializer->serialize(['type'=>'GAME_YOUR-TURN','room'=>$room],'json',['groups'=>['get_room_data']]),
        );

        $hub->publish($update);
        
        //et on transmet l'information du joueur dont c'est  le tour à la room entière
        $update=new Update(
            'room/'.$room->getId(),
            $serializer->serialize(['type'=>'GAME_NEW-STEP-ROUND','room'=>$room,'current_player'=>$players[0]->getId()],'json',['groups'=>['get_room_data']]),
        );

        $hub->publish($update);
        return $this->json('indice enregistré', Response::HTTP_CREATED);
    
    }

    /**
     * receive the vote given by a user , store it in bdd and send it to the others players.
     *
     */
    public function sendVote(SetRound $setRound, Request $request, CharacterRepository $characterRepository, RoomRepository $roomRepository, EntityManagerInterface $entityManager, HubInterface $hub, SerializerInterface $serializer, VictoryVerifier $victoryVerifier){
                       
        // récupération des données du vote
        $data = json_decode($request->getContent(), true);

        $voter = $characterRepository->find($data['voterId']);
            if($voter===null){
                return $this->json('cet utilisateur n\'existe pas', Response::HTTP_NOT_FOUND );
             }

        $room = $roomRepository->find($data['roomId']);
        if($room===null){
            return $this->json('cette partie n\'existe pas', Response::HTTP_NOT_FOUND );
        }

        //vérification que le vote viens bien d'un utilisateur de la partie
        $characters = $room->getCharacters();

        $exists = $characters->exists(function($key, $value) {
            $user=$this->getUser();
            return $value->getUser() === $user;
        });

        if($exists===false){
            return $this->json(['erreur'=>'vous ne pouvez voter dans une partie à laquelle vous ne participez pas'], Response::HTTP_FORBIDDEN);
        }

        //prise en compte du vote blanc : si target === null on met le joueur comme ayant voté sans augmenter le nombre de vote d'un autre
        if($data['targetId']===null){            
            
            $voter->setVoted(true);
            $entityManager->flush();
      
        }else{
            //vérification des données
            $target=$characterRepository->find($data['targetId']);
            if ($target===null) {
                return $this->json('cet utilisateur n\'existe pas', Response::HTTP_NOT_FOUND);
            }
            if ($target->isIsEliminated()===true) {
                return $this->json('cet joueur est déjà éliminé', Response::HTTP_BAD_REQUEST);
            }

            //augmentation du compteur du joueur cible
            $target->setVoteCount($target->getVoteCount()+1);
            //set du voted du joueur ayant voté sur true
            $voter->setVoted(true);

            $entityManager->flush();
        }

        //si nombre de joueur encore en jeu = nombre de vote au total on déclenche la fin du vote et une vérification de victoire.
                
        // On filtre les joueurs pour ne garder que ceux non éliminés
        $playersAlive = $room->getCharacters()->filter(function($element){
            return $element->isIsEliminated()===false;
        });
  
        //parmis eux on filtre ceux ayant voté
        $playersAliveAndVoted=$playersAlive->filter(function($element){
            return $element->isVoted()===true;
        });

        //si tout le monde n'a pas voté on en reste là
        if ($playersAlive->count()>$playersAliveAndVoted->count()) {
            
            //publie qu'on vote a été soumis
            $update=new Update(
                'room/'.$room->getId(),
                $serializer->serialize(['type'=>'GAME_VOTE-SEND','from'=>$voter],'json',['groups'=>['get_room_data']]),
            );

            $hub->publish($update);

            return $this->json('vote pris en compte', Response::HTTP_OK);
        }



        //si tout le monde a voté on élimine celui qui a reçu le plus de vote
        
        //On défini le nombre de vote blanc
        $nbVote=$playersAliveAndVoted->count();
        $nbBlankVote = $nbVote;

        foreach($playersAliveAndVoted as $players){
            $nbBlankVote=$nbBlankVote-$players->getVoteCount();
        }
 
        //On utilise le repository pour récupérer les joueurs en vie et les classer par nombre de vote reçu
        $playersAlive=$characterRepository->findBy(['Room'=>$room->getId(),'is_eliminated'=>false],['vote_count'=>'DESC']);

        //on vérifie que le joueur avec le plus de vote en a plus qu'il n'y a de vote blanc et qu'il n'y a pas d'égalité entre les 2 premiers.
        if($playersAlive[0]->getVoteCount()>$nbBlankVote && $playersAlive[0]->getVoteCount()>$playersAlive[1]->getVoteCount()){
            //si c'est le cas on l'élimine
             $playersAlive[0]->setIsEliminated(True);
             $entityManager->flush();

             //on publie l'élimination du joueur dans la room
             $update = new Update(
                'room/'.$room->getId(),
                $serializer->serialize(['type'=>'GAME_VOTE-RESULT','room'=>$room],'json',['groups'=>['get_room_data']])
             );

             $hub->publish($update);


            // Puis on vérifie si un camp a gagné
            $winners = $victoryVerifier->check($room);

            //on annonce le camps des vainqueurs si il y'en a un
            if(isSet($winners)){
                $update = new Update(
                    'room/'.$room->getId(),
                    $serializer->serialize(['type'=>'GAME_VICTORY','winner'=>$winners],'json',['groups'=>['get_room_data']])                
                );
                $hub->publish($update);

                return $this->json('Partie terminée', Response::HTTP_OK);
            }



            // si pas de victoire on prépare un nouveau tour de jeu
            $setRound->set($room);
            return $this->json('vote pris en compte', Response::HTTP_OK);

        }

        //si le blanc l'emporte ou en cas d'égalité 
         //on publie l'absence d'elimination
         $update = new Update(
            'room/'.$room->getId(),
            $serializer->serialize(['type'=>'GAME_VOTE-RESULT','room'=>$room],'json',['groups'=>['get_room_data']])
         );

        $hub->publish($update);

        // puis on déclenche un nouveau tour
        $setRound->set($room);

        return $this->json('vote pris en compte', Response::HTTP_OK);
       
    }
}



//
<?php

namespace App\Controller;

use Exception;
use App\Entity\Room;
use App\Entity\Character;
use App\Service\VictoryVerifier;
use App\Repository\RoomRepository;
use Symfony\Component\Mercure\Update;
use App\Repository\CharacterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Serializer;

class RoomController extends AbstractController
{
    /**
     * Create a new game
     * 
     * @param EntityManagerInterface $em
     */
    public function createRoom(EntityManagerInterface $em): JsonResponse
    {   
        try{
            /**@var \App\Entity\User */
            $user=$this->getUser();

            $newRoom = new Room();        
            $newRoom->setOwner($user);

            //ajouter $user en tant que joueur de la partie
            $newCharacter= new Character();
            $newCharacter->setUser($user);
            $newRoom->addCharacter($newCharacter);

            $em->persist($newCharacter);
            $em->persist($newRoom);
            $em->flush();

            return $this->json(['code'=>$newRoom->getRoomCode()], JsonResponse::HTTP_CREATED);
        }catch(Exception $e){
            return $this->json(['erreur' => $e], JsonResponse::HTTP_BAD_REQUEST);
        }

    }

    /**
     * set the parameters of a room     *
     * 
     * @return Json
     */
    public function editRoom(RoomRepository $roomRepository, Request $request,
     SerializerInterface $serializer, EntityManagerInterface $entityManager,
      ValidatorInterface $validator){

        $data=json_decode($request->getContent(),true);
        $room=$roomRepository->find($data['roomId']);

        if($room===null){
            return $this->json(['erreur'=>'cette partie n\'existe pas'], JsonResponse::HTTP_NOT_FOUND);
        }

        //vérification que l'utilisateur est bien le créateur de la partie
        /**@var \App\Entity\User */
        $user=$this->getUser();

        if($room->getOwner()!==$user){
            return $this->json(['message'=>'seul le créateur de la partie peut modifier les paramètres'], Response::HTTP_FORBIDDEN);
        }


        $serializer->deserialize($request->getContent(), Room::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $room]);

        if($room->getCharacters()->count()>$room->getNbPlayer()){
            return $this->json(
                'Vous ne pouvez pas définir une limite de joueurs inférieure au nombre de personnes déjà présentes',
                 Response::HTTP_NOT_FOUND);
        }
        //Vérification de la conformité des paramètres saisi pour la partie

        $errors = $validator->validate($room);

        $errorsList = [];
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $errorsList[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->json($errorsList, Response::HTTP_BAD_REQUEST);
        }
        //si ok on applique les changements
        $entityManager->flush();

        return $this->json('edition faite', Response::HTTP_OK);

    }

    /**
     * Enable to join a room with its room code.
     * 
     * @return json
     */
    public function joinRoom($roomCode, RoomRepository $roomRepository, EntityManagerInterface $em,
     HubInterface $hub, SerializerInterface $serializer){

        //récupération de la room via son code passé en paramètre d'URL
        $room = $roomRepository->findOneBy(['room_code'=> $roomCode]);
        if($room===null){
            return $this->json(
                ['erreur'=>'cette partie n\'existe pas'],
                 JsonResponse::HTTP_NOT_FOUND);
        }

        if($room->getCharacters()->count()>=$room->getNbPlayer()){
            return $this->json(
                'Toutes les places de joueurs de cette partie sont occupées ou elle a déjà commencé',
                 Response::HTTP_FORBIDDEN);
        }

        // Récupération de l'utilisateur connecté pour l'ajouter en tant que joueur de la partie.

        /**@var \App\Entity\User */
        $user=$this->getUser();

        //vérification que l'utilisateur n'est pas déjà présent dans la partie
        foreach($room->getCharacters() as $character){
            if($character->getUser()=== $user){
                return $this->json(
                    'Vous ne pouvez rejoindre plusieurs fois la même partie',
                     Response::HTTP_FORBIDDEN); 
            }

        }

        //ajouter $user en tant que joueur de la partie
        $newCharacter= new Character();
        $newCharacter->setUser($user);
        $room->addCharacter($newCharacter);

        $em->persist($newCharacter);
        $em->flush();

        $update= new Update(
            'room/'.$room->getId(),
            $serializer->serialize([
                'type'=>'CONNECTION_NEW' ,
                'data'=>$newCharacter],
                'json',['groups'=>['get_room_data']]),
                    );

        $hub->publish($update);

        return $this->json(
            [
            'partie'=>$room,
            ],
            Response::HTTP_OK,
            [],
            //récupération des données de la room (paramètre de jeu / utilisateurs) via serializers 
            ['groups'=>['get_room_data']]);  

    }
    
    /**
     * Enable ton leave a room
     *
     * @return json
     */
    public function leaveRoom(Room $room, CharacterRepository $characterRepository, VictoryVerifier $victoryVerifier, SerializerInterface $serializer, HubInterface $hub ){

        //récupération de l'utilisateur courant
        /**@var \App\Entity\User */
        $user=$this->getUser();

        //récupération de la liste des joueurs présent dans la partie
        $characters = $room->getCharacters();

        //on parcourt la liste pour trouver le personnage correspondant à l'utilisateur et on le retire.
        foreach($characters as $character){
            if($character->getUser()===$user){
                $characterRepository->remove($character,true);
         
            }
        }

        //si la partie n'est pas closed et si  au moins un character a un rôle c'est que la partie est en cours: on vérifie la condition de victoire:
        if(!$room->isClosed()){ 
               foreach($characters as $character){
                    if($character->getRole()!==null){
                        $winners=$victoryVerifier->check($room);
                        if(isSet($winners)){
                            $update = new Update(
                                'room/'.$room->getId(),
                                $serializer->serialize(['type'=>'GAME_VICTORY','winner'=>$winners],'json',['groups'=>['get_room_data']])                
                            );
                            $hub->publish($update);
            
                            return $this->json('Partie terminée', Response::HTTP_OK);
                        }
                    }
                }    
            }
        $update = new Update(
            'room/'.$room->getId(),
            $serializer->serialize(['type'=>'CONNECTION_CLOSED','from'=>$user],'json',['groups'=>['get_room_data']])                
        );
        $hub->publish($update);
        
        //sinon c'est qu'elle n'avait pas débuté et on ne fait rien de plus
        return $this->json(
            [
            'message'=>"'vous avez bien quitté la partie",
            ],
            Response::HTTP_OK);  

    }

}

   

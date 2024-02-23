<?php

namespace App\Service;

use App\Entity\Room;
use App\Repository\CharacterRepository;
use Symfony\Component\Mercure\Update;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Serializer\SerializerInterface;

class SetRound{

    private $entityManager;
    private $characterRepository;
    private $serializer;
    private $hub;

    public function __construct(EntityManagerInterface $entityManager,
     SerializerInterface $serializer, HubInterface $hub,
      CharacterRepository $characterRepository)
    {
        $this->entityManager=$entityManager;
        $this->characterRepository=$characterRepository;
        $this->serializer=$serializer;
        $this->hub=$hub;

    }

    /**
     * set a new round of the game
     *
     * @param Room $room
     * @return void
     */
    public function set($room){

                

        //remise par défaut des paramètres utile à la partie
        // pour chaque joueur
        $characters = $room->getCharacters();
        foreach($characters as $character){
            $character->setVoteCount(0);
            $character->setHint(null);
            $character->setVoted(false);
        }

        //On convertit la collection de participants 
        //en array PHP pour pouvoir le manipuler plus facilement
        $charactersArray=[];
        foreach($characters as $character){
            $charactersArray[]=$character;
        }

        //randomisation de l'ordre des joueurs
        shuffle($charactersArray);
        //enregistrement en base de donnée de l'ordre du tour à venir
        for($i=0; $i<$room->getCharacters()->count(); $i++){

            $charactersArray[$i]->setRoundOrder($i);
            
        }

        $this->entityManager->flush();

        //récupération du joueur qui jouera en premier
        $players=$this->characterRepository->findBy([
            'Room'=>$room->getId()],
            ['roundOrder'=>'ASC']);

        //notification à l'ensemble des joueurs 
        //du début de tour de la partie 
        $update= new Update(
            'room/'.$room->getId(),
            $this->serializer->serialize([
                'type'=>'GAME_NEW-ROUND',
                'room'=>$room,
                'current_player'=>$players[0]->getId()],
                'json',
                ['groups'=>['get_room_data']]),
        );
        $this->hub->publish($update);
              
            

        // Notif au joueur dont c'est le tour         
        $update= new Update(
            'character/'.$players[0]->getId(),
            $this->serializer->serialize([
                'type'=>'GAME_YOUR-TURN',
                'room'=>$room],
                'json',['groups'=>['get_room_data']]),
        );

        $this->hub->publish($update);


    }


}
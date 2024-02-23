<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class VictoryVerifier
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager=$entityManager;
    }

    /**
     * check if a side has won the game
     *
     * @param \App\Entity\Room $room
     * @return string|null
     */
    public function check($room)
    {
        //récupération des participants de la room
        $characters = $room->getCharacters();

        //décompte du nombre de civil et du nombre de agent
        $nbCivil = 0;
        $nbAgent = 0 ;

        foreach ($characters as $character) {
            if ($character->isIsEliminated()===false && $character->getRole()==='civil') {
                $nbCivil ++;
            } elseif ($character->isIsEliminated()===false && $character->getRole()==='agent') {
                $nbAgent ++;
            }
        }

        $winners = null;

        //si plus aucun agent : victoire des civils
        if ($nbAgent===0) {
            $winners ='civil';
            $this->updatedNbGames($winners, $room);
        }
        //si plus aucun civil ou si un civil et un agent: victoire des agents
        if ($nbCivil===0 || $nbCivil===1&&$nbAgent===1) {
            $winners ='agent';
            $this->updatedNbGames($winners, $room);
        }

        //si il y'a un gagnant on ferme la partie
        if($winners !== null){
        $room->setClosed(true);
        $this->entityManager->flush();
        }
               

        //puis on renvoi l'info du camp gagnant ou null
        return $winners;

    }


    /**
     * at the end of o game set the count of victory/games of players
     * @param string $winners
     * @param \App\Entity\Room $room
     */
    public function updatedNbGames($winners, $room)
    {
        //récupérations des characters
        $characters = $room->getCharacters();

        //Ajout des stat de victoires et de partie jouées en fonction des rôles
        foreach ($characters as $character) {
            if ($character->getRole()===$winners) {
                $user=$character->getUser();
                $user->setPlayedGame($user->getPlayedGame()+1);
                $user->setWonGame($user->getWonGame()+1);
            } else {
                $user=$character->getUser();
                $user->setPlayedGame($user->getPlayedGame()+1);
            }
        }

        $this->entityManager->flush();

    }
}
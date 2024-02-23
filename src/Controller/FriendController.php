<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class FriendController extends AbstractController
{
    /**
     * send a friend request from the current user ton an other by his id
     * 
     * @return Response
     */
    public function sendFriendRequest(Request $request, HubInterface $hub, SerializerInterface $serializer, UserRepository $userRepository): Response
    {

        $data=json_decode($request->getContent(),true);
        $userWhoReceived=$userRepository->find($data['id']);


        //Verification que l'utilisateur existe bien.
        if($userWhoReceived===null){
            return $this->Json(['erreur'=>'cet utilisateur n\'existe pas'], Response::HTTP_NOT_FOUND);
        }
        //Vérification que l'utilisateur n'est pas déjà dans la liste d'ami
        /**@var \App\Entity\User */
        $userWhoSend=$this->getUser();
        $friends = $userWhoSend->getFriends();

        foreach($friends as $friend){
            if($friend===$userWhoReceived){
                return $this->Json(['erreur'=>'cet utilisateur est déjà dans votre liste d\'ami'], Response::HTTP_BAD_REQUEST);
            }
        }

        //verification que l'utilisateur qui fait la demande et celui qui la reçoit sont bien 2 users différents
        if($userWhoReceived===$userWhoSend){
            return $this->Json(['erreur'=>'même si vous vous aimez beaucoup, vous ne pouvez pas être votre propre ami'], Response::HTTP_BAD_REQUEST);

        }
        
        
        $update=new Update(
            'friendrequest/'.$userWhoReceived->getId(),
            $serializer->serialize(['type'=>'FRIEND-REQUEST_RECEIVED','from'=>$userWhoSend],'json',['groups'=>['get_user_who_send_request']])
        );

        $hub->publish($update);
        

        return $this->Json(['message'=>'demande d\'ami envoyée'], Response::HTTP_OK);
        }

    /**
     * manage the choice af a user to accept or refuse a friend request
     * 
     * @return Response
     */
    
     public function answerFriendRequest(Request $request, EntityManagerInterface $entityManager, HubInterface $hub, SerializerInterface $serializer, UserRepository $userRepository): Response
    {

        $data=json_decode($request->getContent(),true);
        $userWhoSend=$userRepository->find($data['id']);

        
         /**@var \App\Entity\User */
         $userWhoAnswer=$this->getUser();

        //si la demande est accepté 
        if($data['choice']===true){
            //On vérifie une dernière fois que les  utilisateurs sont différent
            /**@var \App\Entity\User */
            $userWhoAnswer=$this->getUser();
            if($userWhoSend===$userWhoAnswer){
                return $this->Json(['erreur'=>'même si vous vous aimez beaucoup, vous ne pouvez pas être votre propre ami'], Response::HTTP_BAD_REQUEST);
    
            }
            // puis on ajoute la relation d'ami dans les deux sens aux utilisateurs
            $userWhoSend->addFriend($userWhoAnswer);
            $userWhoAnswer->addFriend($userWhoSend);

            $entityManager->flush();
           

            $update=new Update(
                'friendrequest/'.$userWhoSend->getId(),
                $serializer->serialize(['type'=>'FRIEND-REQUEST_ACCEPTED','from'=>$userWhoAnswer],'json',['groups'=>['get_user_who_send_request']])
            );
    
            $hub->publish($update);

            $update2=new Update(
                'friendrequest/'.$userWhoAnswer->getId(),
                $serializer->serialize(['type'=>'FRIEND-REQUEST_ACCEPTED','from'=>$userWhoSend],'json',['groups'=>['get_user_who_send_request']])
            );
    
            $hub->publish($update2);

            
            return $this->Json(['message'=>'ami ajouté'], Response::HTTP_OK);
        }
                
        //et on confirme la prise en compte de son choix à l'utilisateur ayant refusé
        return $this->Json(['message'=>'demande d\'ami refusée'], Response::HTTP_OK);
        }

    /**
     * Remove a friend from the list of the current user
     *
     * @return void
     */
    public function removeFriend(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository){
        /**@var \App\Entity\User */
        $currentUser=$this->getUser();

        $data=json_decode($request->getContent(),true);
        $friendToRemove=$userRepository->find($data['id']);
        

        if($friendToRemove===null){
            return $this->Json(['erreur'=>'cet utilisateur n\'existe pas'], Response::HTTP_NOT_FOUND);
        }
        
            // suppression de la relation d'ami des deux coté
            $currentUser->removeFriend($friendToRemove);
            $friendToRemove->removeFriend($currentUser);

            $entityManager->flush();

            return $this->Json(['message'=>'ami retiré de votre liste'], Response::HTTP_OK);


    }
}


    


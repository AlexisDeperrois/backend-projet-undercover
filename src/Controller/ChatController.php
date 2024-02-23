<?php

namespace App\Controller;


use App\Entity\Message;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChatController extends AbstractController
{
    /**
     * send message to a room
     */
    public function sendMessage(RoomRepository $roomRepository, Request $request, SerializerInterface $serializer, HubInterface $hub, EntityManagerInterface $entitymanager, ValidatorInterface $validator): JsonResponse
    {   
        $data=json_decode($request->getContent(),true);
        $room=$roomRepository->find($data['roomId']);        

        //vérification de l'existence de la room
        if($room===null){
            return $this->json(['erreur'=>'cette partie n\'existe pas'], Response::HTTP_NOT_FOUND);
        }
        /**@var  \App\Entity\User*/
        $user=$this->getUser();

        //vérification que l'utilisateur est bien un characters de la partie
        $characters = $room->getCharacters();
        
        $exists = $characters->exists(function($key, $value) {
            $user=$this->getUser();
            return $value->getUser() === $user;
        });

        if($exists===false){
            return $this->json(['erreur'=>'vous ne pouvez envoyer de messages dans une partie à laquelle vous ne participez pas'], Response::HTTP_BAD_REQUEST);
        }

        $message = new Message;

        //Récupération du contenu du message dans la request
        $serializer->deserialize($request->getContent(), Message::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $message]);

        //récupération de l'utilisateur courant en tant qu'auteur
        /**@var \App\Entity\User */
        $user=$this->getUser();
        $message->setUser($user);

        //récupération de la room via l'id en paramètre d'URL
        $message->setRoom($room);

        //on vérifie la conformité du message
        $errors = $validator->validate($message);

        $errorsList = [];
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $errorsList[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->json($errorsList, Response::HTTP_BAD_REQUEST);
        }

        //stockage du message en bdd
        $entitymanager->persist($message);
        $entitymanager->flush();

        //envoi via mercure aux autre participant de la game
        $update=new Update(
            'room/'.$room->getId(),
            $serializer->serialize(['type'=>'MESSAGE_RECEIVED' ,'data'=>$message],'json',['groups'=>['get_room_data']])
            
        );

        $hub->publish($update);

        return $this->json('message envoyé', Response::HTTP_CREATED);

        
    }
}

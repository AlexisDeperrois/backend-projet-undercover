<?php

namespace App\Controller;

use Exception;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    /**
     * Find the data from the authenticated User return them as Json
     *
     * @return JsonResponse
     */
    public function getCurrentUser(): JsonResponse
    {
        //Récupération de l'utilisateur connecté
        /**@var \App\Entity\User */
        $user=$this->getUser();
        //si pas d'utilisateur connecté , renvoi d'une 404
        if($user === null){
            return $this->Json(['erreur'=>'cet utilisateur n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        $friendList=$user->getFriends();
        // renvoi des donnés en JSon via le serializer
        return $this->json(
            [
            'user'=>$user,
            'friends'=>$friendList,
            ],
            Response::HTTP_OK,
            [],
            ['groups'=>['get_current_user']]);
    }



    /**
     * Find a User whith an id and return the Entity found as Json
     *
     * @param User|null $user get from Paramconverter
     * @return JsonResponse the Entity found as Json
     */
    public function getUserById(User $user=null): JsonResponse
    {
        //si pas d'utilisateur trouvé , renvoi d'une 404
        if($user === null){
            return $this->Json(['erreur'=>'cet utilisateur n\'existe pas'], Response::HTTP_NOT_FOUND);
        }
        // renvoi des donnés en JSon via le serializer
        return $this->json(
            [
            'user'=>$user,
            ],
            Response::HTTP_OK,
            [],
            ['groups'=>'get_user_by_id']);
    }


    /**check if a User already exist with the given string as pseudo ( case-sensitive)
     * 
     * @param string pseudo to find in bdd
     * 
     * @return boolean 
     */
    public function checkIfAvalaiblePseudo($params,UserRepository $userRepository){
        $existingUsers = $userRepository->findBy(['pseudo'=>$params]);
        if(!empty($existingUsers)){
            foreach ($existingUsers as $user) {
                if ($user->getPseudo()===$params) {
                    return $this->Json(['available'=>false], Response::HTTP_OK);
                }
            }   
        }
        
        return $this->Json(['available'=>true], Response::HTTP_OK);
    }

    /**
     * Get the users which pseudo contain the string given and return them as Json data
     *
     * @param [string] pseudo or part of it to search
     
     * @return JsonResponse
     */
    public function searchUsersWithPseudo($params, UserRepository $userRepository): JsonResponse
    {   

        //Récupération de l'utilisateur courant et des ses amis
        /**@var \App\Entity\User */
        $currentUser=$this->getUser();

        //appel du UserRepository pour récupérer les utilisateurs en lui passant le paramètre récupéré en URL
        $usersWithPseudoWhichMatch =$userRepository->findWithPseudo($params);
      
        $users=[];

        //on ne conserve que les user qui ne sont ni l'utilisateur courant, ni un utilisateur déjà présent dans sa liste d'ami. 
        foreach($usersWithPseudoWhichMatch as $userToTest){
                if($userToTest!==$currentUser && !$exists = $userToTest->getFriends()->exists(function($key, $value) {
                    $user=$this->getUser();
                    return $value === $user;
                })){
                    $users[]=$userToTest;
                }
        }

        
        // renvoi des donnés en JSon via le serializer
        return $this->json(
            [
            'user'=>$users,
            ],
            Response::HTTP_OK,
            [],
            ['groups'=>'get_one_user']);
    }

    /**
     * check if a password is really the user's one 
     *
     * @param [string] password ton compare with user's one.
     * @return bool
     */
    public function passwordConfirmation($params, UserPasswordHasherInterface $userPasswordHasher){
        //récupération de l'utilisateur connecté et donc a éditer

        /**@var \App\Entity\User $user */
        $user=$this->getUser();

         if($userPasswordHasher->isPasswordValid($user,$params)){
            return $this->json(true,Response::HTTP_OK);
        }

        return $this->json(false,Response::HTTP_OK);
    }

    /**
     * Edit the current user form the BDD with data send in the request
     *
     * @return JsonResponse
     */
    public function editUser(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        try {
            //récupération de l'utilisateur connecté et donc a éditer
            /**@var \App\Entity\User $user */
            $user=$this->getUser();

            $serializer->deserialize($request->getContent(), User::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $user]);

            $errors = $validator->validate($user);

            $errorsList = [];
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $errorsList[$error->getPropertyPath()][] = $error->getMessage();
                }

                return $this->json($errorsList, Response::HTTP_BAD_REQUEST);
            }

            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $user->getPassword()
                )
            );
        
            $entityManager->flush();

            return $this->json([
                'message' => 'c\'est ok j\'ai édité',
            ], Response::HTTP_OK);

        }catch(Exception $e){
            return  $this->json([
                'message' => 'un truc s\'est mal passé',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}

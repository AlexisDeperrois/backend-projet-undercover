<?php

namespace App\Controller;


use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ApiLoginController extends AbstractController
{
    /**
     * Lgin
     */
    public function index(Request $request, JWTTokenManagerInterface $JWTManager, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {   
        try {

            //fetch data's user from request :
            $requestContent = json_decode($request->getContent(), true);
            $username=$requestContent['username'];

            $user = $userRepository->findOneBy(['email'=>$username]);
            
            //user's update
            $user->setUpdatedAt(new DateTime('now'))
                 ->setIsOnline(true);
            
            $entityManager->flush();

            //create JWT token
            $token = $JWTManager->create($user);

            //return token and status code if ok
            return $this->json([
                'connexion réussie' => 'Bienvenu '.$user->getPseudo(),
                'token' => $token,
            ], Response::HTTP_OK);
        }catch(Exception $e){

            //Return status code if error
            return $this->json([
                'erreur' => $e,
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    
    /**
     * Logout
     */
    public function logout(EntityManagerInterface $entityManager): JsonResponse
    {
        //fetch data's user from request :
               
        /**@var App\Entity\User $user */
        $user=$this->getUser();
        
        
        //user's update
        $user->setIsOnline(false);
        $entityManager->flush();


        return $this->json([
            'Déconnexion réussie' => 'Au revoir '.$user->getPseudo(),
        ], Response::HTTP_OK);
    }
    
}

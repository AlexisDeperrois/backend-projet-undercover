<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailVerifier;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    /**
     * Register a new User
     */
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, SerializerInterface $serializer,ValidatorInterface $validator): Response
    {
        //Recupération des données de la request et création d'un objet user avec
        $requestContent = $request->getContent();
        $user = $serializer->deserialize($requestContent, User::class, 'json');
                
        //Verification de la validité des informations envoyé pour la création
        $errors = $validator->validate($user);

        $errorsList = [];
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $errorsList[$error->getPropertyPath()][] = $error->getMessage();
            }

            //si erreur retour message avec les champs invalide + code erreur
            return $this->json($errorsList, Response::HTTP_BAD_REQUEST);
        }

        //si ok on hache le password pour le stocker en BDD
        $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $user->getPassword()
                    )
                );
        
        //On enregistre le nouvel utilisateur
        $entityManager->persist($user);
        $entityManager->flush();

        // generate a signed url and email it to the user
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('no-reply@undercover.axolotl-concept.com', 'La Team Undercover'))
                ->to($user->getEmail())
                ->subject('Inscription sur Undercover validée')
                ->htmlTemplate('registration/confirmation_email.html.twig')
        );
        // do anything else you need here, like send an email

        return $this->json(['message'=>'création de compte réussie'], Response::HTTP_CREATED); 
    
    }

    /**
     * @Route("/verify/email", name="app_verify_email")
     */
    public function verifyUserEmail(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $this->getUser());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
    }
}

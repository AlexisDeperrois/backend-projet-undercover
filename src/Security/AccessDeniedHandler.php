<?php

// src/Security/AuthenticationEntryPoint.php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
   
    public function handle(Request $request, AccessDeniedException $accessDeniedException)
    {
        return new JsonResponse(['message'=>'Accès interdit, vous n\'avez pas les droits pour accéder à cette route'], Response::HTTP_FORBIDDEN);
    }
   
}
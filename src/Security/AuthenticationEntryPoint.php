<?php

// src/Security/AuthenticationEntryPoint.php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
   
    public function start(Request $request, AuthenticationException $authException = null): JsonResponse
    {
                    return new JsonResponse(['message'=>'Accès interdit, vous devez être identifié'], Response::HTTP_UNAUTHORIZED);
    }
}
<?php

namespace App\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Yaml\Yaml;

class GeneratorWord
{
    public function genererMots()
    {
        // Charge la liste de catégories et de mots depuis mon fichier YAML
        $categories = Yaml::parseFile('../config/synonymes.yaml');
        $categorieAleatoire = array_rand($categories);
        $mots = $categories[$categorieAleatoire];

        // Sélectionne deux mots aléatoires dans la catégorie sélectionnée
        $clesAleatoires = array_rand($mots, 2);
        $mots[] = $mots[$clesAleatoires[0]];
        $mots[] = $mots[$clesAleatoires[1]];

        // Retourne une réponse JSON contenant la catégorie et les deux mots aléatoires
        return $mots;
    }
}

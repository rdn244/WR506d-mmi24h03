<?php

/*
 * Fournit l'EntityManager à PHPStan (extension Doctrine) pour lire les métadonnées des entités.
 * Aucune connexion à la base n'est ouverte.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();

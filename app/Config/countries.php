<?php

declare(strict_types=1);

// Pays proposés à l'inscription et indicatifs téléphoniques acceptés (codes ISO 3166-1 alpha-2).
// Décision du 17/10/2026 : le service s'adresse au Mali, au Burkina Faso et à la Côte d'Ivoire.
// Pour ouvrir un pays : ajouter son code ici et son indicatif dans App\Services\PhoneNumber::COUNTRY_CODES.
return ['ML', 'BF', 'CI'];

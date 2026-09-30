<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Orange;

use RuntimeException;

/**
 * Échec d'un appel à l'API Orange qui ne vient pas du message lui-même
 * (jeton impossible à obtenir, Orange injoignable...) : toujours réessayable.
 */
class OrangeSmsException extends RuntimeException {}

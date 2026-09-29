<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur métier qui doit être renvoyée au client (message en français).
 */
class BusinessException extends RuntimeException
{
}

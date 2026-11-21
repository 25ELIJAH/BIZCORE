<?php

namespace App\Exceptions;

use RuntimeException;

/** A business rule was violated (not a bug). The message is safe to show to the user. */
class BusinessRuleException extends RuntimeException {}

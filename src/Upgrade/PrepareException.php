<?php

declare( strict_types=1 );

namespace WMCZ\Upgrade;

/** An expected failure (bad input, failed download or verification, ...) */
class PrepareException extends \RuntimeException {
}

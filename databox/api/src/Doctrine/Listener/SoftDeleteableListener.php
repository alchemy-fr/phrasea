<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Doctrine\Common\EventArgs;
use Gedmo\SoftDeleteable\SoftDeleteableListener as BaseSoftDeleteableListener;

/**
 * See https://github.com/doctrine-extensions/DoctrineExtensions/issues/1175#issuecomment-149493409.
 */
class SoftDeleteableListener extends BaseSoftDeleteableListener
{
    private static bool $enabled = true;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * {@inheritdoc}
     */
    public function onFlush(EventArgs $args)
    {
        if (self::$enabled) {
            parent::onFlush($args);
        }
    }
}

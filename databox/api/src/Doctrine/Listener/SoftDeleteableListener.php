<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Gedmo\SoftDeleteable\SoftDeleteableListener as BaseSoftDeleteableListener;
use Doctrine\Common\EventArgs;

/**
 * See https://github.com/doctrine-extensions/DoctrineExtensions/issues/1175#issuecomment-149493409
 */
class SoftDeleteableListener extends BaseSoftDeleteableListener
{
    /**
     * @inheritdoc
     */
    public function onFlush(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        $om = $ea->getObjectManager();
        if (!$om->getFilters()->isEnabled('softdeleteable')) {
            return;
        }

        parent::onFlush($args);
    }
}

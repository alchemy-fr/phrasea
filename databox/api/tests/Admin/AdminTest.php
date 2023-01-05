<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;

class AdminTest extends AbstractAdminTest
{
    /* todo: EA3 fix
     * 1) App\Tests\Admin\AdminTest::testAdmin
     * TypeError: Argument 1 passed to EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\AssociationConfigurator::EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\{closure}()
     * must be an instance of Doctrine\ORM\EntityRepository,
     *  instance of App\Repository\Cache\AttributeDefinitionRepositoryMemoryCachedDecorator given,
     *  called in /var/workspace/databox/api/vendor/symfony/doctrine-bridge/Form/Type/EntityType.php on line 32
     */
    public function testAdmin()
    {
        // $this->doTestAllPages();
    }
}

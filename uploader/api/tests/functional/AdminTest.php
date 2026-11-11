<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AdminBundle\Tests\AbstractAdminTestCase;
use App\Controller\Admin\GlobalPermissionsController;

class AdminTest extends AbstractAdminTestCase
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    public function testGlobalPermissionPages(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $router = static::getContainer()->get('router');

        foreach ([
            GlobalPermissionsController::ROUTE_FORM_SCHEMA => 'form_schema',
            GlobalPermissionsController::ROUTE_TARGET_DATA => 'target_params',
            GlobalPermissionsController::ROUTE_UPLOAD_LINK => 'upload_link',
        ] as $route => $objectType) {
            $this->client->request('GET', $router->generate($route));
            $this->assertResponseIsSuccessful($route);
            $this->assertStringContainsString('Permissions on all <b>'.$objectType.'</b>', (string) $this->client->getResponse()->getContent(), $route);
        }
    }
}

<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PhpInfoController extends AbstractAdminController
{
    /**
     * @Route("/php-info", name="phpinfo")
     */
    public function phpInfoAction()
    {
        $this->denyAccessUnlessGranted('ROLE_TECH');
        ob_start();
        phpinfo();
        $content = ob_get_contents();
        ob_end_clean();

        // too bad phpinfo() contains css that conflicts with ea
        // keep only the html
        $content = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $content);

        return $this->render('@AlchemyAdmin/phpinfo.html.twig', [
            'data' => $content,
        ]);
    }
}

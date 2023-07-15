<?php

return [
    Alchemy\CoreBundle\AlchemyCoreBundle::class => ['all' => true],
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Nelmio\CorsBundle\NelmioCorsBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle::class => ['dev' => true, 'test' => true],
    Fidry\AliceDataFixtures\Bridge\Symfony\FidryAliceDataFixturesBundle::class => ['dev' => true, 'test' => true],
    Hautelook\AliceBundle\HautelookAliceBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Oneup\FlysystemBundle\OneupFlysystemBundle::class => ['all' => true],
    Alchemy\AuthBundle\AlchemyAuthBundle::class => ['all' => true],
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    Alchemy\AdminBundle\AlchemyAdminBundle::class => ['all' => true],
    Alchemy\ReportBundle\AlchemyReportBundle::class => ['all' => true],
    FOS\OAuthServerBundle\FOSOAuthServerBundle::class => ['all' => true],
    Alchemy\OAuthServerBundle\AlchemyOAuthServerBundle::class => ['all' => true],
    Alchemy\AclBundle\AlchemyAclBundle::class => ['all' => true],
    Snc\RedisBundle\SncRedisBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Exercise\HTMLPurifierBundle\ExerciseHTMLPurifierBundle::class => ['all' => true],
    Alchemy\NotifyBundle\AlchemyNotifyBundle::class => ['all' => true],
    OldSound\RabbitMqBundle\OldSoundRabbitMqBundle::class => ['all' => true],
    Arthem\Bundle\RabbitBundle\ArthemRabbitBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Alchemy\StorageBundle\AlchemyStorageBundle::class => ['all' => true],
];

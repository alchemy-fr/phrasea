<?php

declare(strict_types=1);

namespace App;

use App\Entity\EnvVar;
use Doctrine\ORM\EntityManagerInterface;

class ConfigurationManager
{
    public const CONFIG = [
        'globalCSS' => [
            'name' => 'GLOBAL_CSS',
            'type' => 'string',
        ],
        'devMode' => [
            'name' => 'DEV_MODE',
            'type' => 'bool',
        ],
        'displayServicesMenu' => [
            'name' => 'DISPLAY_SERVICES_MENU',
            'type' => 'bool',
        ],
        'dashboardBaseUrl' => [
            'name' => 'DASHBOARD_BASE_URL',
            'type' => 'string',
        ],
        'mapBoxToken' => [
            'name' => 'MAPBOX_TOKEN',
            'type' => 'string',
        ],
        'zippyEnabled' => [
            'name' => 'ZIPPY_BASE_URL',
            'cast' => 'bool',
        ],
        'sidebarDefaultOpen' => [
            'name' => 'SIDEBAR_DEFAULT_OPEN',
            'type' => 'bool',
        ],
    ];
    private EntityManagerInterface $em;
    private ?array $cache = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    private function get(string $key)
    {
        $this->load();

        $c = self::CONFIG[$key];

        $value = $this->cache[$c['name']] ?? null;
        if (null === $value) {
            $value = getenv($c['name']);
            if (false === $value) {
                $value = null;
            }
        }

        if (null === $value && isset($c['default'])) {
            $value = (string)$c['default'];
        }

        if ($c['type'] ?? false) {
            $value = $this->normalizeValue($value, $c['type']);
        }
        if ($c['cast'] ?? false) {
            $value = $this->castValue($value, $c['cast']);
        }

        return $value;
    }

    private function load(): void
    {
        if (null !== $this->cache) {
            return;
        }

        $envVars = $this->em->getRepository(EnvVar::class)->findAll();

        foreach ($envVars as $envVar) {
            $this->cache[$envVar->getName()] = $envVar->getValue();
        }
    }

    public function getArray(): array
    {
        $data = [];
        foreach (self::CONFIG as $key => $c) {
            $data[$key] = $this->get($key);
        }

        return $data;
    }

    private function normalizeValue(?string $value, string $type)
    {
        if (null === $value) {
            return null;
        }

        switch ($type) {
            case 'int':
                return intval($value);
            case 'string':
                return $value;
            case 'bool':
                return in_array(strtolower(trim($value)), [
                    '1',
                    'true',
                    'on',
                ]);
        }
    }

    private function castValue($value, string $type)
    {
        switch ($type) {
            case 'bool':
                return !empty($value);
            case 'string':
                return (string)$value;
            case 'int':
                return intval($value);
        }
    }
}

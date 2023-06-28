<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class YamlToApiResourceCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:migrate:api-platform')
            ->addArgument('file', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');

        $this->dump($file, $output);

        return 0;
    }
    
    private function dump(string $file, OutputInterface $output): void
    {
        $content = Yaml::parseFile($file);

        $d = reset($content['resources']);

        $sections = [];
        if (isset($d['attributes'])) {
            foreach ($d['attributes'] as $k => $n) {
                $nk = match ($k) {
                    'normalization_context' => 'normalizationContext',
                    'denormalization_context' => 'denormalizationContext',
                    default => $k,
                };
                $v = $d['attributes'][$k] ?? null;
                if (!empty($v)) {
                    $sections[] = sprintf(<<<EOF
        %s: %s
    EOF, $nk, ltrim(dumpPhpVar($v, 1)));
                }
            }
        }

        $operations = [];
        if (isset($d['itemOperations']) && is_array($d['itemOperations'])) {
            foreach ($d['itemOperations'] as $k => $op) {
                $operations[] = dumpOperation($k, $op, true);
            }
        }
        if (isset($d['collectionOperations']) && is_array($d['collectionOperations'])) {
            foreach ($d['collectionOperations'] as $k => $op) {
                $operations[] = dumpOperation($k, $op, false);
            }
        }

        $output->writeln(sprintf(<<<EOF
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    shortName: %s,
    operations: [
        %s
    ],
    %s
)]
EOF,
            dumpPhpVar($d['shortName']),
            ltrim(implode(",\n", $operations)),
            ltrim(implode(",\n", $sections)),
));
    }
}

function dumpOperation(string $name, ?array $op, bool $itemOp): string {
    $op ??= [];

    $params = [];
    if (in_array($name, [
        'get',
        'post',
        'delete',
        'patch',
        'put',
    ], true)) {
        $metadata = match($name) {
            'get' => $itemOp ? 'Get' : 'GetCollection',
            'post' => 'Post',
            'delete' => 'Delete',
            'patch' => 'Patch',
            'put' => 'Put',
        };
    } else {
        $params['name'] = $name;
        $metadata = match(strtoupper((string) $op['method'])) {
            'POST' => 'Post',
            'DELETE' => 'Delete',
            'PUT' => 'Put',
            'PATCH' => 'Patch',
            'GET' => 'Get',
        };
    }

    unset($op['method']);
    foreach ($op as $k => $v) {
        $nk = match ($k) {
            'path' => 'uriTemplate',
            'security_post_denormalize' => 'securityPostDenormalize',
            'normalization_context' => 'normalizationContext',
            'denormalization_context' => 'denormalizationContext',
            'validation_groups' => 'validationContext',
            'openapi_context' => 'openapiContext',
            default => $k,
        };
        if ('validation_groups' === $k) {
            $v = ['groups' => $v];
        }

        $params[$nk] = $v;
    }

    $paramsStr = '';
    $i = 0;
    $many = count($params) > 1;
    if ($many) {
        $paramsStr .= PHP_EOL.i(3);
    }
    foreach ($params as $k => $v) {
        if ($i++ > 0) {
            $paramsStr .= ','.PHP_EOL.i(3);
        }
        $paramsStr .= sprintf('%s: %s', $k, ltrim(dumpPhpVar($v, 3)));
    }
    if ($many) {
        $paramsStr .= PHP_EOL.i(2);
    }

    return sprintf(<<<EOF
        new %s(%s)
EOF,
    $metadata, $paramsStr
);
}

function dumpPhpVar(mixed $v, int $indent = 0): string {
    if (is_string($v)) {
        if (str_contains($v, '\\') && class_exists($v)) {
            return '\\'.$v.'::class';
        }
        return sprintf("'%s'", str_replace("'", "\\'", $v));
    } elseif (is_array($v)) {
        return dumpArray($v, $indent);
    } elseif (is_bool($v)) {
        return $v ? 'true' : 'false';
    } else {
       return  (string) $v;
    }
}

function i(int $indent): string {
    return str_repeat(' ', $indent * 4);
}

function dumpArray(array $a, int $indent): string
{
    $assoc = isAssociativeArray($a);
    $o = i($indent).'[';
    $many = count($a) > 1 || (count($a) === 1 && is_array(reset($a)));
    if ($many) {
        $o .= PHP_EOL;
    }
    foreach ($a as $k => $v) {
        $o .= $assoc
            ? sprintf("%s'%s' => %s", i(!$many ? 0 : $indent + 1), $k, ltrim(dumpPhpVar($v, $indent + 1)))
            : sprintf("%s%s", i(!$many ? 0 : $indent + 1), ltrim(dumpPhpVar($v, $indent + 1)))
        ;
        if ($many) {
            $o .= ','.PHP_EOL;
        }
    }
    if ($many) {
        $o .= i($indent);
    }
    $o .= ']';

    return $o;
}

function isAssociativeArray(array $arr): bool
{
    return array_keys($arr) !== range(0, count($arr) - 1);
}

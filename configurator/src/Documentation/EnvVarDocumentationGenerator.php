<?php

namespace App\Documentation;

use Alchemy\CoreBundle\Documentation\DocumentationGenerator;
use App\Documentation\Parser\Dto\EnvVar;
use App\Documentation\Parser\EnvParser;

class EnvVarDocumentationGenerator extends DocumentationGenerator
{
    public function __construct(
        private EnvParser $envParser,
    ) {
    }

    public function getContent(): ?string
    {
        $envVars = $this->envParser->parse(file_get_contents(__DIR__.'/../../.env.default'));

        $output = '';

        $categories = [];
        foreach ($envVars as $envVar) {
            $category = $envVar->category ?? 'Uncategorized';
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $envVar;
        }

        foreach ($categories as $category => $envVars) {
            $output .= "## {$category}\n\n";

            /** @var EnvVar $envVar */
            foreach ($envVars as $envVar) {
                $output .= "### `{$envVar->name}`\n\n";

                if ($envVar->description) {
                    $output .= "{$envVar->description}\n\n";
                }

                $output .= '- **Default Value:** '.($envVar->defaultValue ? "`{$envVar->defaultValue}`" : 'Unset')."\n";
                if (null !== $envVar->allowEmpty) {
                    $output .= '- **Not blank:** `'.($envVar->allowEmpty ? 'true' : 'false')."`\n";
                }
                if ($envVar->changeMe) {
                    $output .= "- ⚠️ This variable must be set/changed. Please ensure to set it to a secure value in production environments.\n";
                }
                if ($envVar->rawSecret) {
                    $output .= "- **Default raw secret:** `{$envVar->rawSecret}`\n";
                }

                if (!empty($envVar->tags)) {
                    $output .= '- **Tags:** `'.implode('`, `', $envVar->tags)."`\n";
                }

                $output .= "\n---\n\n";
            }
        }

        return $output;
    }

    public function getPath(): string
    {
        return '_env_variables.md';
    }
}

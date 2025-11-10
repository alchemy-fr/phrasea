<?php

namespace App\Documentation;

use Alchemy\CoreBundle\Documentation\DocumentationGenerator;
use App\Documentation\Parser\Dto\EnvVar;
use App\Documentation\Parser\EnvParser;

class EnvVarDocumentationGenerator extends DocumentationGenerator
{
    public function __construct(
        private readonly EnvParser $envParser,
    ) {
    }

    public function getContent(): ?string
    {
        $categories = $this->envParser->parse(file_get_contents(__DIR__.'/../../.env.default'));

        $output = '';

        foreach ($categories as $category) {
            $title = $category->title ?: $category->name;
            $output .= "## {$title}\n\n";
            if (!empty($category->description)) {
                $output .= "{$category->description}\n\n";
            }

            /** @var EnvVar $envVar */
            foreach ($category->getEnvVars() as $envVar) {
                if ($envVar->deprecated) {
                    $output .= "### ~~`{$envVar->name}`~~ **(deprecated)** \n\n";
                } else {
                    $output .= "### `{$envVar->name}`\n\n";
                }

                if ($envVar->description) {
                    $output .= "{$envVar->description}\n\n";
                }

                if (!empty($envVar->type)) {
                    $output .= "- **Type:** `{$envVar->type}`\n";
                }

                if (!empty($envVar->tags)) {
                    $output .= '- **Tags:** `'.implode('`, `', $envVar->tags)."`\n";
                }

                if ($envVar->changeMe) {
                    $output .= "> ⚠️ This variable must be set/changed. Please ensure to set it to a secure value in production environments.\n\n";
                }

                $output .= '- **Default Value:** '.($envVar->defaultValue ? "`{$envVar->defaultValue}`" : 'Unset')."\n";
                if ($envVar->rawSecret) {
                    $output .= "- **Default raw secret:** `{$envVar->rawSecret}`\n";
                }

                if (null !== $envVar->allowEmpty) {
                    $output .= '- **Not blank:** `'.($envVar->allowEmpty ? 'true' : 'false')."`\n";
                }

                if (!empty($envVar->example)) {
                    $output .= "- **Example:** `{$envVar->example}`\n";
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

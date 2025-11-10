<?php

namespace App\Documentation\Parser;

use App\Documentation\Parser\Dto\Category;
use App\Documentation\Parser\Dto\EnvVar;

final readonly class EnvParser
{
    private const string KEYWORD_CATEGORY = 'category';
    private const string KEYWORD_CATEGORY_DESCRIPTION = 'category-desc';
    private const string KEYWORD_CATEGORY_TITLE = 'category-title';
    private const string TYPE_BOOLEAN = 'bool';
    private const string TYPE_STRING = 'string';
    private const string KEYWORD_TAG = 'tag';
    private const string KEYWORD_EXAMPLE = 'example';
    private const string KEYWORD_ALLOW_EMPTY = 'allow-empty';
    private const string KEYWORD_DEPRECATED = 'deprecated';
    private const string KEYWORD_CHANGE_ME = 'change-me';
    private const string KEYWORD_TYPE = 'type';
    private const string KEYWORD_RAW_SECRET = 'raw-secret';
    private const string KEYWORD_DESCRIPTION = 'desc';

    /**
     * @return array<string, Category>
     */
    public function parse(string $envContent): array
    {
        preg_match_all('/^(\n?)((?:(?:^|\n)#.*)*)(?:\n([A-Z_][A-Z0-9_]*)(?:=(.*?))?|\n)$/m', $envContent, $variables);

        $categories = [];
        $keywords = [
            self::KEYWORD_CATEGORY => self::TYPE_STRING,
            self::KEYWORD_TAG => self::TYPE_STRING,
            self::KEYWORD_EXAMPLE => self::TYPE_STRING,
            self::KEYWORD_ALLOW_EMPTY => self::TYPE_BOOLEAN,
            self::KEYWORD_DEPRECATED => self::TYPE_BOOLEAN,
            self::KEYWORD_CHANGE_ME => self::TYPE_BOOLEAN,
            self::KEYWORD_TYPE => self::TYPE_STRING,
            self::KEYWORD_RAW_SECRET => self::TYPE_STRING,
            self::KEYWORD_CATEGORY_DESCRIPTION => self::TYPE_STRING,
            self::KEYWORD_CATEGORY_TITLE => self::TYPE_STRING,
        ];

        $lastInfo = [];
        foreach ($variables[0] as $index => $fullMatch) {
            $separator = $variables[1][$index];
            if ('' !== $separator) {
                // New section, reset last info
                $lastInfo = [];
            }
            $info = $lastInfo;
            unset($info[self::KEYWORD_EXAMPLE]);
            unset($info[self::KEYWORD_RAW_SECRET]);
            unset($info[self::KEYWORD_CHANGE_ME]);
            unset($info[self::KEYWORD_ALLOW_EMPTY]);
            unset($info[self::KEYWORD_TYPE]);
            unset($info[self::KEYWORD_DESCRIPTION]);
            unset($info[self::KEYWORD_DEPRECATED]);

            $commentBlock = trim($variables[2][$index] ?? '');
            $name = $variables[3][$index];
            $defaultValue = '' !== $variables[4][$index] ? $variables[4][$index] : null;

            if ($commentBlock) {
                $comments = explode("\n", $commentBlock);
                $descriptionLines = [];

                foreach ($comments as $comment) {
                    $comment = trim($comment, "# \n");

                    foreach ($keywords as $key => $type) {
                        if (preg_match('#^@'.preg_quote($key, '#').'(\s|$)#', $comment)) {
                            $info[$key] = trim(substr($comment, strlen($key) + 1));
                            if (self::TYPE_BOOLEAN === $type) {
                                $info[$key] = filter_var($info[$key] ?: true, FILTER_VALIDATE_BOOLEAN);
                            }

                            if (self::KEYWORD_CATEGORY === $key && !isset($categories[$info[$key]])) {
                                $categories[$info[$key]] ??= new Category($info[$key]);
                            } elseif (self::KEYWORD_CATEGORY_DESCRIPTION === $key) {
                                [$category, $desc] = explode(' ', $info[$key], 2);
                                $category = trim($category);
                                $categories[$category] ??= new Category($category);
                                $categories[$category]->description = trim($desc);
                            } elseif (self::KEYWORD_CATEGORY_TITLE === $key) {
                                [$category, $title] = explode(' ', $info[$key], 2);
                                $category = trim($category);
                                $categories[$category] ??= new Category($category);
                                $categories[$category]->title = trim($title);
                            }

                            continue 2;
                        }
                    }

                    $descriptionLines[] = trim($comment);
                }

                if (!empty($descriptionLines)) {
                    $info[self::KEYWORD_DESCRIPTION] = implode(' ', $descriptionLines);
                }

                $lastInfo = $info;
            }

            if (empty(trim($name))
                || (isset($info[self::KEYWORD_DESCRIPTION]) && 1 === count($info))) {
                continue;
            }

            $category = $info[self::KEYWORD_CATEGORY] ?? 'Uncategorized';
            $categories[$category] ??= new Category($category);

            $categories[$category]->addEnvVar(new EnvVar(
                name: $name,
                defaultValue: $defaultValue,
                description: $info[self::KEYWORD_DESCRIPTION] ?? null,
                deprecated: $info[self::KEYWORD_DEPRECATED] ?? false,
                allowEmpty: $info[self::KEYWORD_ALLOW_EMPTY] ?? null,
                changeMe: $info[self::KEYWORD_CHANGE_ME] ?? false,
                tags: !empty($info[self::KEYWORD_TAG]) ? explode(',', $info[self::KEYWORD_TAG]) : [],
                type: $info[self::KEYWORD_TYPE] ?? null,
                example: $info[self::KEYWORD_EXAMPLE] ?? null,
                rawSecret: $info[self::KEYWORD_RAW_SECRET] ?? null,
            ));
        }

        return $categories;
    }
}

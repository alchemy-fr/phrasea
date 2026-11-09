<?php

declare(strict_types=1);

namespace App\Form\Builder;

/**
 * The keys Databox reads besides the attribute slugs (see Databox AttributeDataImporter),
 * and the notification opt-in handled by the uploader itself.
 */
enum Builtin: string
{
    case Name = 'databox_name';
    case Tags = 'databox_tags';
    case IsStory = 'databox_is_story';
    case StoryName = 'databox_story_name';
    case Notify = '__notify';

    public function getType(): FieldType
    {
        return match ($this) {
            self::Name, self::StoryName => FieldType::Text,
            self::Tags => FieldType::Tag,
            self::IsStory, self::Notify => FieldType::Boolean,
        };
    }

    public function isMultiple(): bool
    {
        return self::Tags === $this;
    }
}

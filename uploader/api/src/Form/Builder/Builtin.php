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
    case IsStory = 'databox_is_story';
    case StoryName = 'databox_story_name';
    case Notify = '__notify';

    public function getType(): FieldType
    {
        return match ($this) {
            self::Name, self::StoryName => FieldType::Text,
            self::IsStory, self::Notify => FieldType::Boolean,
        };
    }
}

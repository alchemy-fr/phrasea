<?php

declare(strict_types=1);

namespace App\Form\Builder;

enum FieldWidget: string
{
    case Autocomplete = 'autocomplete';
    case Select = 'select';
    case Checkboxes = 'checkboxes';
    case Radios = 'radios';
    case Switch = 'switch';
    case Checkbox = 'checkbox';
}

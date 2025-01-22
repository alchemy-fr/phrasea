<?php

namespace App\Entity;

interface FollowableInterface
{
    public function getTopicKeys(): array;
}

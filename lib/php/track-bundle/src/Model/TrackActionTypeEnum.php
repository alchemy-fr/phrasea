<?php

namespace Alchemy\TrackBundle\Model;

enum TrackActionTypeEnum: int
{
    case UPDATE = 1;
    case CREATE = 2;
    case DELETE = 3;
}

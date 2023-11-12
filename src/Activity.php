<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

enum Activity
{
    case sourcesModified;
    case testsModified;
    case fixturesModified;
    case propertiesModified;
    case start;
}

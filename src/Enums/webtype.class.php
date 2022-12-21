<?php

namespace API\Utils;

require_once(__DIR__ . "/enum.class.php");

abstract class WebTypes extends Enum {
    const Web = 0x1;
    const PageType = 0x2;
    const Profile = 0x3;
    const Blog = 0x4;
}
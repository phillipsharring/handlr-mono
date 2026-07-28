<?php

declare(strict_types=1);

namespace Handlr\Database;

/**
 * The kind of write a {@see Change} records.
 */
enum ChangeOp
{
    case Insert;
    case Update;
    case Delete;
}

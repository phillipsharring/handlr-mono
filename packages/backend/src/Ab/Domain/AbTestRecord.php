<?php

declare(strict_types=1);

namespace Handlr\Ab\Domain;

use Handlr\Database\Record;

/**
 * @property int $id
 * @property string $name
 * @property string $variants
 * @property string $status
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class AbTestRecord extends Record
{
    protected bool $useUuid = false;

    protected array $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
    ];

    public function getVariants(): array
    {
        $v = $this->variants;
        if (is_string($v)) {
            return json_decode($v, true) ?: [];
        }
        return is_array($v) ? $v : [];
    }
}

<?php

declare(strict_types=1);

namespace App\Support\ProductSheet;

/**
 * One column of the product spreadsheet. Immutable description used by the
 * export, the template, the importer's header mapping and the docs.
 */
final class Column
{
    public const TEXT = 'text';
    public const MONEY = 'money';
    public const INTEGER = 'integer';
    public const BOOLEAN = 'boolean';
    public const STATUS = 'status';
    public const LIST = 'list';
    public const REFERENCE = 'reference';

    /** @var string */
    public $key;
    /** @var string */
    public $header;
    /** @var array<int,string> extra heading spellings (already human-readable; normalised on lookup) */
    public $aliases;
    /** @var bool required when the row creates a new product */
    public $requiredOnCreate;
    /** @var string one of the type constants */
    public $type;
    /** @var string what admins are told about it */
    public $help;
    /** @var int Excel column width */
    public $width;
    /** @var string core | pricing | seo | merch | media */
    public $group;

    /** @param array<string,mixed> $def */
    public function __construct(array $def)
    {
        $this->key = $def['key'];
        $this->header = $def['header'];
        $this->aliases = $def['aliases'] ?? [];
        $this->requiredOnCreate = $def['required'] ?? false;
        $this->type = $def['type'] ?? self::TEXT;
        $this->help = $def['help'] ?? '';
        $this->width = $def['width'] ?? 18;
        $this->group = $def['group'] ?? 'core';
    }
}

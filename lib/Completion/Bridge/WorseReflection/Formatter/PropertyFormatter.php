<?php

namespace Phpactor\Completion\Bridge\WorseReflection\Formatter;

use Phpactor\WorseReflection\Core\Reflection\ReflectionProperty;
use Phpactor\Completion\Core\Formatter\Formatter;
use Phpactor\Completion\Core\Formatter\ObjectFormatter;
use Phpactor\WorseReflection\TypeUtil;

class PropertyFormatter implements Formatter
{
    public function canFormat(object $object): bool
    {
        return $object instanceof ReflectionProperty;
    }

    public function format(ObjectFormatter $formatter, object $object): string
    {
        assert($object instanceof ReflectionProperty);

        $info = [
            substr((string) $object->visibility(), 0, 3),
        ];

        if ($object->isStatic()) {
            $info[] = ' static';
        }

        $info[] = ' ';
        $info[] = '$' . $object->name();

        if (TypeUtil::isDefined($object->inferredType())) {
            $info[] = ': ' . TypeUtil::short($object->inferredType());
        }

        return implode('', $info);
    }
}

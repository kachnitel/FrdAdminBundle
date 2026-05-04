<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Utils;

use Doctrine\Persistence\Proxy;

class ObjectHelper
{
    /**
     * Get the class name of an object without the namespace.
     */
    public static function getClassName(object $object): string
    {
        return (new \ReflectionClass($object))->getShortName();
    }

    /**
     * Get the real class name, unwrapping Doctrine proxies.
     *
     * @param class-string|object $classOrObject
     * @return class-string
     */
    public static function getRealClass(string|object $classOrObject): string
    {
        // 1. If it's an object, use the fast native operator
        if (is_object($classOrObject)) {
            if ($classOrObject instanceof Proxy) {
                $parent = get_parent_class($classOrObject);
                return $parent !== false ? $parent : $classOrObject::class;
            }
            return $classOrObject::class;
        }

        // 2. If it's a string, we MUST use the function
        // (The 3rd parameter 'true' allows it to check strings and triggers autoloading)
        if (is_subclass_of($classOrObject, Proxy::class, true)) {
            $parent = get_parent_class($classOrObject);
            return $parent !== false ? $parent : $classOrObject;
        }

        return $classOrObject;
    }
}
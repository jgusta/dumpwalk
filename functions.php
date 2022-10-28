<?php

namespace Janky\DumpWalk;

use Closure;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;
use PDOStatement;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use ReflectionParameter;

if (!function_exists('dump_walk')) {
    /**
     * This function is like print_r($node, true) but cleaner.
     *
     * @param        $node
     * @param string $indent_string
     *
     * @return string
     * @version 1.0.1
     */
    function dump_walk($node, string $indent_string = '    '): string {
        return namespace\___dump_walk_recurser($node, $indent_string, 0, null, []);
    }
}

if (!function_exists('___dump_walk_recurser')) {
    /**
     * Recursive function called by dump_walk with a longer
     * signature needed to recursion.
     *
     * @param        $node
     * @param string $indent_string
     * @param int    $depth internal use only
     * @param null   $parentType internal use only
     * @param array  $objects_shown internal use only
     *
     * @return string
     * @version 1.0.1
     */
    function ___dump_walk_recurser($node, string $indent_string = '    ', int $depth = 0, $parentType = null, array $objects_shown = []): string {
        // Process the current node
        try {
            $append = '';
            if (is_array($node)) {
                $nodeType = 'array';
            } elseif (is_object($node)) {
                $nodeType = 'object';
            } else {
                $nodeType = 'leaf';
            }

            // Parent node check
            if (null === $parentType) {
                // TODO: make this use helper function
                switch (true) {
                    case $nodeType === 'array':
                        $append .= "Root array(" . count($node) . ")\n";
                        break;
                    case $nodeType === 'object':
                        $append .= 'Root object ' . get_class($node) . "\n";
                        break;
                    case is_string($node):
                        $append .= "Root ($nodeType) '$node'";
                        break;
                    default:
                        $append .= "Root ($nodeType) $node";
                }
                ++$depth;
            }

            // children node check
            if ($nodeType === 'array') {
                foreach ($node as $k => $child) {

                    $append .= str_repeat($indent_string, $depth);

                    [$childNodeType, $child, $branch] = namespace\___dump_walk_helper_parse_child($child);

                    if (is_int($k)) {
                        $aKey = "[$k]";
                    } else {
                        $aKey = "['$k']";
                    }

                    if ($branch) {
                        $append .= "$aKey => $childNodeType\n" . namespace\___dump_walk_recurser($child, $indent_string, $depth + 1, $nodeType, $objects_shown);
                    } elseif (is_string($child)) {
                        $append .= "$aKey => $childNodeType '$child'\n";
                    } elseif (is_bool($child)) {
                        $append .= "$aKey => $childNodeType " . ($child ? "true" : "false") . "\n";
                    } elseif (is_null($child)) {
                        $append .= "$aKey => $childNodeType " . 'NULL' . "\n";
                    } elseif (is_object($child)) {
                        // non-branching object, don't try and cast to string
                        $append .= "$aKey => $childNodeType\n";
                    } else {
                        $append .= "$aKey => $childNodeType $child\n";
                    }
                }
            } elseif ($nodeType === 'object') {
                $reflect = new ReflectionObject($node);
                $props = $reflect->getProperties();

                if (!in_array(spl_object_hash($node), $objects_shown, true)) {
                    $objects_shown[] = spl_object_hash($node);
                } else {
                    $append .= str_repeat($indent_string, $depth);
                    return $append . "(...)\n";
                }

                foreach ($props as $prop) {
                    // Mark this member as private, public, or protected
                    $is_static = $prop->isStatic();
                    $visibility = ($prop->isPrivate() ? 'priv' : '') . ($prop->isPublic() ? 'publ' : '') . ($prop->isProtected() ? 'prot' : '');
                    $prop->setAccessible(true);
                    $append .= str_repeat($indent_string, $depth);
                    $child = $prop->getValue($node);
                    [$childNodeType, $child, $branch] = namespace\___dump_walk_helper_parse_child($child);
                    $propAndVis = "-> <$visibility" . ($is_static ? ':stat' : '') . "> {$prop->getName()}";
                    if ($branch) {
                        $append .= $propAndVis . " = $childNodeType\n" . namespace\___dump_walk_recurser($prop->getValue($node), $indent_string, $depth + 1, $nodeType, $objects_shown);
                    } elseif (is_string($child)) {
                        $append .= $propAndVis . " = $childNodeType '$child'\n";
                    } elseif (is_bool($child)) {
                        $append .= $propAndVis . " = $childNodeType " . ($child ? "true" : "false") . "\n";
                    } elseif (is_null($child)) {
                        $append .= $propAndVis . " = $childNodeType " . 'NULL' . "\n";
                    } elseif (is_object($child)) {
                        // non-branching object, don't try and cast to string
                        $append .= $propAndVis . " = $childNodeType\n";
                    } else {
                        $append .= $propAndVis . " = $childNodeType $child\n";
                    }
                }
            }
            return $append;
        } catch (Exception $e) {
            return 'dump_walk() error: ' . $e->getMessage();
        }
    }
}
if (!function_exists('___dump_walk_helper_parse_child')) {
    /**
     * Figures out the 'type' shown for the child and if it should branch.
     *
     * @param $child
     *
     * @throws ReflectionException
     * @return array [$childNodeType, $child, $branch]
     */
    function ___dump_walk_helper_parse_child($child): array {
        $branch = false;
        if (is_array($child)) {
            $branch = true;
            $childNodeType = "array (" . count($child) . ")";
        } elseif (is_object($child)) {
            if ($child instanceof DateTime) {
                $child->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $childNodeType = 'object ' . get_class($child);
                $child = $child->format("Y-m-d g:i:s a e");
            } elseif ($child instanceof mysqli) {
                $childNodeType = 'object ' . get_class($child);
            } elseif ($child instanceof mysqli_result) {
                $childNodeType = 'object mysqli_result';
            } elseif ($child instanceof PDO) {
                $childNodeType = 'PDO (PHP Database Object handle)';
            } elseif ($child instanceof PDOStatement) {
                $childNodeType = 'PDOStatement (prepared statement)';
            } elseif ($child instanceof Closure) {
                $refFunc = new ReflectionFunction($child);
                $params = array_map(function (ReflectionParameter $param) {
                    $out = '$' . $param->getName();
                    if ($param->isPassedByReference()) {
                        $out = "&$out";
                    }
                    $out = "{$param->getType()} $out";
                    if ($param->isOptional()) {
                        $out = "$out?";
                    }
                    if ($param->isDefaultValueAvailable()) {
                        $default = $param->getDefaultValue();
                        if (is_int($default)) {
                            $out = "$out = $default";
                        } elseif (is_string($default)) {
                            $out = "$out = \"$default\"";
                        } else {
                            $out = "$out = " . gettype($default);
                        }
                    }
                    return trim($out);
                }, $refFunc->getParameters());

                $childNodeType = "closure (" . implode(", ", $params) . ")";
            } else {
                $branch = true;
                $childNodeType = 'object ' . get_class($child);
            }
        } else {
            $childNodeType = '(' . gettype($child) . ')';
        }
        return [$childNodeType, $child, $branch];
    }
}
// end ___dump_walk_helper_parse_child
if (!function_exists('pre_dump')) {
    /**
     * @param $node
     * @return string
     */
    function pre_dump($node): string {

        return "<div style='background: white;border:3px grey solid;'><div></div><pre>" .
            htmlentities(dump_walk($node)) . "</pre></div>";
    }
}


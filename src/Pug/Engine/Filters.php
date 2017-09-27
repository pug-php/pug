<?php

namespace Pug\Engine;

use ArrayAccess;
use InvalidArgumentException;
use Phug\Renderer;

/**
 * Class Pug\Engine\Filters.
 */
abstract class Filters extends Renderer
{
    /**
     * Register / override new filter.
     *
     * @param string   $name
     * @param callable $filter
     *
     * @return $this
     */
    public function filter($name, $filter)
    {
        return $this->getCompiler()->setFilter($name, $filter);
    }
    /**
     * Check if a filter is registered.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasFilter($name)
    {
        return $this->getCompiler()->hasFilter($name);
    }
    /**
     * Get a registered filter by name.
     *
     * @param string $name
     *
     * @return callable
     */
    public function getFilter($name)
    {
        return $this->getCompiler()->getFilter($name);
    }
}

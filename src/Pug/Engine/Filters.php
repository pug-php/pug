<?php

namespace Pug\Engine;

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
    public function setFilter($name, $filter)
    {
        if (!(
            is_callable($filter) ||
            class_exists($filter) ||
            method_exists($filter, 'parse')
        )) {
            throw new InvalidArgumentException(
                'Invalid ' . $name . ' filter given: ' .
                'it must be a callable or a class name.'
            );
        }

        return $this->getCompiler()->setFilter($name, $filter);
    }

    /**
     * @alias setFilter
     */
    public function filter($name, $filter)
    {
        return $this->setFilter($name, $filter);
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
     * Get a registered / resolvable filter by name.
     *
     * @param string $name
     *
     * @return callable
     */
    public function getFilter($name)
    {
        return $this->getCompiler()->getFilter($name);
    }

    /**
     * Add filter.
     *
     * @param string   $name   filter name
     * @param callable $filter filter treatment
     */
    public function addKeyword($name, $filter)
    {
        if ($this->hasFilter($name)) {
            throw new InvalidArgumentException("The filter $name is already set.", 31);
        }

        $this->setFilter($name, $filter);
    }

    /**
     * Replace filter.
     *
     * @param string   $name   filter name
     * @param callable $filter filter treatment
     */
    public function replaceKeyword($name, $filter)
    {
        if (!$this->hasFilter($name)) {
            throw new InvalidArgumentException("The filter $name is not set.", 32);
        }

        $this->setFilter($name, $filter);
    }

    /**
     * Remove filter.
     *
     * @param string $name the filter to be removed.
     */
    public function removeFilter($name)
    {
        if ($this->hasFilter($name)) {
            $this->getCompiler()->unsetFilter($name);
        }
    }
}

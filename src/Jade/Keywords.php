<?php

namespace Jade;

/**
 * Class Jade\Keywords.
 */
abstract class Keywords
{
    /**
     * Set custom keyword.
     *
     * @param string   $keyword the keyword to be found.
     * @param callable $action  action to be executed when the keyword is found.
     */
    public function setKeyword($keyword, $action)
    {
        if (!isset($this->options['customKeywords'])) {
            $this->options['customKeywords'] = array();
        }

        if (!is_callable($action)) {
            throw new \InvalidArgumentException("Please add a callable action for your keyword $keyword", 30);
        }

        $this->options['customKeywords'][$keyword] = $action;
    }

    /**
     * Add custom keyword.
     *
     * @param string   $keyword the keyword to be found.
     * @param callable $action  action to be executed when the keyword is found.
     */
    public function addKeyword($keyword, $action)
    {
        if (isset($this->options['customKeywords'], $this->options['customKeywords'][$keyword])) {
            throw new \InvalidArgumentException("The keyword $keyword is already set.", 31);
        }

        $this->setKeyword($keyword, $action);
    }

    /**
     * Replace custom keyword.
     *
     * @param string   $keyword the keyword to be found.
     * @param callable $action  action to be executed when the keyword is found.
     */
    public function replaceKeyword($keyword, $action)
    {
        if (!isset($this->options['customKeywords'], $this->options['customKeywords'][$keyword])) {
            throw new \InvalidArgumentException("The keyword $keyword is not set.", 32);
        }

        $this->setKeyword($keyword, $action);
    }

    /**
     * Remove custom keyword.
     *
     * @param string $keyword the keyword to be removed.
     */
    public function removeKeyword($keyword)
    {
        if (isset($this->options['customKeywords'], $this->options['customKeywords'][$keyword])) {
            unset($this->options['customKeywords'][$keyword]);
        }
    }
}

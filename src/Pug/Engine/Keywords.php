<?php

namespace Pug\Engine;

use ArrayAccess;
use InvalidArgumentException;
use Phug\Renderer;

/**
 * Class Pug\Engine\Keywords.
 */
abstract class Keywords extends Renderer
{
    protected function hasKeyword($keyword)
    {
        return $this->hasValidCustomKeywordsOption() && $this->getOption(['custom_keywords', $keyword]);
    }

    protected function hasValidCustomKeywordsOption()
    {
        return is_array($this->getOption('custom_keywords')) ||
            $this->getOption('custom_keywords') instanceof ArrayAccess;
    }

    /**
     * Set custom keyword.
     *
     * @param string   $keyword the keyword to be found.
     * @param callable $action  action to be executed when the keyword is found.
     */
    public function setKeyword($keyword, $action)
    {
        if (!is_callable($action)) {
            throw new InvalidArgumentException("Please add a callable action for your keyword $keyword", 30);
        }

        if (!$this->hasValidCustomKeywordsOption()) {
            $this->setOption('custom_keywords', []);
        }

        $this->setOption(['custom_keywords', $keyword], $action);
    }

    /**
     * Add custom keyword.
     *
     * @param string   $keyword the keyword to be found.
     * @param callable $action  action to be executed when the keyword is found.
     */
    public function addKeyword($keyword, $action)
    {
        if ($this->hasKeyword($keyword)) {
            throw new InvalidArgumentException("The keyword $keyword is already set.", 31);
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
        if (!$this->hasKeyword($keyword)) {
            throw new InvalidArgumentException("The keyword $keyword is not set.", 32);
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
        if ($this->hasKeyword($keyword)) {
            $this->unsetOption(['custom_keywords', $keyword]);
        }
    }
}

<?php

namespace Pug\Filter;

use Pug\Compiler;
use Pug\Nodes\Filter;

/**
 * Class Pug\Filter\AbstractFilter.
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * @obsolete
     */
    protected function getNodeString()
    {
        throw new \RuntimeException('->getNodeString is no longer supported since you get now contents as a string.');
    }

    public function wrapInTag($code)
    {
        if (isset($this->tag)) {
            $code = '<' . $this->tag . (isset($this->textType) ? ' type="text/' . $this->textType . '"' : '') . '>' .
                $code .
                '</' . $this->tag . '>';
        }

        return $code;
    }

    public function __invoke(Filter $node, Compiler $compiler)
    {
        throw new \RuntimeException('Pug\Filter\FilterInterface is no longer supported. Now use Pug\FilterInterface instead.');
    }

    public function __pugInvoke($code, array $options = null)
    {
        if (method_exists($this, 'parse')) {
            $code = $this->parse($code, $options);
        }

        return $this->wrapInTag($code);
    }
}

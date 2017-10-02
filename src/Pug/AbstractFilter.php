<?php

namespace Pug;

/**
 * Class Pug\AbstractFilter.
 */
abstract class AbstractFilter implements FilterInterface
{
    public function wrapInTag($code)
    {
        if (isset($this->tag)) {
            $code = '<' . $this->tag . (isset($this->textType) ? ' type="text/' . $this->textType . '"' : '') . '>' .
                $code .
                '</' . $this->tag . '>';
        }

        return $code;
    }

    public function __invoke($code, array $options = null)
    {
        return $this->__pugInvoke($code, $options);
    }

    public function __pugInvoke($code, array $options = null)
    {
        if (method_exists($this, 'parse')) {
            $code = $this->parse($code, $options);
        }

        return $this->wrapInTag($code);
    }
}

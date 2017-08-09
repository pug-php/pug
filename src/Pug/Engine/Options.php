<?php

namespace Pug\Engine;

/**
 * Class Pug\Engine\Options.
 */
class Options extends Keywords
{
    /**
     * @var array
     */
    protected $options = array(
        'allowMixedIndent'   => true,
        'allowMixinOverride' => true,
        'basedir'            => null,
        'cache'              => null,
        'classAttribute'     => null,
        'customKeywords'     => array(),
        'expressionLanguage' => 'auto',
        'extension'          => array('.pug', '.jade'),
        'filterAutoLoad'     => true,
        'indentChar'         => ' ',
        'indentSize'         => 2,
        'jsLanguage'         => array(),
        'keepBaseName'       => false,
        'keepNullAttributes' => false,
        'nodePath'           => null,
        'phpSingleLine'      => false,
        'php5compatibility'  => false,
        'postRender'         => null,
        'preRender'          => null,
        'prettyprint'        => false,
        'pugjs'              => false,
        'restrictedScope'    => false,
        'singleQuote'        => false,
        'stream'             => null,
        'upToDateCheck'      => true,
    );

    /**
     * @param array  $arrays
     * @param string $functionName
     *
     * @return $this
     */
    private function setOptionArrays(array $arrays, $functionName)
    {
        var_dump($arrays);
        exit;
        array_unshift($arrays, $this->options);
        $this->options = call_user_func_array($functionName, array_filter($arrays, 'is_array'));

        return $this;
    }

    /**
     * @param array|string $keys
     * @param callable     $callback
     *
     * @return &$options
     */
    private function withOptionsReference(&$keys, $callback)
    {
        var_dump($keys);
        exit;
        $options = &$this->options;
        if (is_array($keys)) {
            foreach (array_slice($keys, 0, -1) as $key) {
                if (!array_key_exists($key, $options)) {
                    $options[$key] = [];
                }
                $options = &$options[$key];
            }
            $keys = end($keys);
        }

        return $callback($options, $keys);
    }
}

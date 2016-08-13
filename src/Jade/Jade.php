<?php

namespace Jade;

use Jade\Engine\Keywords;

/**
 * Class Jade\Jade.
 */
class Jade extends Keywords
{
    /**
     * @var string
     */
    protected $streamName = 'jade';

    /**
     * @var array
     */
    protected $options = array(
        'allowMixedIndent'   => true,
        'allowMixinOverride' => true,
        'cache'              => null,
        'classAttribute'     => null,
        'customKeywords'     => array(),
        'extension'          => array('.pug', '.jade'),
        'filterAutoLoad'     => true,
        'indentChar'         => ' ',
        'indentSize'         => 2,
        'keepBaseName'       => false,
        'keepNullAttributes' => false,
        'phpSingleLine'      => false,
        'postRender'         => null,
        'preRender'          => null,
        'prettyprint'        => false,
        'restrictedScope'    => false,
        'singleQuote'        => false,
        'stream'             => null,
        'upToDateCheck'      => true,
    );

    /**
     * Built-in filters.
     *
     * @var array
     */
    protected $filters = array(
        'php'        => 'Jade\Filter\Php',
        'css'        => 'Jade\Filter\Css',
        'cdata'      => 'Jade\Filter\Cdata',
        'escaped'    => 'Jade\Filter\Escaped',
        'javascript' => 'Jade\Filter\Javascript',
    );

    /**
     * @var array
     */
    protected $sharedVariables = array();

    /**
     * Merge local options with constructor $options.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        if (is_null($this->options['stream'])) {
            $this->options['stream'] = $this->streamName . '.stream';
        }
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Returns true if suhosin extension is loaded and the stream name
     * is missing in the executor include whitelist.
     * Returns false in any other case.
     *
     * @return bool
     */
    protected function whiteListNeeded($extension)
    {
        return extension_loaded($extension) &&
            false === strpos(
                ini_get($extension . '.executor.include.whitelist'),
                $this->options['stream']
            );
    }

    /**
     * Returns list of requirements in an array identified by keys.
     * For each of them, the value can be true if the requirement is
     * fullfilled, false else.
     *
     * If a requirement name is specified, returns only the matching
     * boolean value for this requirement.
     *
     * @param string name
     *
     * @throws \InvalidArgumentException
     *
     * @return array|bool
     */
    public function requirements($name = null)
    {
        $requirements = array(
            'streamWhiteListed' => !$this->whiteListNeeded('suhosin'),
            'cacheFolderExists' => $this->options['cache'] && is_dir($this->options['cache']),
            'cacheFolderIsWritable' => $this->options['cache'] && is_writable($this->options['cache']),
        );

        if ($name) {
            if (!isset($requirements[$name])) {
                throw new \InvalidArgumentException($name . ' is not in the requirements list (' . implode(', ', array_keys($requirements)) . ')', 19);
            }

            return $requirements[$name];
        }

        return $requirements;
    }

    /**
     * Get standard or custom option, return the previously setted value or the default value else.
     *
     * Throw a invalid argument exception if the option does not exists.
     *
     * @param string name
     *
     * @throws \InvalidArgumentException
     */
    public function getOption($name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException("$name is not a valid option name.", 2);
        }

        return $this->options[$name];
    }

    /**
     * Set one standard option (listed in $this->options).
     *
     * @param string name
     * @param mixed option value
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setOption($name, $value)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException("$name is not a valid option name.", 3);
        }

        $this->options[$name] = $value;

        return $this;
    }

    /**
     * Set multiple standard options.
     *
     * @param array option list
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setOptions($options)
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }

        return $this;
    }

    /**
     * Set one custom option.
     *
     * @param string name
     * @param mixed option value
     *
     * @return $this
     */
    public function setCustomOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * Set multiple custom options.
     *
     * @param array options list
     *
     * @return $this
     */
    public function setCustomOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Compile PHP code from a Pug input or a Pug file.
     *
     * @param string input
     *
     * @throws \Exception
     *
     * @return string
     */
    public function compile($input)
    {
        $parser = new Parser($input, null, $this->options);
        $compiler = new Compiler($this->options, $this->filters, $parser->getFilename());
        $php = $compiler->compile($parser->parse());
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            $php = preg_replace_callback('/(' . preg_quote('\\Jade\\Compiler::getPropertyFromAnything', '/') . '\\(((?>[^()]+)|(?-2))*\\))[ \t]*(\\(((?>[^()]+)|(?-2))*\\))/', function ($match) {
                return 'call_user_func(' . $match[1] . ', ' . $match[4] . ')';
            }, $php);
        }
        $postRender = $this->getOption('postRender');
        if (is_callable($postRender)) {
            $php = call_user_func($postRender, $php);
        }

        return $php;
    }

    /**
     * Compile HTML code from a Pug input or a Pug file.
     *
     * @param sring Pug input or file
     * @param array vars to pass to the view
     *
     * @throws \Exception
     *
     * @return string
     */
    public function render($input, array $vars = array())
    {
        $file = $this->options['cache']
            ? $this->cache($input)
            : $this->stream($this->compile($input));

        extract(array_merge($this->sharedVariables, $vars));
        ob_start();
        try {
            include $file;
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Create a stream wrapper to allow
     * the possibility to add $scope variables.
     *
     * @param string input
     *
     * @throws \ErrorException
     *
     * @return string
     */
    public function stream($input)
    {
        if ($this->whiteListNeeded('suhosin')) {
            throw new \ErrorException('To run Pug.php on the fly, add "' . $this->options['stream'] . '" to the "suhosin.executor.include.whitelist" settings in your php.ini file.', 4);
        }

        if (!in_array($this->options['stream'], stream_get_wrappers())) {
            stream_wrapper_register($this->options['stream'], 'Jade\Stream\Template');
        }

        return $this->options['stream'] . '://data;' . $input;
    }

    /**
     * Add a variable or an array of variables to be shared with all templates that will be rendered
     * by the instance of Pug.
     *
     * @param array|string $variables|$key an associatives array of variable names and values, or a
     *                                     variable name if you wish to sahre only one
     * @param mixed        $value          if you pass an array as first argument, the second
     *                                     argument will be ignored, else it will used as the
     *                                     variable value for the variable name you passed as first
     *                                     argument
     */
    public function share($variables, $value = null)
    {
        if (!is_array($variables)) {
            $variables = array(strval($variables) => $value);
        }
        $this->sharedVariables = array_merge($this->sharedVariables, $variables);
    }

    /**
     * Remove all previously set shared variables.
     */
    public function resetSharedVariables()
    {
        $this->sharedVariables = array();
    }
}

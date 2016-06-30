<?php

namespace Jade;

use Jade\Compiler\FilterHelper;

/**
 * Class Jade\Jade.
 */
class Jade
{
    /**
     * @var string
     */
    protected $streamName = 'jade';

    /**
     * @var array
     */
    protected $options = array(
        'cache'              => null,
        'stream'             => null,
        'extension'          => array('.pug', '.jade'),
        'prettyprint'        => false,
        'phpSingleLine'      => false,
        'keepBaseName'       => false,
        'allowMixinOverride' => true,
        'allowMixedIndent'   => true,
        'keepNullAttributes' => false,
        'restrictedScope'    => false,
        'singleQuote'        => true,
        'filterAutoLoad'     => true,
        'indentSize'         => 2,
        'indentChar'         => ' ',
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
     * Indicate if we registered the stream wrapper,
     * in order to not ask the stream registry each time
     * We need to render a template.
     *
     * @var bool
     */
    protected static $wrappersRegistered = array();

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
     * Get main template file extension.
     *
     * @return string
     */
    public function getExtension()
    {
        $extension = $this->getOption('extension');

        return is_string($extension)
            ? $extension
            : (isset($extension[0])
                ? $extension[0]
                : ''
            );
    }

    /**
     * Get list of supported extensions.
     *
     * @return array
     */
    public function getExtensions()
    {
        $extension = $this->getOption('extension');

        return is_string($extension)
            ? array($extension)
            : array_unique($extension);
    }

    /**
     * Register / override new filter.
     *
     * @param string name
     * @param callable filter
     *
     * @return $this
     */
    public function filter($name, $filter)
    {
        $this->filters[$name] = $filter;

        return $this;
    }

    /**
     * Check if a filter is registered.
     *
     * @param string name
     *
     * @return bool
     */
    public function hasFilter($name)
    {
        $helper = new FilterHelper($this->filters, $this->options['filterAutoLoad']);

        return $helper->hasFilter($name);
    }

    /**
     * Get a registered filter by name.
     *
     * @param string name
     *
     * @return callable
     */
    public function getFilter($name)
    {
        $helper = new FilterHelper($this->filters, $this->options['filterAutoLoad']);

        return $helper->getFilter($name);
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
        $compiler = new Compiler($this->options, $this->filters);

        return $compiler->compile($parser->parse());
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

        extract($vars);
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

        if (!in_array($this->options['stream'], static::$wrappersRegistered)) {
            static::$wrappersRegistered[] = $this->options['stream'];
            stream_wrapper_register($this->options['stream'], 'Jade\Stream\Template');
        }

        return $this->options['stream'] . '://data;' . $input;
    }

    /**
     * Return a file path in the cache for a given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getCachePath($name)
    {
        return str_replace('//', '/', $this->options['cache'] . '/' . $name) . '.php';
    }

    /**
     * Return true if the file or content is up to date in the cache folder,
     * false else.
     *
     * @param string  $input file or pug code
     * @param &string $path  to be filled
     *
     * @return bool
     */
    protected function isCacheUpToDate($input, &$path)
    {
        if (is_file($input)) {
            $path = $this->getCachePath(($this->options['keepBaseName'] ? basename($input) : '') . md5($input));

            // Do not re-parse file if original is older
            return file_exists($path) && filemtime($input) < filemtime($path);
        }

        // Get the stronger hashing algorithm available to minimize collision risks
        $algos = hash_algos();
        $algo = $algos[0];
        $number = 0;
        foreach ($algos as $hashAlgorithm) {
            if (strpos($hashAlgorithm, 'md') === 0) {
                $hashNumber = substr($hashAlgorithm, 2);
                if ($hashNumber > $number) {
                    $number = $hashNumber;
                    $algo = $hashAlgorithm;
                }
                continue;
            }
            if (strpos($hashAlgorithm, 'sha') === 0) {
                $hashNumber = substr($hashAlgorithm, 3);
                if ($hashNumber > $number) {
                    $number = $hashNumber;
                    $algo = $hashAlgorithm;
                }
                continue;
            }
        }
        $path = $this->getCachePath(rtrim(strtr(base64_encode(hash($algo, $input, true)), '+/', '-_'), '='));

        // Do not re-parse file if the same hash exists
        return file_exists($path);
    }

    /**
     * Get cached input/file a matching cache file exists.
     * Else, render the input, cache it in a file and return it.
     *
     * @param string input
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     *
     * @return string
     */
    public function cache($input)
    {
        $cacheFolder = $this->options['cache'];

        if (!is_dir($cacheFolder)) {
            throw new \ErrorException($cacheFolder . ': Cache directory seem\'s to not exists', 5);
        }

        if ($this->isCacheUpToDate($input, $path)) {
            return $path;
        }

        if (!is_writable($cacheFolder)) {
            throw new \ErrorException(sprintf('Cache directory must be writable. "%s" is not.', $cacheFolder), 6);
        }

        $rendered = $this->compile($input);
        file_put_contents($path, $rendered);

        return $this->stream($rendered);
    }
}

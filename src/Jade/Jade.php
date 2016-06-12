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

    public function getOption($name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException("$name is not a valid option name.", 1);
        }

        return $this->options[$name];
    }

    public function setOption($name, $value)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException("$name is not a valid option name.", 1);
        }

        $this->options[$name] = $value;
    }

    public function setOptions($options)
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    public function setCustomOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    public function setCustomOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
    }

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
     * register / override new filter.
     *
     * @param $name
     * @param $filter
     *
     * @return $this
     */
    public function filter($name, $filter)
    {
        $this->filters[$name] = $filter;

        return $this;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasFilter($name)
    {
        $helper = new FilterHelper($this->filters, $this->options['filterAutoLoad']);

        return $helper->hasFilter($name);
    }

    /**
     * @param $name
     *
     * @return callable
     */
    public function getFilter($name)
    {
        $helper = new FilterHelper($this->filters, $this->options['filterAutoLoad']);

        return $helper->getFilter($name);
    }

    /**
     * @param $input
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
     * @param $input
     * @param array $vars
     *
     * @return mixed|string
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
     * @param $input
     *
     * @return string
     */
    public function stream($input)
    {
        if (extension_loaded('suhosin') && false === strpos(ini_get('suhosin.executor.include.whitelist'), $this->options['stream'])) {
            throw new \ErrorException('To run Pug.php on the fly, add "' . $this->options['stream'] . '" to the "suhosin.executor.include.whitelist" settings in your php.ini file.');
        }

        if (!in_array($this->options['stream'], static::$wrappersRegistered)) {
            static::$wrappersRegistered[] = $this->options['stream'];
            stream_wrapper_register($this->options['stream'], 'Jade\Stream\Template');
        }

        return $this->options['stream'] . '://data;' . $input;
    }

    /**
     * @param $input
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     *
     * @return mixed|string
     */
    public function cache($input)
    {
        $cacheFolder = $this->options['cache'];

        if (!is_dir($cacheFolder)) {
            throw new \ErrorException($cacheFolder . ': Cache directory seem\'s to not exists');
        }

        if (is_file($input)) {
            $path = str_replace('//', '/', $cacheFolder . '/' . ($this->options['keepBaseName'] ? basename($input) : '') . md5($input) . '.php');

            // Do not re-parse file if original is older
            if (file_exists($path) && filemtime($input) < filemtime($path)) {
                return $path;
            }
        } else {
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
            $path = str_replace('//', '/', $cacheFolder . '/' . rtrim(strtr(base64_encode(hash($algo, $input, true)), '+/', '-_'), '='));

            // Do not re-parse file if the same hash exists
            if (file_exists($path)) {
                return $path;
            }
        }

        if (!is_writable($cacheFolder)) {
            throw new \ErrorException(sprintf('Cache directory must be writable. "%s" is not.', $cacheFolder));
        }

        $rendered = $this->compile($input);
        file_put_contents($path, $rendered);

        return $this->stream($rendered);
    }
}

<?php

namespace Pug;

use InvalidArgumentException;
use Phug\Renderer\Adapter\StreamAdapter;
use Pug\Engine\PugJsEngine;

/**
 * Class Pug\Pug.
 */
class Pug extends PugJsEngine
{
    /**
     * expressionLanguage option values.
     */
    const EXP_AUTO = 0;
    const EXP_JS = 1;
    const EXP_PHP = 2;

    /**
     * Built-in filters.
     *
     * @var array
     */
    protected $filters = [
        'php'        => 'Pug\Filter\Php',
        'css'        => 'Pug\Filter\Css',
        'cdata'      => 'Pug\Filter\Cdata',
        'escaped'    => 'Pug\Filter\Escaped',
        'javascript' => 'Pug\Filter\Javascript',
    ];

    public function __construct($options = null)
    {
        $normalize = function ($name) {
            return str_replace('_', '', strtolower($name));
        };
        $this->addOptionNameHandlers(function ($name) use ($normalize) {
            return is_array($name) ? array_map($normalize, $name) : $normalize($name);
        });

        parent::__construct($options);
    }

    /**
     * Returns true if suhosin extension is loaded and the stream name
     * is missing in the executor include whitelist.
     * Returns false in any other case.
     *
     * @param string $extension PHP extension name
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
     * fulfilled, false else.
     *
     * If a requirement name is specified, returns only the matching
     * boolean value for this requirement.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return array|bool
     */
    public function requirements($name = null)
    {
        $requirements = [
            'streamWhiteListed'     => !$this->whiteListNeeded('suhosin'),
            'cacheFolderExists'     => !$this->options['cache'] || is_dir($this->options['cache']),
            'cacheFolderIsWritable' => !$this->options['cache'] || is_writable($this->options['cache']),
        ];

        if ($name) {
            if (!isset($requirements[$name])) {
                throw new InvalidArgumentException($name . ' is not in the requirements list (' . implode(', ', array_keys($requirements)) . ')', 19);
            }

            return $requirements[$name];
        }

        return $requirements;
    }

    /**
     * Render using the PHP engine.
     *
     * @param string $input    pug input or file
     * @param array  $vars     to pass to the view
     * @param string $filename optional file path
     *
     * @throws \Exception
     *
     * @return string
     */
    public function renderWithPhp($input, array $vars, $filename = null)
    {
        return parent::render($input, $vars, $filename);
    }

    /**
     * Render using the PHP engine.
     *
     * @param string $path pug input or file
     * @param array  $vars to pass to the view
     *
     * @throws \Exception
     *
     * @return string
     */
    public function renderFileWithPhp($path, array $vars)
    {
        return parent::renderFile($path, $vars);
    }

    /**
     * Compile HTML code from a Pug input or a Pug file.
     *
     * @param string $input    pug input or file
     * @param array  $vars     to pass to the view
     * @param string $filename optional file path
     *
     * @throws \Exception
     *
     * @return string
     */
    public function render($input, array $vars = [], $filename = null)
    {
        $fallback = function () use ($input, $vars, $filename) {
            return $this->renderWithPhp($input, $vars, $filename);
        };

        if ($this->options['pugjs']) {
            return $this->renderWithJs($input, null, $vars, $fallback);
        }

        return call_user_func($fallback);
    }

    /**
     * Compile HTML code from a Pug input or a Pug file.
     *
     * @param string $input pug file
     * @param array  $vars  to pass to the view
     *
     * @throws \Exception
     *
     * @return string
     */
    public function renderFile($path, array $vars = [])
    {
        $fallback = function () use ($path, $vars) {
            return $this->renderFileWithPhp($path, $vars);
        };

        if ($this->options['pugjs']) {
            return $this->renderFileWithJs($path, $vars, $fallback);
        }

        return call_user_func($fallback);
    }

    /**
     * Obsolete.
     *
     * @throws \ErrorException
     */
    public function stream()
    {
        throw new \ErrorException(
            '->stream() is no longer available, please use ' . StreamAdapter::class . ' instead',
            34
        );
    }
}

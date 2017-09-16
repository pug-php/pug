<?php

namespace Pug;

use InvalidArgumentException;
use JsPhpize\JsPhpizePhug;
use Phug\Compiler\Event\ElementEvent;
use Phug\Formatter\Element\AssignmentElement;
use Phug\Formatter\Element\MarkupElement;
use Phug\Formatter\Event\NewFormatEvent;
use Phug\Renderer\Adapter\StreamAdapter;
use Pug\Engine\PugJsEngine;
use SplObjectStorage;

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

    /**
     * Built-in filters.
     *
     * @var array
     */
    protected $optionsAliases = [
        'prettyprint'      => 'pretty',
        'allowMixedIndent' => 'allow_mixed_indent',
    ];

    public function __construct($options = null)
    {
        $normalize = function ($name) {
            return str_replace('_', '', strtolower($name));
        };
        $this->addOptionNameHandlers(function ($name) use ($normalize) {
            if (is_string($name) && isset($this->optionsAliases[$name])) {
                $name = $this->optionsAliases[$name];
            } else if (is_array($name) && isset($this->optionsAliases[$name[0]])) {
                $name[0] = $this->optionsAliases[$name[0]];
            }

            return is_array($name) ? array_map($normalize, $name) : $normalize($name);
        });
        foreach ($this->optionsAliases as $from => $to) {
            if (isset($options[$from]) && !isset($options[$to])) {
                $options[$to] = $options[$from];
            }
        }
        if (isset($options['classAttribute'])) {
            $classAttribute = $options['classAttribute'];
            $options['on_element'] = function (ElementEvent $event) {
                $element = $event->getElement();
                if ($element instanceof MarkupElement) {
                    $element->getAssignments()->attach(new AssignmentElement('attributes', new SplObjectStorage(), $element));
                }
            };
            $options['on_new_format'] = function (NewFormatEvent $event) use (&$copyFormatter, $classAttribute) {
                $copyFormatter = $event->getFormatter();
                $newFormat = clone $event->getFormat();
                $newFormat
                    ->registerHelper('class_attribute_name', $classAttribute)
                    ->provideHelper('attributes_assignment', [
                        'merge_attributes',
                        'class_attribute_name',
                        'pattern',
                        'pattern.attribute_pattern',
                        'pattern.boolean_attribute_pattern',
                        function ($mergeAttributes, $classAttribute, $pattern, $attributePattern, $booleanPattern) {
                            return function () use ($mergeAttributes, $classAttribute, $pattern, $attributePattern, $booleanPattern) {
                                $attributes = call_user_func_array($mergeAttributes, func_get_args());
                                $code = '';
                                foreach ($attributes as $name => $value) {
                                    if ($value) {
                                        if ($name === 'class') {
                                            $name = $classAttribute;
                                        }
                                        $code .= $value === true
                                            ? $pattern($booleanPattern, $name, $name)
                                            : $pattern($attributePattern, $name, $value);
                                    }
                                }

                                return $code;
                            };
                        },
                    ]);
                $event->setFormat($newFormat);
            };
        }

        parent::__construct($options);

        // TODO: find a better way to apply snake_case options to the compiler
        $compiler = $this->getCompiler();
        if (!$compiler->hasOption('memory_limit')) {
            $compiler->setOption('memory_limit', $this->getOption('memory_limit'));
        }
        if (!$compiler->hasOption('execution_max_time')) {
            $compiler->setOption('execution_max_time', $this->getOption('memory_limit'));
        }
        if (!$this->hasOption('expressionLanguage') ||
            strtolower($this->getOption('expressionLanguage')) !== 'php'
        ) {
            $compiler = $this->getCompiler();
            $compiler->addModule(new JsPhpizePhug($compiler));
        }
    }

    public function filter()
    {
        throw new \Exception('todo');
    }

    public function hasFilter()
    {
        throw new \Exception('todo');
    }

    public function getFilter()
    {
        throw new \Exception('todo');
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

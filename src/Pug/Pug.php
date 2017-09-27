<?php

namespace Pug;

use InvalidArgumentException;
use JsPhpize\JsPhpizePhug;
use Phug\Compiler\Event\OutputEvent;
use Phug\Formatter\Format\HtmlFormat;
use Phug\Lexer\Event\LexEvent;
use Phug\Renderer\Adapter\FileAdapter;
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
    protected $filters;

    /**
     * Built-in filters.
     *
     * @var array
     */
    protected $optionsAliases = [
        'cache'              => 'cachedir',
        'prettyprint'        => 'pretty',
        'expressionLanguage' => 'expressionlanguage',
        'allowMixedIndent'   => 'allow_mixed_indent',
        'keepBaseName'       => 'keep_base_name',
        'notFound'           => 'not_found_template',
    ];

    public function __construct($options = null)
    {
        if (!isset($options['filterAutoLoad']) || $options['filterAutoLoad']) {
            if (!isset($options['filter_resolvers'])) {
                $options['filter_resolvers'] = [];
            }

            $options['filter_resolvers'][] = function ($name) {
                if (isset($this->filters[$name])) {
                    return $this->filters[$name];
                }

                foreach (['Pug', 'Jade'] as $namespace) {
                    $filter = $namespace . '\\Filter\\' . implode('', array_map('ucfirst', explode('-', $name)));

                    if (class_exists($filter)) {
                        $this->filters[$name] = method_exists($filter, '__pug3Invoke')
                            ? [new $filter(), '__pug3Invoke']
                            : (method_exists($filter, 'parse')
                                ? [new $filter(), 'parse']
                                : $filter
                            );

                        return $this->filters[$name];
                    }

                    return null;
                }
            };
        }
        $normalize = function ($name) {
            return isset($this->optionsAliases[$name])
                ? $this->optionsAliases[$name]
                : str_replace('_', '', strtolower($name));
        };
        $this->addOptionNameHandlers(function ($name) use ($normalize) {
            if (is_string($name) && isset($this->optionsAliases[$name])) {
                $name = $this->optionsAliases[$name];
            } elseif (is_array($name) && isset($this->optionsAliases[$name[0]])) {
                $name[0] = $this->optionsAliases[$name[0]];
            }

            return is_array($name) ? array_map($normalize, $name) : $normalize($name);
        });
        foreach ($this->optionsAliases as $from => $to) {
            if (isset($options[$from]) && !isset($options[$to])) {
                $options[$to] = $options[$from];
            }
        }
        if (!isset($options['formats'])) {
            $options['formats'] = [];
        }
        if (!isset($options['formats']['default'])) {
            $options['formats']['default'] = HtmlFormat::class;
        }
        if (!isset($options['formats']['5'])) {
            $options['formats']['5'] = HtmlFormat::class;
        }
        if (isset($options['cachedir']) && $options['cachedir']) {
            $options['adapterclassname'] = FileAdapter::class;
        }
        if (isset($options['allowMixinOverride'])) {
            $options['mixin_merge_mode'] = $options['allowMixinOverride']
                ? 'replace'
                : 'ignore';
        }
        if (isset($options['preRender'])) {
            $preRender = $options['preRender'];
            $onLex = isset($options['on_lex']) ? $options['on_lex'] : null;
            $options['on_lex'] = function (LexEvent $event) use ($onLex, $preRender) {
                if ($onLex) {
                    call_user_func($onLex, $event);
                }
                $event->setInput(call_user_func($preRender, $event->getInput()));
            };
        }
        $postRender = isset($options['postRender']) ? $options['postRender'] : null;
        $onOutput = isset($options['on_output']) ? $options['on_output'] : null;
        $options['on_output'] = function (OutputEvent $event) use ($onOutput, $postRender) {
            if ($onOutput) {
                call_user_func($onOutput, $event);
            }
            $output = $event->getOutput();
            if (stripos($output, 'namespace') !== false) {
                $namespace = null;
                $tokens = array_slice(token_get_all('?>' . $output), 1);
                $afterNamespace = false;
                $start = 0;
                $end = 0;
                foreach ($tokens as $token) {
                    if (is_string($token)) {
                        $length = mb_strlen($token);
                        if (!$afterNamespace) {
                            $start += $length;
                        }
                        $end += $length;

                        continue;
                    }
                    if ($token[0] === T_NAMESPACE) {
                        $afterNamespace = true;
                    }
                    $length = mb_strlen($token[1]);
                    if (!$afterNamespace) {
                        $start += $length;
                    }
                    $end += $length;
                    if ($afterNamespace && $token[0] === T_STRING) {
                        $namespace = $token[1];
                        break;
                    }
                }
                if ($namespace) {
                    $output = "<?php\n\nnamespace $namespace;\n\n?>" .
                        mb_substr($output, 0, $start) .
                        ltrim(mb_substr($output, $end), ' ;');
                }
            }
            if ($postRender) {
                $output = call_user_func($postRender, $output);
            }
            $event->setOutput($output);
        };
        if (isset($options['jsLanguage'])) {
            if (!isset($options['module_options'])) {
                $options['module_options'] = [];
            }
            $options['module_options']['jsphpize'] = $options['jsLanguage'];
        }
        if (isset($options['classAttribute'])) {
            if (!isset($options['attributes_mapping'])) {
                $options['attributes_mapping'] = [];
            }
            $options['attributes_mapping']['class'] = $options['classAttribute'];
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
        if (strtolower($this->getDefaultOption('expressionLanguage')) !== 'php') {
            $compiler = $this->getCompiler();
            $compiler->addModule(new JsPhpizePhug($compiler));
        }
    }

    public function setCustomOption($name, $value)
    {
        return $this->$this->setOption($name, $value);
    }

    public function setCustomOptions($options)
    {
        return $this->$this->setOptions($options);
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
            'cacheFolderExists'     => !$this->getDefaultOption('cache_dir') || is_dir($this->getOption('cache_dir')),
            'cacheFolderIsWritable' => !$this->getDefaultOption('cache_dir') || is_writable($this->getOption('cache_dir')),
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

        if ($this->getDefaultOption('pugjs')) {
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

        if ($this->getDefaultOption('pugjs')) {
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

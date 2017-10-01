<?php

namespace Pug\Engine;

use JsPhpize\JsPhpizePhug;
use Phug\Compiler\Event\OutputEvent;
use Phug\Formatter\Format\HtmlFormat;
use Phug\Lexer\Event\LexEvent;
use Phug\Renderer\Adapter\FileAdapter;
use Pug\Format\XmlHhvmFormat;

/**
 * Class Pug\Engine\Keywords.
 */
abstract class Options extends PugJsEngine
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
        'customKeywords'     => 'keywords',
    ];

    protected function setUpFilterAutoload(&$options)
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
                        $this->filters[$name] = method_exists($filter, '__pugInvoke')
                            ? [new $filter(), '__pugInvoke']
                            : (method_exists($filter, 'parse')
                                ? [new $filter(), 'parse']
                                : $filter
                            );

                        return $this->filters[$name];
                    }

                    return;
                }
            };
        }
    }

    protected function setUpOptionsAliases(&$options)
    {
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
    }

    protected function setUpFormats(&$options)
    {
        if (!isset($options['formats'])) {
            $options['formats'] = [];
        }
        if (!isset($options['formats']['default'])) {
            $options['formats']['default'] = HtmlFormat::class;
        }
        if (!isset($options['formats']['5'])) {
            $options['formats']['5'] = HtmlFormat::class;
        }
        // @codeCoverageIgnoreStart
        if (!isset($options['formats']['xml']) && defined('HHVM_VERSION')) {
            $options['formats']['xml'] = XmlHhvmFormat::class;
        }
        // @codeCoverageIgnoreEnd
    }

    protected function setUpCache(&$options)
    {
        if (isset($options['cachedir']) && $options['cachedir']) {
            $options['adapterclassname'] = FileAdapter::class;
        }
    }

    protected function setUpMixins(&$options)
    {
        if (isset($options['allowMixinOverride'])) {
            $options['mixin_merge_mode'] = $options['allowMixinOverride']
                ? 'replace'
                : 'ignore';
        }
    }

    protected function setUpEvents(&$options)
    {
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
    }

    protected function setUpJsPhpize(&$options)
    {
        if (isset($options['jsLanguage'])) {
            if (!isset($options['module_options'])) {
                $options['module_options'] = [];
            }
            $options['module_options']['jsphpize'] = $options['jsLanguage'];
        }
    }

    protected function setUpAttributesMapping(&$options)
    {
        if (isset($options['classAttribute'])) {
            if (!isset($options['attributes_mapping'])) {
                $options['attributes_mapping'] = [];
            }
            $options['attributes_mapping']['class'] = $options['classAttribute'];
        }
    }

    protected function initializeLimits()
    {
        // TODO: find a better way to apply snake_case options to the compiler
        $compiler = $this->getCompiler();
        if (!$compiler->hasOption('memory_limit')) {
            $compiler->setOption('memory_limit', $this->getOption('memory_limit'));
        }
        if (!$compiler->hasOption('execution_max_time')) {
            $compiler->setOption('execution_max_time', $this->getOption('memory_limit'));
        }
    }

    protected function initializeJsPhpize()
    {
        if (strtolower($this->getDefaultOption('expressionLanguage')) !== 'php') {
            $compiler = $this->getCompiler();
            $compiler->addModule(new JsPhpizePhug($compiler));
        }
    }
}

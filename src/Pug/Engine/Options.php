<?php

namespace Pug\Engine;

use JsPhpize\JsPhpizePhug;
use Phug\Formatter\Format\HtmlFormat;
use Phug\Renderer\Adapter\FileAdapter;
use Pug\Format\XmlHhvmFormat;

/**
 * Class Pug\Engine\Keywords.
 */
abstract class Options extends OptionsHandler
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
                }

                return;
            };
        }
    }

    protected function setUpOptionsAliases(&$options)
    {
        $this->setUpOptionNameHandlers();
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
        if (!isset($options['formats']['xml'])) {
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
        $this->setUpPreRender($options);
        $this->setUpPostRender($options);
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

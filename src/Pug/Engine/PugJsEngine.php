<?php

namespace Pug\Engine;

use NodejsPhpFallback\NodejsPhpFallback;

/**
 * Class Pug\PugJsEngine.
 */
class PugJsEngine extends Keywords
{
    /**
     * @var NodejsPhpFallback
     */
    protected $nodeEngine;

    /**
     * Return the NodejsPhpFallback instance used to execute pug-cli via node.
     *
     * @return NodejsPhpFallback
     */
    public function getNodeEngine()
    {
        if (!$this->nodeEngine) {
            $this->nodeEngine = new NodejsPhpFallback($this->hasOption('node_path')
                ? $this->getDefaultOption('node_path')
                : 'node'
            );
        }

        return $this->nodeEngine;
    }

    protected function getHtml($file, array &$options)
    {
        if (empty($this->getDefaultOption('cache_dir'))) {
            $html = file_get_contents($file);
            unlink($file);

            return $html;
        }

        $currentDirectory = getcwd();
        $realPath = realpath($file);

        $handler = fopen($file, 'a');
        fwrite($handler, 'module.exports=template;');
        fclose($handler);

        $directory = dirname($file);
        $renderFile = './render.' . time() . mt_rand(0, 999999999) . '.js';
        chdir($directory);
        file_put_contents($renderFile,
            'console.log(require(' . json_encode($realPath) . ')' .
            '(' . (empty($options['obj']) ? '{}' : (!$options['localsJsonFile'] ? $options['obj'] : 'require(' . json_encode($options['obj']) . ')')) . '));'
        );

        $node = $this->getNodeEngine();
        $html = $node->nodeExec($renderFile);
        unlink($renderFile);
        chdir($currentDirectory);

        return $html;
    }

    protected function parsePugJsResult($result, $path, $toDelete, array $options)
    {
        $result = explode('rendered ', $result);
        if (count($result) < 2) {
            throw new \RuntimeException(
                'Pugjs throw an error: ' . $result[0]
            );
        }
        $file = trim($result[1]);
        $html = $this->getHtml($file, $options);

        if ($toDelete) {
            unlink($path);
        }

        if ($options['localsJsonFile']) {
            unlink($options['obj']);
        }

        return $html;
    }

    protected function getPugCliArguments($options)
    {
        $args = [];

        if (!empty($options['pretty'])) {
            $args[] = '--pretty';
            unset($options['pretty']);
        }

        // options that need be encoded by json_encode
        $jsonOptions = ['pretty'];
        if (!$options['localsJsonFile']) {
            $jsonOptions[] = 'obj';
        }

        foreach ($options as $option => $value) {
            if (!empty($value)) {
                $function = in_array($option, $jsonOptions)
                    ? 'json_encode'
                    : 'escapeshellarg';
                $value = call_user_func($function, $value);
                $args[] = '--' . $option . ' ' . $value;
            }
        }

        return $args;
    }

    protected function callPugCli($input, $filename, $options, $toDelete, $fallback)
    {
        $args = $this->getPugCliArguments($options);

        if (!empty($this->getDefaultOption('cache_dir'))) {
            $args[] = '--client';
            $renderFile = $options['out'] . '/' . preg_replace('/\.[^.]+$/', '', basename($filename)) . '.js';
            if (file_exists($renderFile) && (
                    ($mTime = filemtime($renderFile)) >= filemtime($filename) ||
                    !$this->getDefaultOption('upToDateCheck')
                )) {
                if (!$input) {
                    $input = file_get_contents($filename);
                }
                $html = $this->parsePugJsResult('rendered ' . $renderFile, $input, $toDelete, $options);
                touch($renderFile, $mTime);

                return $html;
            }
        }

        $directory = dirname($filename);
        $currentDirectory = getcwd();
        $basename = basename($filename);
        chdir($directory);
        $node = $this->getNodeEngine();
        $result = $node->execModuleScript(
            'pug-cli',
            'index.js',
            implode(' ', $args) .
            ' ' . escapeshellarg($basename) .
            ' 2>&1',
            $fallback
        );
        chdir($currentDirectory);

        return $this->parsePugJsResult($result, $filename, $toDelete, $options);
    }

    /**
     * Render using the native Pug JS engine.
     *
     * @param string   $input    pug input or file
     * @param string   $filename optional file path
     * @param array    $vars     to pass to the view
     * @param callable $fallback called if JS engine not available
     *
     * @throws \Exception
     *
     * @return string
     */
    public function renderWithJs($input, $filename, $vars = null, $fallback = null)
    {
        if (is_array($filename)) {
            if (!is_null($vars)) {
                $fallback = $vars;
            }
            $vars = $filename;
            $filename = null;
        }

        $workDirectory = empty($this->getDefaultOption('cache_dir'))
            ? sys_get_temp_dir()
            : $this->getOption('cache_dir');
        if ($toDelete = !$filename) {
            $filename = $workDirectory . '/source-' . mt_rand(0, 999999999) . '.pug';
            file_put_contents($filename, $input);
        }

        $options = [
            'path'           => realpath($filename),
            'basedir'        => $this->getDefaultOption('basedir'),
            'localsJsonFile' => $this->getDefaultOption('locals_json_file'),
            'pretty'         => $this->getDefaultOption('prettyprint'),
            'out'            => $workDirectory,
        ];
        if (!empty($vars)) {
            $locals = json_encode($vars, JSON_UNESCAPED_SLASHES);

            $options['obj'] = $locals;

            if ($options['localsJsonFile']) {
                $optionsFile = $workDirectory . '/options-' . mt_rand(0, 999999999) . '.json';
                file_put_contents($optionsFile, $locals);
                $options['obj'] = $optionsFile;
            }
        }

        return $this->callPugCli($input, $filename, $options, $toDelete, $fallback);
    }

    /**
     * Render using the native Pug JS engine.
     *
     * @param string   $path     pug file
     * @param array    $vars     to pass to the view
     * @param callable $fallback called if JS engine not available
     *
     * @throws \Exception
     *
     * @return string
     */
    public function renderFileWithJs($path, array $vars, $fallback)
    {
        return $this->renderWithJs(null, $path, $vars, $fallback);
    }
}

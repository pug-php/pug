<?php

namespace Jade\Engine;

use NodejsPhpFallback\NodejsPhpFallback;

/**
 * Class Jade\PugJsEngine.
 */
class PugJsEngine extends Options
{
    /**
     * @var NodejsPhpFallback
     */
    protected $nodeEngine;

    /**
     * @return NodejsPhpFallback
     */
    public function getNodeEngine()
    {
        if (!$this->nodeEngine) {
            $this->nodeEngine = new NodejsPhpFallback($this->options['nodePath']);
        }

        return $this->nodeEngine;
    }

    protected function getPugJsOptions(&$input, &$filename, &$vars, &$pug)
    {
        if (is_array($filename)) {
            $vars = $filename;
            $filename = null;
        }

        $workDirectory = empty($this->options['cache'])
            ? sys_get_temp_dir()
            : $this->options['cache'];
        $pug = true;
        if ($filename === null && file_exists($input)) {
            $filename = $input;
            $pug = null;
        }
        if ($pug) {
            $pug = $input;
            $input = $workDirectory . '/source-' . mt_rand(0, 999999999) . '.pug';
            file_put_contents($input, $pug);
        }

        $options = array(
            'path' => realpath($filename),
            'basedir' => $this->options['basedir'],
            'pretty' => $this->options['prettyprint'],
            'out' => $workDirectory,
        );
        if (!empty($vars)) {
            $locals = json_encode($vars, JSON_UNESCAPED_SLASHES);

            $options['obj'] = $locals;

            if ($this->options['localsJsonFile']) {
                $optionsFile = $workDirectory . '/options-' . mt_rand(0, 999999999) . '.json';
                file_put_contents($optionsFile, $locals);
                $options['obj'] = $optionsFile;
            }
        }

        return $options;
    }

    protected function getHtml($file, array &$options)
    {
        if (empty($this->options['cache'])) {
            $html = file_get_contents($file);
            unlink($file);

            return $html;
        }

        $currentDirectory = getcwd();
        $realPath = realpath($file);
        if (!file_exists($realPath)) {
            $realPath = realpath($currentDirectory . DIRECTORY_SEPARATOR . $file);
        }

        $handler = fopen($file, 'a');
        fwrite($handler, 'module.exports=template;');
        fclose($handler);

        $directory = dirname($file);
        $renderFile = './render.' . time() . mt_rand(0, 999999999) . '.js';
        chdir($directory);
        file_put_contents($renderFile,
            'console.log(require(' . json_encode($realPath) . ')' .
            '(' . (empty($options['obj']) ? '{}' : (!$this->options['localsJsonFile'] ? $options['obj'] : 'require(' . json_encode($options['obj']) . ')')) . '));'
        );

        $node = $this->getNodeEngine();
        $html = $node->nodeExec($renderFile);
        unlink($renderFile);
        chdir($currentDirectory);

        return $html;
    }

    protected function parsePugJsResult($result, &$input, &$pug, array &$options)
    {
        $result = explode('rendered ', $result);
        if (count($result) < 2) {
            throw new \RuntimeException(
                'Pugjs throw an error: ' . $result[0]
            );
        }
        $file = trim($result[1]);
        $html = $this->getHtml($file, $options);

        if ($pug) {
            unlink($input);
        }

        if ($this->options['localsJsonFile']) {
            unlink($options['obj']);
        }

        return $html;
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
    public function renderWithJs($input, $filename, array $vars, $fallback)
    {
        $options = $this->getPugJsOptions($input, $filename, $vars, $pug);
        $args = array();

        if (!empty($options['pretty'])) {
            $args[] = '--pretty';
            unset($options['pretty']);
        }

        // options that need be encoded by json_encode
        $jsonOptions = array('pretty');
        if (!$this->options['localsJsonFile']) {
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

        if (!empty($this->options['cache'])) {
            $args[] = '--client';
            $renderFile = $options['out'] . '/' . preg_replace('/\.[^.]+$/', '', basename($input)) . '.js';
            if (file_exists($renderFile) && (
                ($mTime = filemtime($renderFile)) >= filemtime($input) ||
                !$this->options['upToDateCheck']
            )) {
                $html = $this->parsePugJsResult('rendered ' . $renderFile, $input, $pug, $options);
                touch($renderFile, $mTime);

                return $html;
            }
        }

        $directory = dirname($input);
        $currentDirectory = getcwd();
        $basename = basename($input);
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

        return $this->parsePugJsResult($result, $input, $pug, $options);
    }
}

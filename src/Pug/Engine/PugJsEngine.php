<?php

namespace Pug\Engine;

use NodejsPhpFallback\NodejsPhpFallback;

/**
 * Class Pug\PugJsEngine.
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
            '(' . (empty($options['obj']) ? '{}' : $options['obj']) . '));'
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
        if (is_array($filename)) {
            $vars = $filename;
            $filename = null;
        }

        $workDirectory = empty($this->options['cache'])
            ? sys_get_temp_dir()
            : $this->options['cache'];
        if ($toDelete = !$filename) {
            $filename = $workDirectory . '/source-' . mt_rand(0, 999999999) . '.pug';
            file_put_contents($filename, $input);
        }

        $options = array(
            'path' => realpath($filename),
            'basedir' => $this->options['basedir'],
            'pretty' => $this->options['prettyprint'],
            'out' => $workDirectory,
        );
        if (!empty($vars)) {
            $options['obj'] = json_encode($vars);
        }
        $args = array();

        if (!empty($options['pretty'])) {
            $args[] = '--pretty';
            unset($options['pretty']);
        }

        foreach ($options as $option => $value) {
            if (!empty($value)) {
                $function = in_array($option, array('pretty', 'obj'))
                    ? 'json_encode'
                    : 'escapeshellarg';
                $value = call_user_func($function, $value);
                $args[] = '--' . $option . ' ' . $value;
            }
        }

        if (!empty($this->options['cache'])) {
            $args[] = '--client';
            $renderFile = $options['out'] . '/' . preg_replace('/\.[^.]+$/', '', basename($filename)) . '.js';
            if (file_exists($renderFile) && (
                ($mTime = filemtime($renderFile)) >= filemtime($filename) ||
                !$this->options['upToDateCheck']
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

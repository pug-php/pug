<?php

namespace Jade\Engine;

use NodejsPhpFallback\NodejsPhpFallback;

/**
 * Class Jade\PugJsEngine.
 */
class PugJsEngine extends Options
{
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
            $input = $workDirectory . '/source.pug';
            file_put_contents($input, $pug);
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

        return $options;
    }

    protected function getHtml($file, array &$options)
    {
        if (empty($this->options['cache'])) {
            $html = file_get_contents($file);
            unlink($file);

            return $html;
        }

        $handler = fopen($file, 'a');
        fwrite($handler, 'module.exports=template;');
        fclose($handler);

        $renderFile = realpath($file) . '.render.' . mt_rand(0, 999999999) . '.js';
        file_put_contents($renderFile,
            'console.log(require(' . json_encode($file) . ')' .
            '(' . (empty($options['obj']) ? '{}' : $options['obj']) . '));'
        );
        clearstatcache();
        echo "\n" . $renderFile . "\n" . var_export(file_exists($renderFile), true) . "\n\n";

        $node = new NodejsPhpFallback();
        $html = $node->nodeExec($renderFile);
        unlink($renderFile);

        if (substr($html, 0, 1) !== '<') {
            echo "\n" . $renderFile . "\n" . var_export(file_exists($renderFile), true) . "\n\n";
            exit(1);
        }

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

        foreach ($options as $option => $value) {
            if (!empty($value)) {
                $args[] = '--' . $option . ' ' . json_encode($value);
            }
        }

        if (!empty($this->options['cache'])) {
            $args[] = '--client';
            $renderFile = $options['out'] . '/' . preg_replace('/\.[^.]+$/', '', basename($input)) . '.js';
            if (file_exists($renderFile) && filemtime($renderFile) >= filemtime($input)) {
                return $this->parsePugJsResult('rendered ' . $renderFile, $input, $pug, $options);
            }
        }

        $node = new NodejsPhpFallback();
        $result = $node->execModuleScript(
            'pug-cli',
            'index.js',
            implode(' ', $args) .
            ' ' . escapeshellarg($input) .
            ' 2>&1',
            $fallback
        );

        return $this->parsePugJsResult($result, $input, $pug, $options);
    }
}

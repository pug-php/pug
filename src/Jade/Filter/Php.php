<?php
/**
 * @Author      ronan.tessier@vaconsulting.lu
 * @Date        11/05/13
 * @File        Php.php
 * @Copyright   Copyright (c) documentation - All rights reserved
 * @Licence     Unauthorized copying of this source code, via any medium is strictly
 *              prohibited, proprietary and confidential.
 */

namespace Jade\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

/**
 * Class Php
 * @package Jade\Filter
 */
class Php {

    /**
     * @param Filter $node
     * @param Compiler $compiler
     * @return string
     */
    public function __invoke(Filter $node, Compiler $compiler)
    {
        $data = '';

        foreach ($node->block->nodes as $n)
        {
            if (preg_match('/^[[:space:]]*\|(.*)/', $n->value, $m))
            {
                $data = $m[1];
            }
            else
            {
                $data .= $n->value . "\n";
            }
        }
        return $data ? '<?php ' . $data . ' ?>' : $data;
    }

}
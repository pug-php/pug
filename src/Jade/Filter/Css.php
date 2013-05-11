<?php
/**
 * @Author      ronan.tessier@vaconsulting.lu
 * @Date        11/05/13
 * @File        Css.php
 * @Copyright   Copyright (c) documentation - All rights reserved
 * @Licence     Unauthorized copying of this source code, via any medium is strictly
 *              prohibited, proprietary and confidential.
 */

namespace Jade\Filter;

use Jade\Compiler;
use Jade\Nodes\Filter;

class Css extends FilterAbstract {

    public function __invoke(Filter $node, Compiler $compiler)
    {
        return '<style type="text/css">' . $this->getNodeString($node, $compiler) . '</style>';
    }


}
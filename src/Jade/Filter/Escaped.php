<?php
/**
 * @Author      ronan.tessier@vaconsulting.lu
 * @Date        11/05/13
 * @File        Escaped.php
 * @Copyright   Copyright (c) documentation - All rights reserved
 * @Licence     Unauthorized copying of this source code, via any medium is strictly
 *              prohibited, proprietary and confidential.
 */

namespace Jade\Filter;

class Escaped extends FilterAbstract {

    public function __invoke($node, $compiler)
    {
        return htmlentities($this->getNodeString($node, $compiler));
    }

}
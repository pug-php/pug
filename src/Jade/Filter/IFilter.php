<?php
/**
 * @Author      ronan.tessier@vaconsulting.lu
 * @Date        11/05/13
 * @File        FilterInterface.php
 * @Copyright   Copyright (c) jadephp - All rights reserved
 * @Licence     Unauthorized copying of this source code, via any medium is strictly
 *              prohibited, proprietary and confidential.
 */

namespace Jade\Filter;

/**
 * Class FilterInterface
 * @package Jade\Filter
 */
interface IFilter {

    /**
     * @param $data
     * @return string
     */
    public function get($data);

}
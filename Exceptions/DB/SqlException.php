<?php
/**
 * Created by 布卡漫画.
 * User: LwB
 * Date: 2016/12/5
 * Time: 17:42
 */

namespace Exceptions\DB;

/**
 * SQL查询异常
 * Class SqlException
 * @package Exceptions\DB
 */
class SqlException extends \Exception
{
    private $sql;

    public function __construct($message, $code, $sql)
    {
        parent::__construct($message, $code);
        $this->sql = $sql;
    }

    public function getSql()
    {
        return $this->sql;
    }
}
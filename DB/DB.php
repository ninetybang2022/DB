<?php
/**
 * Created by 布卡漫画.
 * User: LwB
 * Date: 2016/12/5
 * Time: 11:52
 */

namespace Lib\DB;

use Exceptions\DB\ConnectFail;
use Exceptions\DB\SqlException;
use Exceptions\DB\BindParamException;
/**
 * 数据库基础类
 * Class DB
 * @package Lib\DB
 */

class DB
{
    //MYSQL对象实例
    protected $db;
    //stmt实例
    protected $stmt;
    //主机名
    protected $host;
    //端口号
    protected $port;
    //用户名
    protected $name;
    //密码
    protected $pwd;
    //数据库名
    protected $dbname;
    //字符集
    protected $charset;
    //最后一次执行的SQL
    protected $lastSql;
    //最后的预处理SQL
    protected $lastPrepareSql;
    //绑定的参数
    protected $bindParams = ['types'=>'', 'bindParams'=>[]];
    //返回对象
    protected $result;
    //最后插入的ID
    protected $insertId;
    //受影响的行数
    protected $affectedRows;
    
    public function __construct($host = null, $name = null, $pwd = null, $dbname = null, $port = 3306
                                , $charset = 'utf8')
    {
        $this->host = $host;
        $this->name = $name;
        $this->pwd = $pwd;
        $this->dbname = $dbname;
        $this->port = $port;
        $this->charset = $charset;
        $this->db = $this->connect($host, $name, $pwd, $dbname, $port);
        $this->db->set_charset($charset);
        $this->stmt = $this->db->stmt_init();
    }

    /**
     * 发起一个SQL连接
     * @param $host
     * @param $name
     * @param $pwd
     * @param $dbname
     * @param $port
     * @return \Mysqli
     * @throws ConnectFail
     */
    public function connect($host, $name, $pwd, $dbname, $port)
    {
        $db = new \Mysqli($host, $name, $pwd, $dbname, $port);
        if($db->connect_errno)
        {
            throw new ConnectFail($db->connect_error, $db->connect_errno);
        }
        return $db;
    }

    /**
     * 执行没有结果集的SQL查询
     */
    public function execute($sql, $bindParams = [])
    {
        $this->prepareCommon($sql, $bindParams);
        $this->affectedRows = $this->db->affected_rows;
        $this->insertId = $this->db->insert_id;
        return $this->affectedRows;
    }

    /**
     * 执行存在结果集的SQL查询
     * @param $sql
     */
    public function query($sql, $bindParams = [])
    {
        $this->prepareCommon($sql, $bindParams);
        return $this->getResult();
    }

    private function prepareCommon($sql, $bindParams)
    {
        $this->bindParams = $bindParams?:$this->bindParams;
        $this->createPrepare($sql);
        if($this->bindParams['types'] && $this->bindParams['bindParams'])
        {
            $this->createBindParam($bindParams['types'], $bindParams['bindParams']);
        }
        $this->stmtExecute();
    }

    /**
     * 创建预处理语句
     * @param $sql
     * @throws SqlException
     */
    private function createPrepare($sql)
    {
        $this->lastPrepareSql = $sql;
        $this->stmt->prepare($sql);
        if($this->db->errno)
        {
            throw new SqlException($this->db->error, $this->db->errno, $this->getLastSql());
        }
    }

    /**
     * 创建绑定参数
     * @param $types
     * @param $fieldVals
     */
    private function createBindParam($types, $bindParams)
    {
        $bindVarNames = array_keys($bindParams);
        if($this->checkBindParamNumberIsOk($bindVarNames))
        {
            $bindParamEval = '$this->stmt->bind_param("'.$types.'", ' .$this->fieldsToVarsString($bindVarNames). ');';
            eval($bindParamEval);
            extract($bindParams);
        }
    }

    private function stmtExecute()
    {
        if($this->stmt->execute())
        {
            $this->result = $this->stmt->get_result();
        }
        else
        {
            throw new SqlException($this->stmt->error, $this->stmt->errno, $this->getLastSql());
        }
    }

    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    private function getResult()
    {
        $result = $this->result->fetch_all(MYSQLI_ASSOC);
        return $result;
    }

    public function __destruct()
    {
        if($this->result)
        {
            $this->freeResultStmt();
        }else{

        }
        $this->freeStmt();
        $this->close();
    }

    public function freeResultStmt()
    {
        $this->result->free();
        $this->freeStmt();
        $this->close();
    }

    private function freeStmt()
    {
        $this->stmt->free_result();
        $this->stmt->close();
    }

    /**
     * 检测参数绑定和问号数据是否一致
     * @param $bindParam
     * @return bool
     * @throws BindParamException
     */
    private function checkBindParamNumberIsOk($bindVarNames)
    {
        $questionMarkNum = substr_count($this->lastPrepareSql, '?');
        $bindVarNameCount = count($bindVarNames);
        if($questionMarkNum != $bindVarNameCount)
        {
            throw new BindParamException('The number of binding parameters is not correct the correct number is '
                                        . $questionMarkNum);
        }
        return true;
    }

    private function fieldsToVarsString($fields)
    {
        $fieldVarString = '';
        foreach($fields as $field)
        {
            $fieldVarString .= '$' . $field . ', ';
        }
        return rtrim($fieldVarString, ', ');
    }

    /**
     * 获取最后一个SQL错误
     * @return string
     */
    public function getError()
    {
        if($this->db->errno)
        {
            return $this->db->error;
        }
        return '';
    }

    /**
     * 获取最后一条执行的SQL语句
     */
    public function getLastSql()
    {
        $bindParamValues = array_values($this->bindParams['bindParams']);
        $types  = str_split($this->bindParams['types']);
        $lastSql = preg_replace_callback('/\?/', function($match) use (&$types, &$bindParamValues){
            $ctype = array_shift($types);
            $cvalue = array_shift($bindParamValues);
            if($ctype == 's')
            {
                return "'{$cvalue}'";
            }
            return $cvalue;
        }, $this->lastPrepareSql);
        return $lastSql;
    }

    /**
     * 获取插入语句最后的主键ID
     * @return mixed
     */
    public function getInsertId()
    {
        return $this->insertId;
    }

    /**
     * 关闭SQL连接
     */
    public function close()
    {
        $this->db->close();
    }

    /**
     * 开启事务
     */
    public function begin()
    {
        return $this->db->autocommit(false) && $this->db->begin_transaction();
    }

    /**
     * 提交
     */
    public function commit()
    {
        return $this->db->commit();
    }

    public function rollback()
    {
        return $this->db->rollback();
    }

}
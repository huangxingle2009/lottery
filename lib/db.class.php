<?php
/**
 * mysqli db class - WhileDo MVC
 *
 * @author HzqGhost <admin@whiledo.com>
 * @version 1.2.0
 * @modified by admpub.com
 */
namespace lib;
class db
{
    /**
     * *错误编号
     */
    public static $is_error = false;
    /**
     * *当执行出错时是否中断
     */
    public static $OnErrorStop = false;
    /**
     * *当执行出错时是否提示错误信息
     */
    public static $OnErrorShow = true;
    /**
     * *当前查询SQL语句
     */
    protected static $sql = '';
    /**
     * *mysqli 对象
     */
    protected static $mysqli = null;
    /**
     * *当前结果集
     */
    protected static $result = false;
    /**
     * *查询统计次数
     */
    protected static $query_count = 0;
    /**
     * *当前查询是否开户了事物处理
     */
    protected static $is_commit = false;

    /**
     * *执行查询
     *
     * @param  $sql [string] :SQL查询语句
     * @return 成功赋值并返回self::$result; 失败返回 false 如果有事务则回滚
     */
    public static function query($sql)
    {
        self:: connect();
        self:: $sql = $sql;
        self:: $result = self:: $mysqli->query($sql);
        if (self:: $mysqli->error) {
            $error = sprintf("SQL Query Error: %s\r\n", self:: $mysqli->error);
            self:: $is_error = true;
            self:: log($error);

            //重新执行一次
            self:: $mysqli = null;
            self::connect();
            self:: $result = self:: $mysqli->query($sql);
            return self:: $result;

            if (self:: $OnErrorStop) exit;
            return false;
        } else {
            self:: $query_count++;
        }
        return self:: $result;
    }

    /**
     * *查询指定SQl 第一行，第一列 值
     *
     * @param  $sql [string] :SQL查询语句
     * @return 失败返回 false
     */
    public static function data_scalar($sql)
    {
        if (self:: $result = self:: query($sql)) {
            return self:: fetch_scalar();
        } else {
            return false;
        }
    }

    /**
     * *查询指定SQl 第一行记录
     *
     * @param  $sql [string] :SQL查询语句
     * @param  $assoc [bool] :true 返回数组; false 返回stdClass对象;默认 false
     * @return 失败返回 false
     */
    public static function data_row($sql, $assoc = false)
    {
        if (self:: $result = self:: query($sql)) {
            return self:: fetch_row(self:: $result, $assoc);
        } else {
            return false;
        }
    }

    /**
     * *查询指定SQl 所有记录
     *
     * @param  $sql [string] :SQL查询语句
     * @param  $key_field [string] :指定记录结果键值使用哪个字段,默认为 false 使用 regI{0...count}
     * @param  $assoc [bool] :true 返回数组; false 返回stdClass对象;默认 false
     * @return 失败返回 false
     */
    public static function data_table($sql, $key_field = false, $assoc = false)
    {
        if (self:: $result = self:: query($sql)) {
            return self:: fetch_all($key_field, $assoc);
        } else {
            return false;
        }
    }

    /**
     * *取结果(self::$result)中第一行，第一列值
     *
     * @return 没有结果返回 false
     */
    public static function fetch_scalar()
    {
        if (!empty(self:: $result)) {
            $row = self:: $result->fetch_array();
            return $row[0];
        } else {
            return false;
        }
    }

    /**
     * *取结果$result中第一行记录
     *
     * @param  $result [object] :查询结果数据集
     * @param  $assoc [bool] :true 返回数组; false 返回stdClass对象;默认 false
     * @return 没有结果返回 false
     */
    public static function fetch_row($result = null, $assoc = false)
    {
        if ($result == null) $result = self:: $result;
        if (empty($result)) {
            return false;
        }
        if ($assoc) {
            return $result->fetch_assoc();
        } else {
            return $result->fetch_object();
        }
    }

    /**
     * *取结果(self::$result)中所有记录
     *
     * @param  $key_field [string] :指定记录结果键值使用哪个字段,默认为 false 则使用 regI{0...count}
     * @param  $assoc [bool] :true 返回数组; false 返回stdClass对象;默认 false
     * @return 没有结果返回 false
     */
    public static function fetch_all($key_field = false, $assoc = false)
    {
        $rows = ($assoc) ? array() : new \stdClass;
        $regI = -1;
        while ($row = self:: fetch_row(self:: $result, $assoc)) {
            if ($key_field != false) {
                $regI = ($assoc) ? $row[$key_field] : $row->$key_field;
            } else {
                $regI++;
            }
            if ($assoc) {
                $rows[$regI] = $row;
            } else {
                $rows->{
                $regI} = $row;
            }
        }
        self:: free_result();
        return ($regI > -1) ? $rows : false;
    }

    /**
     * 执行更新数据操作
     *
     * @param  $table [string] 数据库表名称
     * @param  $data [array|stdClass] 待更新的数据
     * @param  $where [string] 更新条件
     * @return 成功 true; 失败 false
     */
    public static function update($table, $data, $where)
    {
        $set = '';
        if (is_object($data) || is_array($data)) {
            foreach ($data as $k => $v) {
                self:: format_value($v);
                $set .= empty($set) ? ("`{$k}` = {$v}") : (", `{$k}` = {$v}");
            }
        } else {
            $set = $data;
        }
        return self:: query("UPDATE `{$table}` SET {$set} WHERE {$where}");
    }

    /**
     * 执行插入数据操作
     *
     * @param  $table [string] 数据库表名称
     * @param  $data [array|stdClass] 待更新的数据
     * @param  $fields [string] 数据库字段，默认为 null。 为空时取 $data的 keys
     * @return 成功 true; 失败 false
     */
    public static function insert($table, $data, $fields = null)
    {
        if ($fields == null) {
            foreach ($data as $v) {
                if (is_array($v)) {
                    $fields = array_keys($v);
                } elseif (is_object($v)) {
                    foreach ($v as $k2 => $v2) {
                        $fields[] = $k2;
                    }
                } elseif (is_array($data)) {
                    $fields = array_keys($data);
                } elseif (is_object($data)) {
                    foreach ($data as $k2 => $v2) {
                        $fields[] = $k2;
                    }
                }
                break;
            }
        }
        $_fields = '`' . implode('`, `', $fields) . '`';
        $_data = self:: format_insert_data($data);
        return self:: query("INSERT INTO `{$table}` ({$_fields}) VALUES {$_data}");
    }

    /**
     * *格式化插入数据
     *
     * @param  $data [array|stdClass] 待格式化的插入数据
     * @return insert 中 values 后的 SQL格式
     */
    protected static function format_insert_data($data)
    {
        $output = '';
        $is_list = false;
        foreach ($data as $value) {
            if (is_object($value) || is_array($value)) {
                $is_list = true;
                $tmp = '';
                foreach ($value as $v) {
                    self:: format_value($v);
                    $tmp .= !empty($tmp) ? ", {$v}" : $v;
                }
                $tmp = "(" . $tmp . ")";
                $output .= !empty($output) ? ", {$tmp}" : $tmp;
                unset($tmp);
            } else {
                self:: format_value($value);
                $output .= !empty($output) ? ", {$value}" : $value;
            }
        }
        if (!$is_list) $output = '(' . $output . ')';
        return $output;
    }

    /**
     * *格式化值
     *
     * @param  $ &$value [string] 待格式化的字符串,格式成可被数据库接受的格式
     */
    protected static function format_value(&$value)
    {
        $value = trim($value);
        if ($value === null || $value == '') {
            $value = 'NULL';
        } elseif (preg_match('/\[\w+\]\.\(.*?\)/', $value)) { // mysql函数 格式:[UNHEX].(参数);
            $value = preg_replace('/\[(\w+)\]\.\((.*?)\)/', "$1($2)", $value);
        } else {
            // $value = "'" . addslashes(stripslashes($value)) ."'";strip
            $value = "'" . addslashes(stripslashes($value)) . "'";
        }
    }

    /**
     * *返回最后一次插入的ID
     */
    public static function insert_id()
    {
        return self:: $mysqli->insert_id;
    }

    /**
     * *返回结果集数量
     *
     * @param  $result [数据集]
     */
    public static function num_rows($result = null)
    {
        if (is_null($result)) $result = self:: $result;
        return mysqli_num_rows($result);
    }

    /**
     * *统计表记录
     *
     * @param  $table [string] 数据库表名称
     * @param  $where [string] SQL统计条件,默认为 1 查询整个表
     */
    public static function total($table, $where = '1')
    {
        $sql = "SELECT count(*) FROM {$table} WHERE {$where}";
        self:: query($sql);
        return self:: fetch_scalar();
    }

    /**
     * *返回当前查询SQl语句
     */
    public static function get_sql()
    {
        return self:: $sql;
    }

    /**
     * *返回当前查询影响的记录数
     */
    public static function get_nums()
    {
        return self:: $result->num_rows;
    }

    /**
     * *开始事物处理,关闭MYSQL的自动提交模式
     */
    public static function commit_begin()
    {
        self:: connect();
        self:: $is_error = false;
        self:: $mysqli->autocommit(false); //使用事物处理,不自动提交
        self:: $is_commit = true;
    }

    /**
     * *提交事物处理
     */
    public static function commit_end()
    {
        if (self:: $is_commit) {
            self:: $mysqli->commit();
        }
        self:: $mysqli->autocommit(true); //不使用事物处理,开启MYSQL的自动提交模式
        self:: $is_commit = false;
        self:: $is_error = false;
    }

    /**
     * *回滚事物处理
     */
    public static function rollback()
    {
        self:: $mysqli->rollback();
    }

    /**
     * *释放数据集
     */
    public static function free_result($result = null)
    {
        if (is_null($result)) $result = self:: $result;
        @mysqli_free_result($result);
    }

    /**
     * *选择数据库
     *
     * @param  $dbname [string] 数据库名称
     */
    public static function select_db($dbname)
    {
        self:: connect();
        return self:: $mysqli->select_db($dbname);
    }

    /**
     * *连接Mysql
     */
    protected static function connect()
    {
        if (is_null(self:: $mysqli)) {
            self:: $mysqli = new \mysqli($GLOBALS['database']['db_host'],
                $GLOBALS['database']['db_user'],
                $GLOBALS['database']['db_pass'],
                $GLOBALS['database']['db_name'],
                $GLOBALS['database']['db_port']);
            if (mysqli_connect_errno()) {
                $error = sprintf("Database Connect failed: %s\r\n", mysqli_connect_error());
                self:: log($error);
                exit;
            } else {
                self:: $mysqli->query("SET character_set_connection=" . $GLOBALS['database']['db_charset'] . ", character_set_results=" . $GLOBALS['database']['db_charset'] . ", character_set_client=binary");
            }
        }
    }

    /**
     * *日志处理
     *
     * @param  $message [string] 产生的日志消息
     */
    protected static function log($message)
    {
        if (self:: $OnErrorShow) {
            echo sys:: error_format($message . '<div style="color:#000;background-color: #ffffe1;padding:4px;">' . self:: $sql . '</div>', -1);
        } else {
            sys:: log($message . '<div style="color:#000;background-color: #ffffe1;padding:4px;">' . self:: $sql . '</div>', -1, __FILE__);
        }
        if (self:: $OnErrorStop) {
            exit;
        }
    }
}

class sys {
    public static function error_format($error_info, $jump_url = 0) {
        return $error_info;
    }
    public static function log($error_info, $jump_url = 0,$file=null) {
        echo $error_info,' file:',$file;
    }
}

?>
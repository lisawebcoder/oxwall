<?php
declare(strict_types=1);

/**
 * EXHIBIT A. Common Public Attribution License Version 1.0
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the “License”);
 * you may not use this file except in compliance with the License. You may obtain a copy of the License at
 * http://www.oxwall.org/license. The License is based on the Mozilla Public License Version 1.1
 * but Sections 14 and 15 have been added to cover use of software over a computer network and provide for
 * limited attribution for the Original Developer. In addition, Exhibit A has been modified to be consistent
 * with Exhibit B. Software distributed under the License is distributed on an “AS IS” basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for the specific language
 * governing rights and limitations under the License. The Original Code is Oxwall software.
 * The Initial Developer of the Original Code is Oxwall Foundation (http://www.oxwall.org/foundation).
 * All portions of the code written by Oxwall Foundation are Copyright (c) 2011. All Rights Reserved.
 * EXHIBIT B. Attribution Information
 * Attribution Copyright Notice: Copyright 2011 Oxwall Foundation. All rights reserved.
 * Attribution Phrase (not exceeding 10 words): Powered by Oxwall community software
 * Attribution URL: http://www.oxwall.org/
 * Graphic Image as provided in the Covered Code.
 * Display of Attribution Information is required in Larger Works which are defined in the CPAL as a work
 * which combines Covered Code or portions thereof with code not governed by the terms of the CPAL.
 */

/**
 * Core database connection class.
 *
 * @author  Sardar Madumarov <madumarov@gmail.com>, Nurlan Dzhumakaliev <nurlanj@live.com>
 * @package ow_core
 * @since   1.0
 */
final class OW_Database
{
    //const DEFAULT_CACHE_LIFETIME = false;
    private const NO_CACHE_ENTRY = 'ow_db_no_cache_entry';

    /**
     * @var array
     */
    private static $classInstances;

    /**
     * Mysql connection object
     *
     * @var PDO
     */
    private $connection;

    /**
     * Number of rows affected by the last SQL statement
     *
     * @var int $affectedRows
     */
    private $affectedRows;

    /**
     * Logger data
     *
     * @var array
     */
    private $queryLog;

    /**
     * Debug option
     *
     * @var boolean
     */
    private $debugMode;

    /**
     * Enter description here...
     *
     * @var boolean
     */
    private $isProfilerEnabled;

    /**
     * Enter description here...
     *
     * @var UTIL_Profiler
     */
    private $profiler;

    /**
     * Last executed query
     *
     * @var int
     */
    private $queryExecTime;

    /**
     * Enter description here...
     *
     * @var int
     */
    private $totalQueryExecTime;

    /**
     *
     * @var int
     */
    private $queryCount;

    /**
     * @var bool
     */
    private $useCache;

    /**
     * @var string
     */
    private $queryString;

    /**
     * Getter for $log property
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Getter for $queryExecTime property
     *
     * @return int
     */
    public function getQueryExecTime()
    {
        return $this->queryExecTime;
    }

    /**
     * @return int
     */
    public function getTotalQueryExecTime()
    {
        return $this->totalQueryExecTime;
    }

    /**
     * @return int
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * @return string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * @return bool
     */
    public function getUseCashe()
    {
        return $this->useCache;
    }

    /**
     * @param bool $useCache
     */
    public function setUseCashe($useCache)
    {
        $this->useCache = (bool)$useCache;
    }

    /**
     * Constructor.
     *
     * @param array $params
     */
    private function __construct($params)
    {
        $port   = isset($params['port']) ? (int)$params['port'] : null;
        $socket = $params['socket'] ?? null;

        try {
            if ($socket === null) {
                $dsn = "mysql:host={$params['host']};";
                if ($port !== null) {
                    $dsn .= "port={$params['port']};";
                }
            } else {
                $dsn = "mysql:unix_socket={$socket};";
            }
            $dsn .= "dbname={$params['dbname']}";

            $this->connection = new PDO($dsn, $params['username'], $params['password'],
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET NAMES UTF8MB4;',
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]);

            if (!$this->isMysqlValidVersion()) {
                throw new InvalidArgumentException('Cant connect to database. Connection needs MySQL version 5.0 + !');
            }

            $this->prepareMysql();

            if (!empty($params['profilerEnable'])) {
                $this->isProfilerEnabled  = true;
                $this->profiler           = UTIL_Profiler::getInstance('db');
                $this->queryCount         = 0;
                $this->queryExecTime      = 0;
                $this->totalQueryExecTime = 0;
                $this->queryLog           = [];
            }

            if (!empty($params['debugMode'])) {
                $this->debugMode = true;
            }

            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->useCache = false;
        } catch (PDOException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
    }

    /**
     * Returns the instance of class for $params
     *
     * @param array $params ( host, username, password, dbname, [socket], [port] )
     *
     * @return OW_Database
     *
     */
    public static function getInstance($params)
    {
        if (!isset(self::$classInstances)) {
            self::$classInstances = [];
        }

        ksort($params);

        $connectionKey = serialize($params);

        if (empty(self::$classInstances[$connectionKey])) {
            if (!isset($params['host'], $params['username'], $params['password'], $params['dbname'])) {
                throw new InvalidArgumentException("Can't connect to database. Please provide valid connection attributes.");
            }

            self::$classInstances[$connectionKey] = new self($params);
        }

        return self::$classInstances[$connectionKey];
    }

    /**
     * @param string $sql
     * @param array  $params
     * @param int    $cacheLifeTime
     * @param array  $tags
     * @return mixed
     */
    public function queryForColumn($sql, array $params = null, $cacheLifeTime = 0, $tags = [])
    {
        $dataFromCache = $this->getFromCache($sql, $params, $cacheLifeTime);

        if ($dataFromCache !== self::NO_CACHE_ENTRY) {
            return $dataFromCache;
        }

        $stmt   = $this->execute($sql, $params);
        $result = $stmt->fetchColumn(); // (PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        if ($result === false) {
            $result = null;
        }

        $this->saveToCache($result, $sql, $params, $cacheLifeTime, $tags);
        return $result;
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param string $className
     * @param array  $params
     * @param int    $cacheLifeTime
     * @param array  $tags
     * @return object|null|$className
     */
    public function queryForObject($sql, $className, array $params = null, $cacheLifeTime = 0, $tags = [])
    {
        $dataFromCache = $this->getFromCache($sql, $params, $cacheLifeTime);

        if ($dataFromCache !== self::NO_CACHE_ENTRY) {
            return $dataFromCache;
        }

        $stmt = $this->execute($sql, $params);
        $stmt->setFetchMode(PDO::FETCH_CLASS, $className);
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result === false) {
            $result = null;
        } else {
            $result->generateFieldsHash();
        }

        $this->saveToCache($result, $sql, $params, $cacheLifeTime, $tags);
        return $result;
    }

    /**
     *
     * @param string $sql
     * @param string $className
     * @param array  $params
     * @param int    $cacheLifeTime
     * @param array  $tags
     * @return array
     */
    public function queryForObjectList($sql, $className, array $params = null, $cacheLifeTime = 0, $tags = [])
    {
        $dataFromCache = $this->getFromCache($sql, $params, $cacheLifeTime);

        if ($dataFromCache !== self::NO_CACHE_ENTRY) {
            return $dataFromCache;
        }

        $stmt   = $this->execute($sql, $params);
        $result = $stmt->fetchAll(PDO::FETCH_CLASS, $className);

        /** @var OW_Entity $item */
        foreach ($result as $item) {
            $item->generateFieldsHash();
        }

        $this->saveToCache($result, $sql, $params, $cacheLifeTime, $tags);
        return $result;
    }

    /**
     * Set time zone
     *
     * @return void
     * @throws Exception
     */
    public function setTimezone()
    {
        $date = new DateTime;
        $this->query('SET TIME_ZONE = ?', [
            $date->format('P'),
        ]);
    }

    /**
     * @param string $sql
     * @param array  $params
     * @param int    $cacheLifeTime
     * @param array  $tags
     * @return array
     */
    public function queryForRow($sql, array $params = null, $cacheLifeTime = 0, $tags = [])
    {
        $dataFromCache = $this->getFromCache($sql, $params, $cacheLifeTime);

        if ($dataFromCache !== self::NO_CACHE_ENTRY) {
            return $dataFromCache;
        }

        $stmt   = $this->execute($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($result === false) {
            $result = [];
        }

        $this->saveToCache($result, $sql, $params, $cacheLifeTime, $tags);
        return $result;
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param array  $params
     * @param int    $cacheLifeTime
     * @param array  $tags
     * @return array
     */
    public function queryForList($sql, array $params = null, $cacheLifeTime = 0, $tags = [])
    {
        $dataFromCache = $this->getFromCache($sql, $params, $cacheLifeTime);

        if ($dataFromCache !== self::NO_CACHE_ENTRY) {
            return $dataFromCache;
        }

        $stmt   = $this->execute($sql, $params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->saveToCache($result, $sql, $params, $cacheLifeTime, $tags);
        return $result;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @param int    $cacheLifeTime
     * @param array  $tags
     * @return array
     */
    public function queryForColumnList(string $sql, array $params = null, int $cacheLifeTime = 0, array $tags = [])
    {
        $dataFromCache = $this->getFromCache($sql, $params, $cacheLifeTime);

        if ($dataFromCache !== self::NO_CACHE_ENTRY) {
            return $dataFromCache;
        }

        $stmt   = $this->execute($sql, $params);
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->saveToCache($result, $sql, $params, $cacheLifeTime, $tags);
        return $result;
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param array  $params
     * @return int
     */
    public function query($sql, array $params = null)
    {
        $stmt     = $this->execute($sql, $params);
        $rowCount = $stmt->rowCount();
        $stmt->closeCursor();
        return $rowCount;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @return int
     */
    public function delete($sql, array $params = null)
    {
        return $this->query($sql, $params);
    }

    /**
     * insert data and return last insert id
     *
     * @param string $sql
     * @param array  $params
     * @return int last_insert_id
     */
    public function insert($sql, array $params = null)
    {
        $stmt         = $this->execute($sql, $params);
        $lastInsertId = $this->connection->lastInsertId();
        $stmt->closeCursor();
        return $lastInsertId;
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param array  $params
     * @return int
     */
    public function update($sql, array $params = null)
    {
        return $this->query($sql, $params);
    }

    /**
     * Insert object $obj to table $tableName. Returns last_insert_id
     * throws InvalidArgumentException
     *
     * @param string $tableName
     * @param object $obj
     * @param bool   $delayed
     * @return int
     */
    public function insertObject($tableName, $obj, $delayed = false)
    {
        if ($obj != null && is_object($obj)) {
            $params     = get_object_vars($obj);
            $paramNames = array_keys($params);
            $columns    = UTIL_String::arrayToDelimitedString($paramNames, ',', '`', '`');
            $values     = UTIL_String::arrayToDelimitedString($paramNames, ',', ':');
            $sql        = 'INSERT' . ($delayed ? ' DELAYED' : '') . " INTO `{$tableName}` ({$columns}) VALUES ({$values})";

            return $this->insert($sql, $params);
        }

        throw new InvalidArgumentException('object expected');
    }

    /**
     * @param string    $tableName
     * @param OW_Entity $obj
     * @param string    $primaryKeyName
     * @param bool      $lowPriority
     * @return bool|int
     */
    public function updateObject($tableName, $obj, $primaryKeyName = 'id', $lowPriority = false)
    {
        if ($obj !== null && is_object($obj)) {
            $params = get_object_vars($obj);

            if (!array_key_exists($primaryKeyName, $params)) {
                throw new InvalidArgumentException('object property not found');
            }

            $fieldsToUpdate = $obj->getEntinyUpdatedFields();

            if (empty($fieldsToUpdate)) {
                return true;
            }

            $updateArray = [];
            foreach ($params as $key => $value) {
                if ($key !== $primaryKeyName) {
                    if (in_array($key, $fieldsToUpdate)) {
                        $updateArray[] = '`' . $key . '`=:' . $key;
                    } else {
                        unset($params[$key]);
                    }
                }
            }

            $updateStmt = UTIL_String::arrayToDelimitedString($updateArray);
            $sql        = 'UPDATE' . ($lowPriority ? ' LOW_PRIORITY' : '') . " `{$tableName}` SET {$updateStmt} WHERE {$primaryKeyName}=:{$primaryKeyName}";
            return $this->update($sql, $params);
        }

        throw new InvalidArgumentException('object expected');
    }

    public function mergeInClause(array $valueList)
    {
        if ($valueList === null) {
            return '';
        }

        $result = '';
        foreach ($valueList as $value) {
            $result .= ('\'' . $this->escapeString($value) . '\','); //"'$value',"
        }

        $result = mb_substr($result, 0, -1);
        return $result;
    }

    public function batchInsertOrUpdateObjectList($tableName, $objects, $batchSize = 50)
    {
        if ($objects != null && is_array($objects)) {
            if (count($objects) > 0) {
                $columns    = '';
                $paramNames = [];

                if (is_object($objects[0])) {
                    $params     = get_object_vars($objects[0]);
                    $paramNames = array_keys($params);
                    $columns    = UTIL_String::arrayToDelimitedString($paramNames, ',', '`', '`');
                } else {
                    throw new InvalidArgumentException('Array of objects expected');
                }

                $i                 = 0;
                $totalInsertsCount = 0;
                $objectsCount      = count($objects);
                $batchSize         = (int)$batchSize;
                $inserts           = [];

                foreach ($objects as $obj) {
                    $values = '(';
                    foreach ($paramNames as $property) {
                        if ($obj->$property !== null) {
                            $values .= ('\'' . $this->escapeString($obj->$property) . '\',');
                        } else {
                            $values .= 'NULL,';
                        }
                    }
                    $values    = mb_substr($values, 0, -1);
                    $values    .= ')';
                    $inserts[] = $values;

                    $i++;
                    $totalInsertsCount++;

                    if ($i === $batchSize || $totalInsertsCount === $objectsCount) {
                        $sql     = "REPLACE INTO `{$tableName}` ({$columns}) VALUES" . implode(',', $inserts);
                        $inserts = [];
                        $i       = 0;
                        $this->execute($sql)->closeCursor();
                        //$this->connection->query($sql)->closeCursor();
                    }
                }
            }
        } else {
            throw new InvalidArgumentException('Array expected');
        }
    }

    /**
     * Escapes SQL string
     *
     * @param mixed $string
     * @return string
     */
    public function escapeString($string)
    {
        $quotedString = $this->connection->quote((string)$string); // real_escape_string( $string );
        return mb_substr($quotedString, 1, -1); //dirty hack to delete quotes
    }

    /**
     * Returns affected rows
     *
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    /**
     * Returns last insert id
     *
     * @param null $seqname
     * @return integer
     */
    public function getInsertId($seqname = null)
    {
        return $this->connection->lastInsertId($seqname);
    }

    /**
     * Class destruct actions
     */
    public function __destruct()
    {
        if (isset($this->connection)) {
            $this->connection = null;
        }
    }

    /**
     * Returns current PDOStatement
     *
     * @param            $sql
     * @param array|null $params
     * @return PDOStatement
     */
    private function execute($sql, array $params = null)
    {
        if ($this->isProfilerEnabled) {
            $this->profiler->reset();
        }

        /* @var PDOStatement $stmt */
        $stmt = $this->connection->prepare($sql);
        if ($params !== null) {
            foreach ($params as $key => $value) {
                $paramType = PDO::PARAM_STR;
                if (is_int($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $paramType = PDO::PARAM_BOOL;
                }

                $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $paramType);
            }
        }
        OW::getEventManager()->trigger(new OW_Event('core.sql.exec_query', ['sql' => $sql, 'params' => $params]));
        $stmt->execute(); //TODO setup profiler
        $this->affectedRows = $stmt->rowCount();

        if ($this->isProfilerEnabled) {
            $this->queryExecTime      = $this->profiler->getTotalTime();
            $this->totalQueryExecTime += $this->queryExecTime;

            $this->queryCount++;
            $this->queryLog[] = ['query' => $sql, 'execTime' => $this->queryExecTime, 'params' => $params];
        }

        return $stmt;
    }

    /**
     * Check if MySQL version is 5+
     *
     * @return boolean
     */
    private function isMysqlValidVersion()
    {
        $verArray = explode('.', $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION));
        return (int)$verArray[0] >= 5;
    }

    /**
     * Set additional MySQL server settings
     */
    private function prepareMysql()
    {
        if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $verArray = explode('.', $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION));

            if ((int)$verArray[0] == 5 && (int)$verArray[1] >= 7 && (int)$verArray[2] >= 9) {
                $this->connection->exec(' SET SESSION sql_mode = ""; ');
            }
        }
    }

    private function getCacheKeyForQuery($query, $params)
    {
        return 'core.sql.' . md5(trim($query) . serialize($params));
    }

    private function cacheEnabled($expTime)
    {
        return !OW_DEV_MODE && $this->useCache && ($expTime === false || $expTime > 0);
    }

    /**
     * @return OW_CacheManager
     */
    private function getCacheManager()
    {
        return OW::getCacheManager();
    }

    private function getFromCache($sql, $params, $cacheLifeTime)
    {
        if ($this->cacheEnabled($cacheLifeTime)) {
            $cacheKey  = $this->getCacheKeyForQuery($sql, $params ?: []);
            $cacheData = $this->getCacheManager()->load($cacheKey);

            if ($cacheData !== null) {
                return unserialize($cacheData);
            }
        }

        $data = OW::getEventManager()->call('core.sql.get_query_result', ['sql' => $sql, 'params' => $params]);

        if (is_array($data) && isset($data['result']) && $data['result'] === true) {
            return $data['value'];
        }

        return self::NO_CACHE_ENTRY;
    }

    private function saveToCache($result, $sql, $params, $cacheLifeTime, $tags)
    {
        if ($this->cacheEnabled($cacheLifeTime)) {
            $cacheKey = $this->getCacheKeyForQuery($sql, $params ?: []);
            $this->getCacheManager()->save(serialize($result), $cacheKey, $tags, $cacheLifeTime);
        }

        OW::getEventManager()->trigger(new OW_Event('core.sql.set_query_result',
            ['sql' => $sql, 'params' => $params, 'result' => $result]));
    }
}

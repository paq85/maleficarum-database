<?php
/**
 * This class provides CRUD implementation specific to MSSQL database.
 *
 * NOTICE:
 * If you're insane and wish to have a Model with over \Maleficarum\Database\Shard\Connection\Mssql\Connection::PDO_PARAMS_LIMIT
 * properties (columns) this class might not work properly because of prepared statements caching ;)
 */
declare (strict_types=1);

namespace Maleficarum\Database\Data\Model\Mssql;

abstract class Model extends \Maleficarum\Database\Data\Model\AbstractModel {
    /* ------------------------------------ Database\AbstractModel START ------------------------------- */

    /**
     * @see \Maleficarum\Database\Data\Model\AbstractModel::create()
     */
    public function create(): \Maleficarum\Database\Data\Model\AbstractModel {
        // connect to shard if necessary
        $shard = $this->getDb()->fetchShard($this->getShardRoute());
        $shard->isConnected() or $shard->connect();

        // fetch DB DTO object
        $data = $this->getDbDTO();

        // build the query
        $query = 'INSERT INTO "' . $this->getTable() . '" (';

        // attach column names
        $temp = [];
        foreach ($data as $el) {
            $temp[] = $el['column'];
        }
        count($temp) and $query .= '"' . implode('", "', $temp) . '"';

        // attach query transitional segment
        $query .= ') OUTPUT inserted.* VALUES (';

        // attach parameter names
        $temp = [];
        foreach ($data as $el) {
            $temp[] = $el['param'];
        }
        count($temp) and $query .= implode(', ', $temp);

        // conclude query building
        $query .= ')';

        $queryParams = [];
        foreach ($data as $el) {
            $queryParams[$el['param']] = $el['value'];
        }
        // prepare new statement every time as MSSQL driver has some issues
        // @link https://github.com/Microsoft/msphpsql/issues/60
        self::$st[static::class . '::' . __FUNCTION__] = $shard->prepareStatement($query, $queryParams);

        // execute the query
        self::$st[static::class . '::' . __FUNCTION__]->execute();

        $returnData = self::$st[static::class . '::' . __FUNCTION__]->fetch();
        // set new model ID if possible
        is_array($returnData) and $this->merge($returnData);

        return $this;
    }

    /**
     * @see \Maleficarum\Database\Data\Model\AbstractModel::read()
     */
    public function read(): \Maleficarum\Database\Data\Model\AbstractModel {
        // connect to shard if necessary
        $shard = $this->getDb()->fetchShard($this->getShardRoute());
        $shard->isConnected() or $shard->connect();

        // build the query
        $query = 'SELECT * FROM "' . $this->getTable() . '" WHERE "' . $this->getIdColumn() . '" = :id';
        $queryParams = [':id' => $this->getId()];
        // prepare new statement every time as MSSQL driver has some issues
        // @link https://github.com/Microsoft/msphpsql/issues/60
        self::$st[static::class . '::' . __FUNCTION__] = $shard->prepareStatement($query, $queryParams);

        if (!self::$st[static::class . '::' . __FUNCTION__]->execute() || count($result = self::$st[static::class . '::' . __FUNCTION__]->fetch()) === 0) {
            throw new \RuntimeException('No entity found - ID: ' . $this->getId() . '. ' . static::class . '::read()');
        }

        // fetch results and merge them into this object
        $this->merge($result);

        return $this;
    }

    /**
     * @see \Maleficarum\Database\Data\Model\AbstractModel::update()
     */
    public function update(): \Maleficarum\Database\Data\Model\AbstractModel {
        // connect to shard if necessary
        $shard = $this->getDb()->fetchShard($this->getShardRoute());
        $shard->isConnected() or $shard->connect();

        // fetch DB DTO object
        $data = $this->getDbDTO();

        // build the query
        $query = 'UPDATE "' . $this->getTable() . '" SET ';

        // attach data definition
        $temp = [];
        foreach ($data as $el) {
            $temp[] = '"' . $el['column'] . '" = ' . $el['param'];
        }
        $query .= implode(", ", $temp) . " ";

        // conclude query building
        $query .= 'OUTPUT inserted.* WHERE "' . $this->getIdColumn() . '" = :id';
        $queryParams = [];
        foreach ($data as $el) {
            $queryParams[$el['param']] = $el['value'];
        }
        $queryParams[':id'] = $this->getId();
        // prepare new statement every time as MSSQL driver has some issues
        // @link https://github.com/Microsoft/msphpsql/issues/60
        self::$st[static::class . '::' . __FUNCTION__] = $shard->prepareStatement($query, $queryParams);

        // bind ID and execute
        self::$st[static::class . '::' . __FUNCTION__]->execute();

        // refresh current data with data returned from the database
        $returnedData = self::$st[static::class . '::' . __FUNCTION__]->fetch();
        is_array($returnedData) and $this->merge($returnedData);

        return $this;
    }

    /**
     * @see \Maleficarum\Database\Data\Model\AbstractModel::delete()
     */
    public function delete(): \Maleficarum\Database\Data\Model\AbstractModel {
        // connect to shard if necessary
        $shard = $this->getDb()->fetchShard($this->getShardRoute());
        $shard->isConnected() or $shard->connect();

        // build the query
        $query = 'DELETE FROM "' . $this->getTable() . '" WHERE "' . $this->getIdColumn() . '" = :id';
        $queryParams = [':id' => $this->getId()];
        // prepare new statement every time as MSSQL driver has some issues
        // @link https://github.com/Microsoft/msphpsql/issues/60
        self::$st[static::class . '::' . __FUNCTION__] = $shard->prepareStatement($query, $queryParams);

        self::$st[static::class . '::' . __FUNCTION__]->execute();

        return $this;
    }

    /* ------------------------------------ Database\AbstractModel END --------------------------------- */
}

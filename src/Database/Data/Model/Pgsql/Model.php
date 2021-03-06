<?php
/**
 * This class provides CRUD implementation specific to postgresql database.
 */
declare (strict_types=1);

namespace Maleficarum\Database\Data\Model\Pgsql;

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
        $query .= ') VALUES (';

        // attach parameter names
        $temp = [];
        foreach ($data as $el) {
            $temp[] = $el['param'];
        }
        count($temp) and $query .= implode(', ', $temp);

        // conclude query building
        $query .= ')';

        // attach returning
        $query .= ' RETURNING *;';

        $queryParams = [];
        foreach ($data as $el) {
            $queryParams[$el['param']] = $el['value'];
        }
        $statement = $shard->prepareStatement($query, $queryParams, true);

        $statement->execute();

        // set new model ID if possible
        $this->merge($statement->fetch());

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
        $statement = $shard->prepareStatement($query, $queryParams, true);

        if (!$statement->execute() || $statement->rowCount() !== 1) {
            throw new \RuntimeException('No entity found - ID: ' . $this->getId() . '. ' . static::class . '::read()');
        }

        // fetch results and merge them into this object
        $result = $statement->fetch();
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
        $query .= 'WHERE "' . $this->getIdColumn() . '" = :id RETURNING *';

        $queryParams = [];
        foreach ($data as $el) {
            $queryParams[$el['param']] = $el['value'];
        }
        $queryParams[':id'] = $this->getId();

        $statement = $shard->prepareStatement($query, $queryParams, true);

        $statement->execute();

        // refresh current data with data returned from the database
        $this->merge($statement->fetch());

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
        $statement = $shard->prepareStatement($query, $queryParams, true);

        $statement->execute();

        return $this;
    }

    /* ------------------------------------ Database\AbstractModel END --------------------------------- */
}

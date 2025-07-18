<?php
/**
 * File containing the ezcDbSchemaCommonSqlWriter class.
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package DatabaseSchema
 * @version //autogentag//
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

/**
 * An abstract class that implements some common functionality required by
 * multiple database backends.
 *
 * @package DatabaseSchema
 * @version //autogentag//
 */
abstract class ezcDbSchemaCommonSqlWriter implements ezcDbSchemaDbWriter, ezcDbSchemaDiffDbWriter
{
    /**
     * Stores writer specfic context information
     *
     * @var array(mixed)
     */
    protected $context;

    /**
     * Stores a list of queries that is generated by the various Database writing backends.
     *
     * @var array(string)
     */
    protected $queries;

    /**
     * Stores the schema definition where the generators operate on.
     *
     * @var ezcDbSchema
     */
    protected $schema;

    /**
     * Stores the difference schema definition
     *
     * @var ezcDbSchemaDiff
     */
    protected $diffSchema;

    /**
     * Returns what type of schema writer this class implements.
     *
     * This method always returns ezcDbSchema::DATABASE
     *
     * @return int
     */
    public function getWriterType()
    {
        return ezcDbSchema::DATABASE;
    }

    /**
     * Creates the tables contained in $schema in the database that is related to $db
     *
     * This method takes the table definitions from $schema and will create the
     * tables according to this definition in the database that is references
     * by the $db handler. If tables with the same name as contained in the
     * definitions already exist they will be removed and recreated with the
     * new definition.
     *
     * @param ezcDbHandler $db
     * @param ezcDbSchema  $dbSchema
     */
    public function saveToDb( ezcDbHandler $db, ezcDbSchema $dbSchema )
    {
        $db->beginTransaction();
        foreach ( $this->convertToDDL( $dbSchema ) as $query )
        {
            if ( $this->isQueryAllowed( $db, $query ) )
            {
                $db->exec( $query );
            }
        }
        $db->commit();
    }

    /**
     * Returns an array with SQL DDL statements that creates the database definition in $dbSchema
     *
     * Converts the schema definition contained in $dbSchema to DDL SQL. This
     * SQL can be used to create tables in an existing database according to
     * the definition.  The SQL queries are returned as an array.
     *
     * @param ezcDbSchema $dbSchema
     * @return array(string)
     */
    public function convertToDDL( ezcDbSchema $dbSchema )
    {
        $this->schema = $dbSchema->getSchema();

        // reset queries
        $this->queries = array();
        $this->context = array();

        $this->generateSchemaAsSql();
        return $this->queries;
    }

    /**
     * Checks if the query is allowed.
     *
     * Perform testing if table exist for DROP TABLE query
     * to avoid stoping execution while try to drop not existent table.
     *
     * @param ezcDbHandler $db
     * @param string       $query
     *
     * @return boolean
     */
    public function isQueryAllowed( ezcDbHandler $db, $query )
    {
        return true;
    }

    /**
     * Creates SQL DDL statements from a schema definitin.
     *
     * Loops over the tables in the schema definition in $this->schema and
     * creates SQL SSL statements for this which it stores internally into the
     * $this->queries array.
     */
    protected function generateSchemaAsSql()
    {
        $prefix = ezcDbSchema::$options->tableNamePrefix;
        foreach ( $this->schema as $tableName => $tableDefinition )
        {
            $this->generateDropTableSql( $prefix . $tableName );
            $this->generateCreateTableSql( $prefix . $tableName, $tableDefinition );
        }
    }

    /**
     * Returns a "CREATE TABLE" SQL statement part for the table $tableName.
     *
     * @param string  $tableName
     * @return string
     */
    protected function generateCreateTableSqlStatement( $tableName )
    {
        return "CREATE TABLE $tableName";
    }

    /**
     * Adds a "create table" query for the table $tableName with definition $tableDefinition to the internal list of queries.
     *
     * @param string           $tableName
     * @param ezcDbSchemaTable $tableDefinition
     */
    protected function generateCreateTableSql( $tableName, ezcDbSchemaTable $tableDefinition )
    {
        $sql = $this->generateCreateTableSqlStatement( $tableName );

        $sql .= " (\n";

        // dump fields
        $fieldsSQL = array();

        foreach ( $tableDefinition->fields as $fieldName => $fieldDefinition )
        {
            $fieldsSQL[] = "\t" . $this->generateFieldSql( $fieldName, $fieldDefinition );
        }

        $sql .= join( ",\n", $fieldsSQL );

        $sql .= "\n)";

        $this->queries[] = $sql;

        // dump indexes
        foreach ( $tableDefinition->indexes as $indexName => $indexDefinition)
        {
            $fieldsSQL[] = $this->generateAddIndexSql( $tableName, $indexName, $indexDefinition );
        }
    }

    /**
     * Returns an appropriate default value for $type with $value.
     *
     * @param string $type
     * @param mixed  $value
     * @return string
     */
    protected function generateDefault( $type, $value )
    {
        switch ( $type )
        {
            case 'boolean':
                return ( $value && $value != 'false' ) ? 'true' : 'false';

            case 'integer':
                return (int) $value;

            case 'float':
            case 'decimal':
                return (float) $value;

            case 'timestamp':
                return (int) $value;

            default:
                return "'$value'";
        }
    }

    /**
     * Generates queries to upgrade an existing database with the changes stored in $this->diffSchema.
     *
     * This method generates queries to migrate a database to a new version
     * with the changes that are stored in the $this->diffSchema property. It
     * will call different subfunctions for the different types of changes, and
     * those functions will add queries to the internal list of queries that is
     * stored in $this->queries.
     */
    protected function generateDiffSchemaAsSql()
    {
        foreach ( $this->diffSchema->changedTables as $tableName => $tableDiff )
        {
            $this->generateDiffSchemaTableAsSql( $tableName, $tableDiff );
        }

        foreach ( $this->diffSchema->newTables as $tableName => $tableDef )
        {
            $this->generateCreateTableSql( $tableName, $tableDef );
        }

        foreach ( $this->diffSchema->removedTables as $tableName => $dummy )
        {
            $this->generateDropTableSql( $tableName );
        }
    }

    /**
     * Generates queries to upgrade a the table $tableName with the differences in $tableDiff.
     *
     * This method generates queries to migrate a table to a new version
     * with the changes that are stored in the $tableDiff property. It
     * will call different subfunctions for the different types of changes, and
     * those functions will add queries to the internal list of queries that is
     * stored in $this->queries.
     *
     * @param string $tableName
     * @param ezcDbSchemaTableDiff $tableDiff
     */
    protected function generateDiffSchemaTableAsSql( $tableName, ezcDbSchemaTableDiff $tableDiff )
    {
        foreach ( $tableDiff->removedIndexes as $indexName => $isRemoved )
        {
            if ( $isRemoved )
            {
                $this->generateDropIndexSql( $tableName, $indexName );
            }
        }

        foreach ( $tableDiff->changedIndexes as $indexName => $indexDefinition )
        {
            $this->generateDropIndexSql( $tableName, $indexName );
        }

        foreach ( $tableDiff->removedFields as $fieldName => $isRemoved )
        {
            if ( $isRemoved )
            {
                $this->generateDropFieldSql( $tableName, $fieldName );
            }
        }

        foreach ( $tableDiff->changedFields as $fieldName => $fieldDefinition )
        {
            $this->generateChangeFieldSql( $tableName, $fieldName, $fieldDefinition );
        }

        foreach ( $tableDiff->addedFields as $fieldName => $fieldDefinition )
        {
            $this->generateAddFieldSql( $tableName, $fieldName, $fieldDefinition );
        }

        foreach ( $tableDiff->changedIndexes as $indexName => $indexDefinition )
        {
            $this->generateAddIndexSql( $tableName, $indexName, $indexDefinition );
        }

        foreach ( $tableDiff->addedIndexes as $indexName => $indexDefinition )
        {
            $this->generateAddIndexSql( $tableName, $indexName, $indexDefinition );
        }
    }

    /**
     * Adds a "drop table" query for the table $tableName to the internal list of queries.
     *
     * @param string $tableName
     */
    protected abstract function generateDropTableSql( $tableName );

    /**
     * Returns a column definition for $fieldName with definition $fieldDefinition.
     *
     * @param  string           $fieldName
     * @param  ezcDbSchemaField $fieldDefinition
     * @return string
     */
    protected abstract function generateFieldSql( $fieldName, ezcDbSchemaField $fieldDefinition );

    /**
     * Adds a "alter table" query to add the field $fieldName to $tableName with the definition $fieldDefinition.
     *
     * @param string           $tableName
     * @param string           $fieldName
     * @param ezcDbSchemaField $fieldDefinition
     */
    protected abstract function generateAddFieldSql( $tableName, $fieldName, ezcDbSchemaField $fieldDefinition );

    /**
     * Adds a "alter table" query to change the field $fieldName to $tableName with the definition $fieldDefinition.
     *
     * @param string           $tableName
     * @param string           $fieldName
     * @param ezcDbSchemaField $fieldDefinition
     */
    protected abstract function generateChangeFieldSql( $tableName, $fieldName, ezcDbSchemaField $fieldDefinition );

    /**
     * Adds a "alter table" query to drop the field $fieldName from $tableName.
     *
     * @param string $tableName
     * @param string $fieldName
     */
    protected abstract function generateDropFieldSql( $tableName, $fieldName );


    /**
     * Adds a "alter table" query to add the index $indexName to the table $tableName with definition $indexDefinition to the internal list of queries
     *
     * @param string           $tableName
     * @param string           $indexName
     * @param ezcDbSchemaIndex $indexDefinition
     */
    protected abstract function generateAddIndexSql( $tableName, $indexName, ezcDbSchemaIndex $indexDefinition );

    /**
     * Adds a "alter table" query to remote the index $indexName from the table $tableName to the internal list of queries.
     *
     * @param string           $tableName
     * @param string           $indexName
     */
    protected abstract function generateDropIndexSql( $tableName, $indexName );
}
?>

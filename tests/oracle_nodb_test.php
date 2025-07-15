<?php
/**
 * File containing test code for the DatabaseSchema component.
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


class ezcDatabaseSchemaOracleNoDbTest extends ezcTestCase
{
    public function testGeneratedSequenceAndTriggerNameMax30Chars()
    {
        $fields = array();
        $fields["veryverylong_id"] = new ezcDbSchemaField( "integer", 10, true, null, true, false );

        $indexes = array();
        $indexes["primary"] = new ezcDbSchemaIndex( array( "veryverylong_id" => new ezcDbSchemaIndexField() ) );

        $tables = array( "veryverylongtablename" => new ezcDbSchemaTable( $fields, $indexes ) );

        $schema = new ezcDbSchema( $tables );

        $oracleWriter = new ezcDbSchemaOracleWriter();
        $ddl = $oracleWriter->convertToDDL( $schema );

        $this->assertEquals( 6, count( $ddl ), "Invalid DDL query stack size. Queries might be shifted and following assertions are invalid!" );
        for ( $i = 0; $i < count( $ddl ); $i++ )
        {
            $this->assertStringNotMatchesFormat( '%sveryverylongtablename_veryverylong_id_seq%s', $ddl[$i] );
            $this->assertStringNotMatchesFormat( '%sveryverylongtablename_veryverylong_id_trg%s', $ddl[$i] );
        }

        $this->assertStringMatchesFormat( '%sveryverylongtab_veryverylo_seq%s', $ddl[2] );
        $this->assertStringMatchesFormat( '%sveryverylongtab_veryverylo_seq%s', $ddl[3] );
        $this->assertStringMatchesFormat( '%sveryverylongtab_veryverylo_trg%s', $ddl[3] );
        $this->assertEquals( 30, strlen( 'veryverylongtab_veryverylo_seq' ) );
    }

    public function testGeneratedConstraintNameMax30Chars()
    {
        $fields = array();
        $fields["oneid"] = new ezcDbSchemaField( "integer", 10, true, null, true, false );

        $indexes = array();
        $indexes["primary"] = new ezcDbSchemaIndex( array( "oneid" => new ezcDbSchemaIndexField() ) );

        $tables = array( "ultraultraveryverylongtablename" => new ezcDbSchemaTable( $fields, $indexes ) );

        $schema = new ezcDbSchema( $tables );

        $oracleWriter = new ezcDbSchemaOracleWriter();
        $ddl = $oracleWriter->convertToDDL( $schema );

        $this->assertEquals( 6, count( $ddl ), "Invalid DDL query stack size. Queries might be shifted and following assertions are invalid!" );
        for ( $i = 0; $i < count( $ddl ); $i++ )
        {
            $this->assertStringNotMatchesFormat( '%sultraultraultraveryverylongtablename_pkey%s', $ddl[$i] );
        }

        $this->assertStringMatchesFormat( '%sultraultraveryverylongtab_pkey%s', $ddl[4] );
        $this->assertEquals( 30, strlen( 'ultraultraveryverylongtab_pkey' ) );
    }

    static public function suite()
    {
        return new \PHPUnit\Framework\TestSuite( 'ezcDatabaseSchemaOracleNoDbTest' );
    }
}
?>

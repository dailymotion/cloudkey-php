#!/usr/bin/env phpunit
<?php

$username = 'test';
$password = 'test';
$base_url = 'http://api.dmcloud.net';

@include 'local_config.php';

require_once 'PHPUnit/Framework.php';
require_once 'CloudKey.php';

class AllTests
{
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite();
        $suite->addTestSuite('CloudKey_MediaTest');
        return $suite;
    }
}

class CloudKey_UserTest extends PHPUnit_Framework_TestCase
{
    protected
        $user = null;

    public function setUp()
    {
        global $username, $password, $base_url;
        $this->user = new CloudKey_User($username, $password, $base_url);
    }

    public function testWhoami()
    {
        global $username;
        $res = $this->user->whoami();
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertObjectHasAttribute('username', $res);
        $this->assertEquals($res->username, $username);
    }
}

class CloudKey_MediaTest extends PHPUnit_Framework_TestCase
{
    protected
        $media = null;

    public function setUp()
    {
        global $username, $password, $base_url;
        $this->media = new CloudKey_Media($username, $password, $base_url);
        $this->media->reset();
    }

    public function tearDown()
    {
        $this->media->reset();
    }

    //
    // MEDIA CRUD
    //

    public function testCreate()
    {
        $res = $this->media->create();
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertEquals(strlen($res->id), 24);
    }

    public function testInfo()
    {
        $media = $this->media->create();
        $res = $this->media->info(array('id' => $media->id));
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertEquals(strlen($res->id), 24);
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testInfoNotFound()
    {
        $this->media->info(array('id' => '1b87186c84e1b015a0000000'));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testInfoInvalidMediaId()
    {
        $this->media->info(array('id' => 'b87186c84e1b015a0000000'));
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testDelete()
    {
        $media = $this->media->create();
        $res = $this->media->delete(array('id' => $media->id));
        $this->assertNull($res);

        // Should throw CloudKey_NotFoundException
        $this->media->info(array('id' => $media->id));
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testDeleteNotFound()
    {
        $this->media->delete(array('id' => '1b87186c84e1b015a0000000'));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testDeleteInvalidMediaId()
    {
        $this->media->delete(array('id' => 'b87186c84e1b015a0000000'));
    }

    //
    // META
    //

    public function testSetMeta()
    {
        $media = $this->media->create();
        $res = $this->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'my_value'));
        $this->assertNull($res);

        $res = $this->media->get_meta(array('id' => $media->id, 'key' => 'mykey'));
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('value', $res);
        $this->assertEquals($res->value, 'my_value');
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testSetMetaMediaNotFound()
    {
        $media = $this->media->create();
        $this->media->set_meta(array('id' => '1b87186c84e1b015a0000000', 'key' => 'mykey', 'value' => 'my_value'));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testSetMetaInvalidMediaId()
    {
        $media = $this->media->create();
        $this->media->set_meta(array('id' => 'b87186c84e1b015a0000000', 'key' => 'mykey', 'value' => 'my_value'));
    }

    /**
     * @expectedException CloudKey_MissingParamException
     */
    public function testSetMetaMissingArgKey()
    {
        $media = $this->media->create();
        $this->media->set_meta(array('id' => $media->id, 'key' => 'mykey'));
    }

    /**
     * @expectedException CloudKey_MissingParamException
     */
    public function testSetMetaMissingArgValue()
    {
        $media = $this->media->create();
        $this->media->set_meta(array('id' => $media->id, 'value' => 'my_value'));
    }

    /**
     * @expectedException CloudKey_MissingParamException
     */
    public function testSetMetaMissingArgKeyAndValue()
    {
        $media = $this->media->create();
        $this->media->set_meta(array('id' => $media->id));
    }

    public function testSetMetaUpdate()
    {
        $media = $this->media->create();

        $res = $this->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'value'));
        $this->assertNull($res);

        $res = $this->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'new_value'));
        $this->assertNull($res);

        $res = $this->media->get_meta(array('id' => $media->id, 'key' => 'mykey'));
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('value', $res);
        $this->assertEquals($res->value, 'new_value');
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testGetMetaMediaNotFound()
    {
        $media = $this->media->create();
        $this->media->get_meta(array('id' => $media->id, 'key' => 'not_found_key'));
    }

    public function testListMeta()
    {
        $media = $this->media->create();

        $res = $this->media->list_meta(array('id' => $media->id));
        $this->assertType('object', $res);

        for ($i = 0; $i < 10; $i++)
        {
            $this->media->set_meta(array('id' => $media->id, 'key' => 'mykey-' . $id, 'value' => 'a value'));
        }

        $res = $this->media->list_meta(array('id' => $media->id));
        $this->assertType('object', $res);

        for ($i = 0; $i < 10; $i++)
        {
            $this->assertObjectHasAttribute('mykey-' . $id, $res);
        }
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testRemoveMeta()
    {
        $media = $this->media->create();
        $this->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'value'));
        $res = $this->media->remove_meta(array('id' => $media->id, 'key' => 'mykey'));
        $this->assertNull($res);
        $this->media->get_meta(array('id' => $media->id, 'key' => 'mykey'));
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testRemoveMetaNotFound()
    {
        $media = $this->media->create();
        $this->media->remove_meta(array('id' => $media->id, 'key' => 'mykey'));
    }

    //
    // ASSETS
    //

    // TODO
}

#!/usr/bin/env phpunit
<?php

$username = null;
$password = null;
$root_username = null;
$root_password = null;
$switch_user = null;

@include 'local_config.php';

require_once 'PHPUnit/Framework.php';
require_once 'CloudKey.php';

class AllTests
{
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite();
        $suite->addTestSuite('CloudKeyTest');
        $suite->addTestSuite('CloudKey_UserTest');
        $suite->addTestSuite('CloudKey_FileTest');
        $suite->addTestSuite('CloudKey_MediaTest');
        $suite->addTestSuite('CloudKey_MediaMetaTest');
        $suite->addTestSuite('CloudKey_MediaAssetTest');
        return $suite;
    }
}

class CloudKeyTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        global $username, $password, $root_username, $root_password, $switch_user;
        if (!$username || !$password || !$root_username || !$root_password || !$switch_user)
        {
            $this->markTestSkipped('Missing test configuration');
        }
    }

    /**
     * @expectedException CloudKey_AuthorizationRequiredException
     */
    public function testAnonymous()
    {
        $cloudkey = new CloudKey(null, null);
        $cloudkey->user->whoami();
    }

    public function testNormalUser()
    {
        global $username, $password;
        $cloudkey = new CloudKey($username, $password);
        $res = $cloudkey->user->whoami();
        $this->assertEquals($res->username, $username);
    }

    public function testNormalUserSu()
    {
        global $username, $password, $switch_user;
        $cloudkey = new CloudKey($username, $password);
        $cloudkey->act_as_user($switch_user);
        $res = $cloudkey->user->whoami();
        $this->assertEquals($res->username, $username);
    }

    public function testSuperUserSu()
    {
        global $root_username, $root_password, $switch_user;
        $cloudkey = new CloudKey($root_username, $root_password);
        $cloudkey->act_as_user($switch_user);
        $res = $cloudkey->user->whoami();
        $this->assertEquals($switch_user, $res->username);
    }

    /**
     * @expectedException CloudKey_AuthenticationFailedException
     */
    public function testSuperUserSuWrongUser()
    {
        global $root_username, $root_password, $switch_user;
        $cloudkey = new CloudKey($root_username, $root_password);
        $cloudkey->act_as_user('unexisting_user');
        $res = $cloudkey->user->whoami();
    }
}

class CloudKey_UserTest extends PHPUnit_Framework_TestCase
{
    protected
        $cloudkey = null;

    protected function setUp()
    {
        global $username, $password;
        if (!$username || !$password)
        {
            $this->markTestSkipped('Missing test configuration');
            return;
        }
        $this->cloudkey = new CloudKey($username, $password);
    }

    public function testWhoami()
    {
        global $username;
        $res = $this->cloudkey->user->whoami();
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertObjectHasAttribute('username', $res);
        $this->assertEquals($res->username, $username);
    }
}

class CloudKey_FileTest extends PHPUnit_Framework_TestCase
{
    protected
        $cloudkey = null;

    protected function setUp()
    {
        global $username, $password;
        if (!$username || !$password)
        {
            $this->markTestSkipped('Missing test configuration');
            return;
        }
        if (!is_file('.fixtures/video.3gp'))
        {
            $this->markTestSkipped('Missing fixtures, please do `git submodule init; git submodule update\'');
            return;
        }
        $this->cloudkey = new CloudKey($username, $password);
        $this->cloudkey->media->reset();
    }

    public function tearDown()
    {
        if ($this->cloudkey)
        {
            $this->cloudkey->media->reset();
        }
    }

    public function testUpload()
    {
        $res = $this->cloudkey->file->upload();
        $this->assertObjectHasAttribute('url', $res);
    }

    public function testUploadTarget()
    {
        $target = 'http://www.example.com/myform';
        $res = $this->cloudkey->file->upload(array('target' => $target));
        $this->assertObjectHasAttribute('url', $res);
        parse_str(parse_url($res->url, PHP_URL_QUERY), $qs);
        $this->assertArrayHasKey('seal', $qs);
        $this->assertArrayHasKey('uuid', $qs);
        $this->assertArrayHasKey('target', $qs);
        $this->assertEquals($qs['target'], $target);
    }

    public function testUploadFile()
    {
        $media = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $this->assertObjectHasAttribute('size', $media);
        $this->assertObjectHasAttribute('name', $media);
        $this->assertObjectHasAttribute('url', $media);
        $this->assertObjectHasAttribute('hash', $media);
        $this->assertObjectHasAttribute('seal', $media);
        $this->assertEquals($media->size, filesize('.fixtures/video.3gp'));
        $this->assertEquals($media->name, 'video');
        $this->assertEquals($media->hash, sha1_file('.fixtures/video.3gp'));
    }
}

class CloudKey_MediaTestBase extends PHPUnit_Framework_TestCase
{
    protected
        $cloudkey = null;

    protected function setUp()
    {
        global $username, $password;
        if (!$username || !$password)
        {
            $this->markTestSkipped('Missing test configuration');
            return;
        }
        $this->cloudkey = new CloudKey($username, $password);
        $this->cloudkey->media->reset();
    }

    public function tearDown()
    {
        if ($this->cloudkey)
        {
            $this->cloudkey->media->reset();
        }
    }
}

class CloudKey_MediaTest extends CloudKey_MediaTestBase
{
    public function testCreate()
    {
        $res = $this->cloudkey->media->create();
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertEquals(strlen($res->id), 24);
    }

    public function testInfo()
    {
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->info(array('id' => $media->id));
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertEquals(strlen($res->id), 24);
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testInfoNotFound()
    {
        $this->cloudkey->media->info(array('id' => '1b87186c84e1b015a0000000'));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testInfoInvalidMediaId()
    {
        $this->cloudkey->media->info(array('id' => 'b87186c84e1b015a0000000'));
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testDelete()
    {
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->delete(array('id' => $media->id));
        $this->assertNull($res);

        // Should throw CloudKey_NotFoundException
        $this->cloudkey->media->info(array('id' => $media->id));
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testDeleteNotFound()
    {
        $this->cloudkey->media->delete(array('id' => '1b87186c84e1b015a0000000'));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testDeleteInvalidMediaId()
    {
        $this->cloudkey->media->delete(array('id' => 'b87186c84e1b015a0000000'));
    }
}

class CloudKey_MediaMetaTest extends CloudKey_MediaTestBase
{
    public function testSetMeta()
    {
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'my_value'));
        $this->assertNull($res);

        $res = $this->cloudkey->media->get_meta(array('id' => $media->id, 'key' => 'mykey'));
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('value', $res);
        $this->assertEquals($res->value, 'my_value');
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testSetMetaMediaNotFound()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => '1b87186c84e1b015a0000000', 'key' => 'mykey', 'value' => 'my_value'));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testSetMetaInvalidMediaId()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => 'b87186c84e1b015a0000000', 'key' => 'mykey', 'value' => 'my_value'));
    }

    /**
     * @expectedException CloudKey_MissingParamException
     */
    public function testSetMetaMissingArgKey()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id, 'key' => 'mykey'));
    }

    /**
     * @expectedException CloudKey_MissingParamException
     */
    public function testSetMetaMissingArgValue()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id, 'value' => 'my_value'));
    }

    /**
     * @expectedException CloudKey_MissingParamException
     */
    public function testSetMetaMissingArgKeyAndValue()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id));
    }

    public function testSetMetaUpdate()
    {
        $media = $this->cloudkey->media->create();

        $res = $this->cloudkey->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'value'));
        $this->assertNull($res);

        $res = $this->cloudkey->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'new_value'));
        $this->assertNull($res);

        $res = $this->cloudkey->media->get_meta(array('id' => $media->id, 'key' => 'mykey'));
        $this->assertType('object', $res);
        $this->assertObjectHasAttribute('value', $res);
        $this->assertEquals($res->value, 'new_value');
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testGetMetaMediaNotFound()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->get_meta(array('id' => $media->id, 'key' => 'not_found_key'));
    }

    public function testListMeta()
    {
        $media = $this->cloudkey->media->create();

        $res = $this->cloudkey->media->list_meta(array('id' => $media->id));
        $this->assertType('object', $res);

        for ($i = 0; $i < 10; $i++)
        {
            $this->cloudkey->media->set_meta(array('id' => $media->id, 'key' => 'mykey-' . $id, 'value' => 'a value'));
        }

        $res = $this->cloudkey->media->list_meta(array('id' => $media->id));
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
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id, 'key' => 'mykey', 'value' => 'value'));
        $res = $this->cloudkey->media->remove_meta(array('id' => $media->id, 'key' => 'mykey'));
        $this->assertNull($res);
        $this->cloudkey->media->get_meta(array('id' => $media->id, 'key' => 'mykey'));
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testRemoveMetaNotFound()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->remove_meta(array('id' => $media->id, 'key' => 'mykey'));
    }
}

class CloudKey_MediaAssetTest extends CloudKey_MediaTestBase
{
    public function waitAssetReady($media_id, $asset_name, $wait = 60)
    {
        while ($wait--)
        {
            $asset = $this->cloudkey->media->get_asset(array('id' => $media_id, 'preset' => $asset_name));
            if ($asset->status !== 'ready')
            {
                if ($asset->status === 'error')
                {
                    return false;
                }
                sleep(1);
                continue;
            }
            return true;
        }
        throw new Exception('timeout exceeded');
    }

    public function testSetAsset()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->set_asset(array('id' => $media->id, 'preset' => 'source', 'url' => $file->url));
        $this->assertNull($res);

        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'source'));
        $this->assertObjectHasAttribute('status', $res);
        $this->assertContains($res->status, array('pending', 'processing'));

        $res = $this->waitAssetReady($media->id, 'source');
        $this->assertTrue($res);
        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'source'));
        $this->assertObjectHasAttribute('status', $res);
        $this->assertEquals($res->status, 'ready');
    }

    /**
    * @expectedException CloudKey_NotFoundException
     */
    public function testRemoveAsset()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->set_asset(array('id' => $media->id, 'preset' => 'source', 'url' => $file->url));
        $res = $this->waitAssetReady($media->id, 'source');
        $this->assertTrue($res);

        $res = $this->cloudkey->media->remove_asset(array('id' => $media->id, 'preset' => 'source'));
        $this->assertNull($res);

        $wait = 10;
        while($wait--)
        {
            // Will throw CloudKey_NotFoundException when effectively removed
            $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'source'));
            sleep(1);
        }
    }

    public function testProcessAsset()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_asset(array('id' => $media->id, 'preset' => 'source', 'url' => $file->url));

        // Don't wait for source asset to be ready, the API should handle the dependancy by itself
        $res = $this->cloudkey->media->process_asset(array('id' => $media->id, 'preset' => 'flv_h263_mp3'));
        $this->assertNull($res);
        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'flv_h263_mp3'));
        $this->assertEquals($res->status, 'pending');

        $res = $this->cloudkey->media->process_asset(array('id' => $media->id, 'preset' => 'mp4_h264_aac'));
        $this->assertNull($res);
        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'mp4_h264_aac'));
        $this->assertEquals($res->status, 'pending');

        $res = $this->waitAssetReady($media->id, 'flv_h263_mp3');
        $this->assertTrue($res);
        $res = $this->waitAssetReady($media->id, 'mp4_h264_aac');
        $this->assertTrue($res);

        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'flv_h263_mp3'));
        $this->assertEquals($res->status, 'ready');
        $this->assertObjectHasAttribute('duration', $res);
        $this->assertObjectHasAttribute('filesize', $res);
        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'preset' => 'flv_h263_mp3'));
        $this->assertEquals($res->status, 'ready');
        $this->assertObjectHasAttribute('duration', $res);
        $this->assertObjectHasAttribute('filesize', $res);
    }
}

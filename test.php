<?php

$base_url = null;

require_once 'config.php';

require_once 'vendor/autoload.php';
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
        $suite->addTestSuite('CloudKey_MediaStreamUrlTest');
        $suite->addTestSuite('CloudKey_MediaEmbedUrlTest');
        return $suite;
    }
}

class CloudKeyTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        global $user_id, $api_key;
        if (!$user_id || !$api_key)
        {
            $this->markTestSkipped('Missing test configuration');
        }
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testAnonymous()
    {
        global $base_url;
        $cloudkey = new CloudKey(null, null, $base_url);
        $cloudkey->user->info(array('fields' => array('id')));
    }

    public function testNormalUser()
    {
        global $user_id, $api_key, $base_url;
        $cloudkey = new CloudKey($user_id, $api_key, $base_url);
        $res = $cloudkey->user->info(array('fields' => array('id')));
        $this->assertEquals($res->id, $user_id);
    }
}

class CloudKey_UserTest extends PHPUnit_Framework_TestCase
{
    protected
        $cloudkey = null;

    protected function setUp()
    {
        global $user_id, $api_key, $base_url;
        if (!$user_id || !$api_key)
        {
            $this->markTestSkipped('Missing test configuration');
            return;
        }
        $this->cloudkey = new CloudKey($user_id, $api_key, $base_url);
    }

    public function testWhoami()
    {
        global $user_id;
        $res = $this->cloudkey->user->info(array('fields' => array('id', 'username')));
        $this->assertInternalType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertObjectHasAttribute('username', $res);
        $this->assertEquals($res->id, $user_id);
    }
}

class CloudKey_FileTest extends PHPUnit_Framework_TestCase
{
    protected
        $cloudkey = null;

    protected function setUp()
    {
        global $user_id, $api_key, $base_url;
        if (!$user_id || !$api_key)
        {
            $this->markTestSkipped('Missing test configuration');
            return;
        }
        if (!is_file('.fixtures/video.3gp'))
        {
            $this->markTestSkipped('Missing fixtures, please do `git submodule init; git submodule update\'');
            return;
        }
        $this->cloudkey = new CloudKey($user_id, $api_key, $base_url);
        $this->cloudkey->system->reset(array('object' => 'media'));
    }

    public function tearDown()
    {
        if ($this->cloudkey)
        {
            $this->cloudkey->system->reset(array('object' => 'media'));
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
        global $user_id, $api_key, $base_url;
        if (!$user_id || !$api_key)
        {
            $this->markTestSkipped('Missing test configuration');
            return;
        }
        $this->cloudkey = new CloudKey($user_id, $api_key, $base_url);
        $this->cloudkey->system->reset(array('object' => 'media'));
    }

    public function tearDown()
    {
        if ($this->cloudkey)
        {
            $this->cloudkey->system->reset(array('object' => 'media'));
        }
    }

    public function waitAssetReady($media_id, $asset_name, $wait = 60)
    {
        while ($wait--)
        {
            $asset = $this->cloudkey->media->get_assets(array('id' => $media_id, 'assets_names' => array($asset_name)));
            if ($asset->$asset_name->status !== 'ready')
            {
                if ($asset->$asset_name->status === 'error')
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
}

class CloudKey_MediaTest extends CloudKey_MediaTestBase
{
    public function testCreate()
    {
        $res = $this->cloudkey->media->create();
        $this->assertEquals(strlen($res->id), 24);
    }

    public function testInfo()
    {
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->info(array('id' => $media->id, 'fields' => array('id')));
        $this->assertInternalType('object', $res);
        $this->assertObjectHasAttribute('id', $res);
        $this->assertEquals(strlen($res->id), 24);
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testInfoNotFound()
    {
        $this->cloudkey->media->info(array('id' => '1b87186c84e1b015a0000000', 'fields' => array('id')));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testInfoInvalidMediaId()
    {
        $this->cloudkey->media->info(array('id' => 'b87186c84e1b015a0000000', 'fields' => array('id')));
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
        $this->cloudkey->media->info(array('id' => $media->id, 'fields' => array('id')));
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
        $res = $this->cloudkey->media->set_meta(array('id' => $media->id, 'meta' => array('mykey' => 'my_value')));
        $this->assertNull($res);

        $res = $this->cloudkey->media->get_meta(array('id' => $media->id, 'keys' => array('mykey')));
        $this->assertObjectHasAttribute('mykey', $res);
        $this->assertEquals($res->mykey, 'my_value');
    }

    /**
     * @expectedException CloudKey_NotFoundException
     */
    public function testSetMetaMediaNotFound()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => '1b87186c84e1b015a0000000', 'meta' => array('mykey' => 'my_value')));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testSetMetaInvalidMediaId()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => 'b87186c84e1b015a0000000', 'meta' => array('mykey' => 'my_value')));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testSetMetaMissingArg()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id));
    }

    /**
     * @expectedException CloudKey_InvalidParamException
     */
    public function testSetMetaMissingArgKeyAndValue()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id));
    }

    public function testSetMetaUpdate()
    {
        $media = $this->cloudkey->media->create();

        $res = $this->cloudkey->media->set_meta(array('id' => $media->id, 'meta' => array('mykey' => 'value')));
        $this->assertNull($res);

        $res = $this->cloudkey->media->set_meta(array('id' => $media->id, 'meta' => array('mykey' => 'new_value')));
        $this->assertNull($res);

        $res = $this->cloudkey->media->get_meta(array('id' => $media->id, 'keys' => array('mykey')));
        $this->assertObjectHasAttribute('mykey', $res);
        $this->assertEquals($res->mykey, 'new_value');
    }

    public function testGetMetaMediaNotFound()
    {
        $media = $this->cloudkey->media->create();
        $res = $this->cloudkey->media->get_meta(array('id' => $media->id, 'keys' => array('not_found_key')));
        $this->assertNull($res->not_found_key);
    }

    public function testRemoveMeta()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_meta(array('id' => $media->id, 'meta' => array('mykey' => 'myvalue')));
        $this->cloudkey->media->remove_meta(array('id' => $media->id, 'keys' => array('mykey')));
        $res = $this->cloudkey->media->get_meta(array('id' => $media->id, 'keys' => array('mykey')));
        $this->assertNull($res->mykey);
    }

    public function testRemoveMetaNotFound()
    {
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->remove_meta(array('id' => $media->id, 'keys' => array('mykey')));
    }
}

class CloudKey_MediaAssetTest extends CloudKey_MediaTestBase
{
    public function testSetAsset()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_assets(array('id' => $media->id, 'assets' => array(array('name' => 'source', 'url' => $file->url))));

        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => array('source')));
        $this->assertObjectHasAttribute('source', $res);
        $this->assertObjectHasAttribute('status', $res->source);
        $this->assertContains($res->source->status, array('pending', 'processing'));

        $res = $this->waitAssetReady($media->id, 'source');
        $this->assertTrue($res);
        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => array('source')));
        $this->assertObjectHasAttribute('source', $res);
        $this->assertObjectHasAttribute('status', $res->source);
        $this->assertEquals($res->source->status, 'ready');
    }

    public function testRemoveAsset()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $media = $this->cloudkey->media->create();
        $this->cloudkey->media->set_assets(array('id' => $media->id, 'assets' => array(array('name' => 'source', 'url' => $file->url))));
        $res = $this->waitAssetReady($media->id, 'source');
        $this->assertTrue($res);

        $this->cloudkey->media->remove_assets(array('id' => $media->id, 'assets_names' => array('source')));

        $wait = 10;
        while($wait--)
        {
            $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => array('source')));
            if (!isset($res->source))
            {
                return;
            }
            sleep(1);
        }
        $this->fail('The source asset is still there');
    }

    public function testProcessAsset()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $media = $this->cloudkey->media->create();
        // Don't wait for source asset to be ready, the API should handle the dependancy by itself
        $this->cloudkey->media->set_assets(array
        (
            'id' => $media->id,
            'assets' => array
            (
                array('name' => 'source', 'url' => $file->url),
                array('name' => 'flv_h263_mp3', 'action' => 'transcode'),
                array('name' => 'mp4_h264_aac', 'action' => 'transcode'),
            )
        ));

        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => array('source', 'flv_h263_mp3', 'mp4_h264_aac')));
        $this->assertObjectHasAttribute('source', $res);
        $this->assertEquals($res->source->status, 'pending');
        $this->assertObjectHasAttribute('flv_h263_mp3', $res);
        $this->assertEquals($res->flv_h263_mp3->status, 'pending');
        $this->assertObjectHasAttribute('mp4_h264_aac', $res);
        $this->assertEquals($res->mp4_h264_aac->status, 'pending');

        $res = $this->waitAssetReady($media->id, 'flv_h263_mp3');
        $this->assertTrue($res);
        $res = $this->waitAssetReady($media->id, 'mp4_h264_aac');
        $this->assertTrue($res);

        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => array('flv_h263_mp3', 'mp4_h264_aac')));
        $this->assertObjectHasAttribute('flv_h263_mp3', $res);
        $this->assertEquals($res->flv_h263_mp3->status, 'ready');
        $this->assertObjectHasAttribute('duration', $res->flv_h263_mp3);
        $this->assertObjectHasAttribute('file_size', $res->flv_h263_mp3);
        $this->assertObjectHasAttribute('mp4_h264_aac', $res);
        $this->assertEquals($res->mp4_h264_aac->status, 'ready');
        $this->assertObjectHasAttribute('duration', $res->mp4_h264_aac);
        $this->assertObjectHasAttribute('file_size', $res->mp4_h264_aac);
    }
}

class CloudKey_MediaPublishTest extends CloudKey_MediaTestBase
{
    public function testPublish()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('flv_h263_mp3', 'mp4_h264_aac', 'flv_h263_mp3_ld', 'jpeg_thumbnail_small', 'jpeg_thumbnail_medium', 'jpeg_thumbnail_large');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => $assets));
        foreach ($assets as $asset)
        {
            $this->assertObjectHasAttribute($asset, $res);
            $this->assertEquals($res->$asset->status, 'pending');
        }

        foreach ($assets as $asset)
        {
            $this->waitAssetReady($media->id, $asset);
        }

        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => $assets));
        foreach ($assets as $asset)
        {
            $this->assertObjectHasAttribute($asset, $res);
            $this->assertEquals($res->$asset->status, 'ready');
            $this->assertObjectHasAttribute('status', $res->$asset);
            $this->assertObjectHasAttribute('duration', $res->$asset);
            $this->assertObjectHasAttribute('filesize', $res->$asset);
        }
    }

    public function testPublishSourceError()
    {
        $assets = array('flv_h263_mp3', 'mp4_h264_aac', 'flv_h263_mp3_ld');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => 'http://localhost/'));

        foreach ($assets as $asset)
        {
            $res = $this->waitAssetReady($media->id, $asset);
            $this->assertFalse($res);
        }

        $res = $this->cloudkey->media->get_assets(array('id' => $media->id, 'assets_names' => $assets));

        foreach ($assets as $asset)
        {
            $this->assertEquals($res->$asset->status, 'error');
        }
    }

    public function testPublishUrlError()
    {
        $file = $this->cloudkey->file->upload_file('.fixtures/broken.avi');
        $assets = array('flv_h263_mp3', 'mp4_h264_aac', 'flv_h263_mp3_ld');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        foreach ($assets as $asset)
        {
            $res = $this->waitAssetReady($media->id, $asset);
            $this->assertFalse($res);
        }

        $res = $this->cloudkey->media->get_asset(array('id' => $media->id, 'assets_names' => $assets));

        foreach ($assets as $asset)
        {
            $this->assertEquals($res->$asset->status, 'error');
        }
    }
}

class CloudKey_MediaStreamUrlTest extends CloudKey_MediaTestBase
{
    public function testGetStreamUrl()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'jpeg_thumbnail_medium'));
        $res_test = sprintf('http://static.dmcloud.net/%s/%s/jpeg_thumbnail_medium.jpeg', $user_id, $media->id);
        $this->assertEquals($res, $res_test);

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac'));
        $res_test = sprintf('/route/%s/%s/mp4_h264_aac.mp4?', $user_id, $media->id);
        $this->assertContains($res_test, $res);
    }

    public function testGetStreamUrl_version()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $version = 3546546546;

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'jpeg_thumbnail_medium', 'version' => $version));
        $res_test = sprintf('http://static.dmcloud.net/%s/%s/jpeg_thumbnail_medium-%s.jpeg', $user_id, $media->id, $version);
        $this->assertEquals($res, $res_test);

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac', 'version' => $version));
        $res_test = sprintf('/route/%s/%s/mp4_h264_aac-%s.mp4?', $user_id, $media->id, $version);
        $this->assertContains($res_test, $res);
    }

    public function testGetStreamUrl_download_no_filename()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $download = true;
        $filename = null;

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'jpeg_thumbnail_medium', 'download' => $download, 'filename' => $filename));
        $res_test = sprintf('http://static.dmcloud.net/%s/%s/jpeg_thumbnail_medium.jpeg', $user_id, $media->id);
        $this->assertEquals($res, $res_test);

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac', 'download' => $download, 'filename' => $filename));
        $res_test = sprintf('/route/http/%s/%s/mp4_h264_aac.mp4?', $user_id, $media->id);
        $this->assertContains($res_test, $res);
    }

    public function testGetStreamUrl_filename_no_download()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $download = false;
        $filename = 'test_filename.mp4';

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'jpeg_thumbnail_medium', 'download' => $download, 'filename' => $filename));
        $res_test = sprintf('http://static.dmcloud.net/%s/%s/jpeg_thumbnail_medium.jpeg', $user_id, $media->id);
        $this->assertEquals($res, $res_test);

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac', 'download' => $download, 'filename' => $filename));
        $res_test = sprintf('/route/http/%s/%s/mp4_h264_aac.mp4?filename=%s', $user_id, $media->id, urlencode(utf8_encode($filename)));
        $this->assertContains($res_test, $res);
    }

    public function testGetStreamUrl_no_filename_no_download()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $download = false;
        $filename = '';

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'jpeg_thumbnail_medium', 'download' => $download, 'filename' => $filename));
        $res_test = sprintf('http://static.dmcloud.net/%s/%s/jpeg_thumbnail_medium.jpeg', $user_id, $media->id);
        $this->assertEquals($res, $res_test);

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac', 'download' => $download, 'filename' => $filename));
        $res_test = sprintf('/route/%s/%s/mp4_h264_aac.mp4?', $user_id, $media->id);
        $this->assertContains($res_test, $res);
    }

    /**
     * @expectedException CloudKey_InvalidMethodException
     */
    public function testGetStreamUrl_bad_protocol()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $protocol = 'bad';

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac', 'protocol' => $protocol));
    }

    public function testGetStreamUrl_protocol_http()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $protocol = 'http';

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'jpeg_thumbnail_medium', 'protocol' => $protocol));
        $res_test = sprintf('http://static.dmcloud.net/%s/%s/jpeg_thumbnail_medium.jpeg', $user_id, $media->id);
        $this->assertEquals($res, $res_test);

        $res = $this->cloudkey->media->get_stream_url(array('id' => $media->id, 'asset_name' => 'mp4_h264_aac', 'protocol' => $protocol));
        $res_test = sprintf('/route/%s/%s/%s/mp4_h264_aac.mp4?', $protocol, $user_id, $media->id);
        $this->assertContains($res_test, $res);
    }

}

class CloudKey_MediaEmbedUrlTest extends CloudKey_MediaTestBase
{
    public function testGetEmbedUrl()
    {
        global $user_id, $api_key, $base_url;

        $file = $this->cloudkey->file->upload_file('.fixtures/video.3gp');
        $assets = array('mp4_h264_aac', 'jpeg_thumbnail_medium');
        $media = $this->cloudkey->media->create(array('assets_names' => $assets, 'url' => $file->url));

        $res = $this->cloudkey->media->get_embed_url(array('id' => $media->id));
        $this->assertContains("http://", $res);

        $res = $this->cloudkey->media->get_embed_url(array('id' => $media->id, 'secure' => true));
        $this->assertContains("https://", $res);
    }
}

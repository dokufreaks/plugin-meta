<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Test cases for the meta plugin
 */
class plugin_meta_rendering_test extends DokuWikiTest {

    public function setUp() {
        $this->pluginsEnabled[] = 'meta';
        parent::setUp();
    }

    public function test_meta_description() {
        $text = "My page content";
        saveWikiText('description_test', $text, 'Created');
        self::assertEquals($text, p_get_metadata('description_test', 'description abstract', METADATA_RENDER_UNLIMITED));

        $text .= DOKU_LF . '~~META:description abstract=My abstract~~';

        saveWikiText('description_test', $text, 'Added meta');

        self::assertEquals('My abstract', p_get_metadata('description_test', 'description abstract', METADATA_RENDER_UNLIMITED));

        $text .= DOKU_LF . '~~META:description foobar=bar~~';
        saveWikiText('description_test', $text, 'Updated meta');
        self::assertEquals('My abstract', p_get_metadata('description_test', 'description abstract', METADATA_RENDER_UNLIMITED));
        self::assertEquals('bar', p_get_metadata('description_test', 'description foobar', METADATA_RENDER_UNLIMITED));
    }

    public function test_meta_description_with_persistent_description() {
        $text = "My page content";
        $id = 'description_test';
        saveWikiText($id, $text, 'Created');
        self::assertEquals($text, p_get_metadata($id, 'description abstract', METADATA_RENDER_UNLIMITED));

        p_set_metadata($id, array('description' => array('abstract' => 'Persistent description')), false, true);
        self::assertEquals('Persistent description', p_get_metadata($id, 'description abstract', METADATA_RENDER_UNLIMITED));

        $text .= DOKU_LF . '~~META:description abstract=My abstract~~';

        saveWikiText($id, $text, 'Added meta');

        self::assertEquals('My abstract', p_get_metadata($id, 'description abstract', METADATA_RENDER_UNLIMITED));

        $text .= DOKU_LF . '~~META:description foobar=bar~~';
        saveWikiText($id, $text, 'Updated meta');
        self::assertEquals('My abstract', p_get_metadata($id, 'description abstract', METADATA_RENDER_UNLIMITED));
        self::assertEquals('bar', p_get_metadata($id, 'description foobar', METADATA_RENDER_UNLIMITED));
    }

    public function test_relation_references_with_link() {
        $text = "My page with a [[link_target|Link]].";
        $id = "source";

        saveWikiText($id, $text, 'Created');

        self::assertEquals(array('link_target' => false), p_get_metadata($id, 'relation references', METADATA_RENDER_UNLIMITED));

        $text .= DOKU_LF. "~~META:relation references=foo~~";
        saveWikiText($id, $text, 'Updated');

        self::assertEquals(array('foo' => false, 'link_target' => false), p_get_metadata($id, 'relation references', METADATA_RENDER_UNLIMITED));
    }

    public function test_relation_references_without_link() {
        $text = "My page without a link.";
        $id = "source";

        saveWikiText($id, $text, 'Created');

        self::assertEquals(null, p_get_metadata($id, 'relation references', METADATA_RENDER_UNLIMITED));

        $text .= DOKU_LF . "~~META:relation references=foo~~";
        saveWikiText($id, $text, 'Updated');

        self::assertEquals(array('foo' => false), p_get_metadata($id, 'relation references', METADATA_RENDER_UNLIMITED));
    }
}

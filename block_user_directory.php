<?php

/**
 * Main plugin functions for user directory
 *
 * @package   block_user_directory
 * @copyright Anthony Kuske <www.anthonykuske.com> and Adam Morris <www.mistermorris.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_user_directory extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_user_directory');
    }

    /**
     * Returns the contents when shown in block form
     */
    public function get_content() {
        return '';
    }

    /**
     * Define what pages the block can be added to
     * (We only want to view the block at /blocks/user_directory but something needs
     * to be defined here)
     */
    public function applicable_formats() {
        return array(
            'all'  => false,
            'site' => true,
        );
    }

    /**
     * Allow multiple instances of the block on the same page?
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Does the block have admin settings?
     */
    public function has_config() {
        return true;
    }
}

<?php

namespace block_sharing_cart\app\backup;

// @codeCoverageIgnoreStart
defined('MOODLE_INTERNAL') || die();

// @codeCoverageIgnoreEnd

use block_sharing_cart\app\factory as base_factory;
use block_sharing_cart\task\asynchronous_backup_task;

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

class handler
{
    private base_factory $base_factory;

    public function __construct(base_factory $base_factory)
    {
        $this->base_factory = $base_factory;
    }

    public function backup_course_module(
        int $course_module_id,
        object $root_item,
        array $settings = []
    ): asynchronous_backup_task {
        global $USER;

        $backup_controller = $this->base_factory->backup()->backup_controller(
            \backup::TYPE_1ACTIVITY,
            $course_module_id,
            $USER->id
        );

        return $this->queue_async_backup($backup_controller, $root_item, $settings);
    }

    public function backup_section(int $section_id, object $root_item, array $settings = []): asynchronous_backup_task
    {
        global $USER;

        $backup_controller = $this->base_factory->backup()->backup_controller(
            \backup::TYPE_1SECTION,
            $section_id,
            $USER->id
        );

        return $this->queue_async_backup($backup_controller, $root_item, $settings);
    }

    public function backup_course(int $course_id, object $root_item, array $settings = []): asynchronous_backup_task
    {
        global $USER;

        $backup_controller = $this->base_factory->backup()->backup_controller(
            \backup::TYPE_1COURSE,
            $course_id,
            $USER->id
        );

        return $this->queue_async_backup($backup_controller, $root_item, $settings);
    }

    public function get_backup_item_tree(\stored_file $file): array
    {
        $tree = [];

        /**
         * @var \file_storage $fs
         */
        $fs = get_file_storage();
        $file_path = $fs->get_file_system()->get_local_path_from_storedfile($file);

        /** @var object $info */
        $info = \backup_general_helper::get_backup_information_from_mbz($file_path);

        foreach ($info->sections as $section) {
            $tree[$section->sectionid] = (object)[
                'sectionid' => $section->sectionid,
                'title' => $section->title,
                'activities' => []
            ];
        }
        foreach ($info->activities as $activity) {
            $tree[$activity->sectionid]->activities[$activity->moduleid] = (object)[
                'moduleid' => $activity->moduleid,
                'sectionid' => $activity->sectionid,
                'modulename' => $activity->modulename,
                'title' => $activity->title
            ];
        }

        return $tree;
    }

    public function get_backup_item_course_modules(\stored_file $file): array
    {
        $course_modules = [];

        /**
         * @var \file_storage $fs
         */
        $fs = get_file_storage();
        $file_path = $fs->get_file_system()->get_local_path_from_storedfile($file);

        /** @var object $info */
        $info = \backup_general_helper::get_backup_information_from_mbz($file_path);

        foreach ($info->activities as $activity) {
            $course_modules[$activity->moduleid] = (object)[
                'moduleid' => $activity->moduleid,
                'modulename' => $activity->modulename,
                'title' => $activity->title
            ];
        }

        return $course_modules;
    }

    private function queue_async_backup(
        \backup_controller $backup_controller,
        object $root_item,
        array $settings = []
    ): asynchronous_backup_task {
        $asynctask = new asynchronous_backup_task();
        $asynctask->set_blocking(false);
        $asynctask->set_custom_data([
            'backupid' => $backup_controller->get_backupid(),
            'block_sharing_cart_root_item_id' => $root_item->id,
            'backup_settings' => $settings
        ]);
        $asynctask->set_userid($backup_controller->get_userid());
        \core\task\manager::queue_adhoc_task($asynctask);

        return $asynctask;
    }
}
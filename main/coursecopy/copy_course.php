<?php
/* For licensing terms, see /license.txt */

use Chamilo\CourseBundle\Component\CourseCopy\CourseBuilder;
use Chamilo\CourseBundle\Component\CourseCopy\CourseRestorer;
use Chamilo\CourseBundle\Component\CourseCopy\CourseSelectForm;

/**
 * @todo rework file in order to use addFlash
 *
 * @package chamilo.backup
 */

// Setting the global file that gets the general configuration, the databases, the languages, ...
require_once __DIR__.'/../inc/global.inc.php';
$current_course_tool = TOOL_COURSE_MAINTENANCE;
api_protect_course_script(true);

if (!api_is_allowed_to_edit()) {
    api_not_allowed(true);
}

api_set_more_memory_and_time_limits();

// Breadcrumbs
$interbreadcrumb[] = [
    'url' => api_get_path(WEB_CODE_PATH).'course_info/maintenance.php?'.api_get_cidreq(),
    'name' => get_lang('Backup'),
];

// The section (for the tabs)
$this_section = SECTION_COURSES;

// Display the header
Display::display_header(get_lang('Copy course'));
echo Display::page_header(get_lang('Copy course'));

$action = isset($_POST['action']) ? $_POST['action'] : '';

// If a CourseSelectForm is posted or we should copy all resources, then copy them
if (Security::check_token('post') && (
    ($action === 'course_select_form') ||
    (isset($_POST['copy_option']) && $_POST['copy_option'] === 'full_copy')
    )
) {
    // Clear token
    Security::clear_token();
    if ($action === 'course_select_form') {
        $course = CourseSelectForm::get_posted_course('copy_course');
    } else {
        $cb = new CourseBuilder();
        $course = $cb->build();
    }
    $cr = new CourseRestorer($course);
    $cr->set_file_option($_POST['same_file_name_option']);
    $cr->restore($_POST['destination_course']);
    echo Display::return_message(
        get_lang('Copying is finished').': <a href="'.api_get_course_url($_POST['destination_course']).'">'.
        Security::remove_XSS($_POST['destination_course']).
        '</a>',
        'normal',
        false
    );
} elseif (Security::check_token('post') && (
        isset($_POST['copy_option']) &&
        $_POST['copy_option'] === 'select_items'
    )
) {
    // Clear token
    Security::clear_token();

    $cb = new CourseBuilder();
    $course = $cb->build();
    $hiddenFields = [];
    $hiddenFields['same_file_name_option'] = $_POST['same_file_name_option'];
    $hiddenFields['destination_course'] = $_POST['destination_course'];
    // Add token to Course select form
    $hiddenFields['sec_token'] = Security::get_token();
    CourseSelectForm::display_form($course, $hiddenFields, true);
} else {
    $table_c = Database::get_main_table(TABLE_MAIN_COURSE);
    $table_cu = Database::get_main_table(TABLE_MAIN_COURSE_USER);
    $user_info = api_get_user_info();
    $course_info = api_get_course_info();

    $courseList = CourseManager::get_courses_list_by_user_id(
        $user_info['user_id'],
        false,
        false,
        false,
        [$course_info['real_id']]
    );

    if (empty($courseList)) {
        echo Display::return_message(get_lang('No destination course available'), 'normal');
    } else {
        $options = [];
        foreach ($courseList as $courseItem) {
            $courseInfo = api_get_course_info_by_id($courseItem['real_id']);
            $options[$courseInfo['code']] = $courseInfo['title'].' ('.$courseInfo['code'].')';
        }

        $form = new FormValidator(
            'copy_course',
            'post',
            api_get_path(WEB_CODE_PATH).'coursecopy/copy_course.php?'.api_get_cidreq()
        );
        $form->addElement('select', 'destination_course', get_lang('Select target course'), $options);

        $group = [];
        $group[] = $form->createElement('radio', 'copy_option', null, get_lang('Full copy'), 'full_copy');
        $group[] = $form->createElement('radio', 'copy_option', null, get_lang('Let me select learning objects'), 'select_items');
        $form->addGroup($group, '', get_lang('Please select a backup option'));

        $group = [];
        $group[] = $form->createElement(
            'radio',
            'same_file_name_option',
            null,
            get_lang('Skip same file name'),
            FILE_SKIP
        );
        $group[] = $form->createElement(
            'radio',
            'same_file_name_option',
            null,
            get_lang('Rename file (eg file.pdf becomes file_1.pdf)'),
            FILE_RENAME
        );
        $group[] = $form->createElement(
            'radio',
            'same_file_name_option',
            null,
            get_lang('Overwrite file'),
            FILE_OVERWRITE
        );
        $form->addGroup($group, '', get_lang('What should be done with imported files with the same file name as existing files?'));
        $form->addProgress();
        $form->addButtonSave(get_lang('Copy course'));
        $form->setDefaults(['copy_option' => 'select_items', 'same_file_name_option' => FILE_OVERWRITE]);

        // Add Security token
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(['sec_token' => $token]);
        $form->display();
    }
}

Display::display_footer();

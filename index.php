<?php
require_once(__DIR__ . '/../../config.php');

$context = context_system::instance();
$PAGE->set_context($context);

require_login();

global $DB, $USER, $OUTPUT;

$PAGE->set_url(new moodle_url('/local/allcourses/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('All Courses');
$PAGE->set_heading('All Courses');
$PAGE->blocks->show_only_fake_blocks();

echo $OUTPUT->header();

/* USER DEPARTMENT */
$userdept = '';
if (!empty($USER->department)) {
    $userdept = strtolower(trim(preg_replace('/\s+/', ' ', $USER->department)));
}

$canviewall = has_capability('local/allcourses:viewall', $context);

/* GET H5P MODULE ID */
$h5pmodule = $DB->get_record('modules', ['name' => 'h5pactivity'], 'id', MUST_EXIST);

/* FETCH COURSES */
$courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC');

echo '<div class="allcourses-container">';

foreach ($courses as $course) {

    if ($course->id == SITEID) {
        continue;
    }

    $category = core_course_category::get($course->category, IGNORE_MISSING);
    if (!$category) {
        continue;
    }

    $coursedept = strtolower(trim(preg_replace('/\s+/', ' ', $category->name)));

    if (!$canviewall) {
        if ($coursedept !== 'all department' && $coursedept !== $userdept) {
            continue;
        }
    }

    /* GET FIRST H5P CMID */
    $sql = "
        SELECT cm.id
        FROM {course_modules} cm
        JOIN {h5pactivity} h ON h.id = cm.instance
        WHERE cm.course = :courseid
          AND cm.module = :moduleid
          AND cm.visible = 1
        ORDER BY cm.id ASC
    ";

    $params = [
        'courseid' => $course->id,
        'moduleid' => $h5pmodule->id
    ];

    //$cmid = $DB->get_field_sql($sql, $params);
    $cmid = $DB->get_field_sql($sql, $params, IGNORE_MULTIPLE);


    if (!$cmid) {
        continue; // skip courses without H5P
    }

    $playerurl = new moodle_url('/local/allcourses/player.php', ['cmid' => $cmid]);

    echo '<div class="course-card">';
    echo '<h3><a href="'.$playerurl.'" target="_blank">'
        . format_string($course->fullname) .
        '</a></h3>';
    echo '<div class="course-department">'.format_string($category->name).'</div>';
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();

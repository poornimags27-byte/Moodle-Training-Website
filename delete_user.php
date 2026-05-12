<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php'); // important for delete_user()

require_login();
require_sesskey();

$userid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_capability('local/useractions:manage', $context);

global $DB, $USER;

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Safety checks
if (
    isguestuser($user) ||
    $user->id == $USER->id ||
    is_siteadmin($user)
) {
    redirect(new moodle_url('/local/useractions/index.php'));
}

// Delete the user using Moodle API function
delete_user($user);

redirect(new moodle_url('/local/useractions/index.php'));

<?php
require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/useractions:manage', $context);
require_sesskey();

$id = required_param('id', PARAM_INT);
global $DB;
$user = $DB->get_record('user',['id'=>$id], '*', MUST_EXIST);

if (!$user->confirmed) {
    $user->confirmed = 1;
    $DB->update_record('user', $user);
}

redirect(new moodle_url('/local/useractions/index.php'));

<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/useractions:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/useractions/index.php'));
$PAGE->set_title('Browse users');
$PAGE->set_heading('Browse users');

global $DB, $USER;

/* =========================================================
 * BULK ACTIONS (PROCESS BEFORE OUTPUT)
 * ========================================================= */
$action  = optional_param('action', '', PARAM_ALPHA);
$userids = optional_param_array('userids', [], PARAM_INT);

if ($action && $userids) {
    foreach ($userids as $uid) {
        $u = $DB->get_record('user', ['id' => $uid], '*', MUST_EXIST);

        if (isguestuser($u) || $u->id == $USER->id || is_siteadmin($u)) {
            continue;
        }

        switch ($action) {
            case 'delete':
                user_delete_user($u);
                break;

            case 'suspend':
                $u->suspended = 1;
                $DB->update_record('user', $u);
                break;

            case 'activate':
                $u->suspended = 0;
                $DB->update_record('user', $u);
                break;

            case 'confirm':
                $u->confirmed = 1;
                $DB->update_record('user', $u);
                break;

            case 'resend':
                \core\message\send_confirmation_email($u);
                break;
        }
    }
    redirect($PAGE->url);
}

/* =========================================================
 * FILTER + PAGINATION PARAMS
 * ========================================================= */
$filterfield = optional_param('filterfield', '', PARAM_ALPHA);
$filtervalue = optional_param('filtervalue', '', PARAM_TEXT);

$page    = optional_param('page', 0, PARAM_INT);
$perpage = 20;

/* =========================================================
 * HEADER
 * ========================================================= */
echo $OUTPUT->header();

/* =========================================================
 * FILTER FORM
 * ========================================================= */
echo html_writer::start_tag('form', ['method' => 'get', 'style' => 'margin-bottom:15px']);

echo html_writer::select(
    [
        '' => 'Select field',
        'firstname'  => 'First name',
        'department' => 'Department',
        'unit'       => 'Unit',
        'idnumber'   => 'ID number'
    ],
    'filterfield',
    $filterfield,
    null,
    ['class' => 'custom-select']
);

echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'filtervalue',
    'value'       => s($filtervalue),
    'placeholder' => 'Enter value',
    'style'       => 'margin-left:10px'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Search',
    'class' => 'btn btn-secondary',
    'style' => 'margin-left:10px'
]);

echo html_writer::end_tag('form');

/* =========================================================
 * FLEXIBLE TABLE
 * ========================================================= */
$table = new flexible_table('local-useractions-users');

$table->define_columns([
    'select',
    'fullname',
    'idnumber',
    'department',
    'unit',
    'cohorts',
    'confirmed',
    'actions'
]);

$table->define_headers([
    html_writer::checkbox('selectall', 1, false),
    'Full name',
    'ID number',
    'Department',
    'Unit',
    'Department Group',
    'Confirmed',
    'Actions'
]);

$table->set_attribute('class', 'generaltable table-striped');
$table->define_baseurl($PAGE->url);
$table->setup();

/* =========================================================
 * SQL (COUNT FOR PAGINATION)
 * ========================================================= */
$countsql = "SELECT COUNT(1)
               FROM {user}
              WHERE deleted = 0
                AND id <> :guestid";

$params = ['guestid' => $CFG->siteguest];

$allowedfields = ['firstname', 'department', 'unit', 'idnumber'];

$countparams = ['guestid' => $CFG->siteguest];

if ($filterfield && $filtervalue && in_array($filterfield, $allowedfields)) {
    $countsql .= " AND LOWER(TRIM({$filterfield})) = LOWER(TRIM(:filterval))";
    $countparams['filterval'] = trim($filtervalue); // ❗ NO %
}

$totalusers = $DB->count_records_sql($countsql, $countparams);

/* =========================================================
 * SQL (FETCH USERS WITH COHORTS)
 * ========================================================= */
$sql = "SELECT u.*,
               GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS cohorts
          FROM {user} u
     LEFT JOIN {cohort_members} cm ON cm.userid = u.id
     LEFT JOIN {cohort} c ON c.id = cm.cohortid
         WHERE u.deleted = 0
           AND u.id <> :guestid";

$sqlparams = ['guestid' => $CFG->siteguest];

if ($filterfield && $filtervalue && in_array($filterfield, $allowedfields)) {
    $sql .= " AND LOWER(TRIM(u.{$filterfield})) = LOWER(TRIM(:filterval))";
    $sqlparams['filterval'] = trim($filtervalue); // ❗ NO %
}

$sql .= " GROUP BY u.id
          ORDER BY u.firstname ASC, u.lastname ASC";

$offset = $page * $perpage;

$users = $DB->get_records_sql(
    $sql . " LIMIT $perpage OFFSET $offset",
    $sqlparams
);

/* =========================================================
 * POPULATE TABLE
 * ========================================================= */
foreach ($users as $u) {

    $checkboxattrs = ['class' => 'user-select'];
    if (isguestuser($u) || $u->id == $USER->id || is_siteadmin($u)) {
        $checkboxattrs['disabled'] = 'disabled';
    }

    $checkbox = html_writer::checkbox('userids[]', $u->id, false, '', $checkboxattrs);

    $confirmed = $u->confirmed
        ? html_writer::tag('span', 'Yes', ['class' => 'badge badge-success'])
        : html_writer::tag('span', 'No', ['class' => 'badge badge-danger']);

    $actions = '';
    if (!isguestuser($u) && $u->id != $USER->id && !is_siteadmin($u)) {

        $actions .= html_writer::link(
            new moodle_url('/user/edit.php', ['id' => $u->id]),
            '<i class="fa fa-pen fa-fw" title="Edit"></i>'
        ) . ' ';

        $actions .= html_writer::link(
            new moodle_url('/local/useractions/toggle_suspend.php', [
                'id' => $u->id,
                'sesskey' => sesskey()
            ]),
            $u->suspended
                ? '<i class="fa fa-eye fa-fw" title="Activate"></i>'
                : '<i class="fa fa-eye-slash fa-fw" title="Suspend"></i>'
        ) . ' ';

        $actions .= html_writer::link(
            new moodle_url('/local/useractions/delete_user.php', [
                'id' => $u->id,
                'sesskey' => sesskey()
            ]),
            '<i class="fa fa-trash fa-fw" title="Delete"></i>',
            ['onclick' => 'return confirm("Are you sure?");']
        ) . ' ';

        $actions .= html_writer::link(
            new moodle_url('/local/useractions/confirm_user.php', [
                'id' => $u->id,
                'sesskey' => sesskey()
            ]),
            '<i class="fa fa-check fa-fw" title="Confirm"></i>'
        ) . ' ';

        $actions .= html_writer::link(
            new moodle_url('/local/useractions/resend_confirmation.php', [
                'id' => $u->id,
                'sesskey' => sesskey()
            ]),
            '<i class="fa fa-envelope fa-fw" title="Resend confirmation"></i>'
        );
    }

    $table->add_data([
        $checkbox,
        fullname($u),
        $u->idnumber ?: '-',
        $u->department ?: '-',
        $u->unit ?: '-',
        $u->cohorts ?: '-',
        $confirmed,
        $actions
    ]);
}

/* =========================================================
 * OUTPUT TABLE + PAGINATION
 * ========================================================= */
$table->print_html();

echo $OUTPUT->paging_bar(
    $totalusers,
    $page,
    $perpage,
    $PAGE->url
);

/* =========================================================
 * BULK ACTIONS
 * ========================================================= */
echo html_writer::start_tag('form', ['method' => 'post', 'style' => 'margin-top:10px']);

echo html_writer::select([
    '' => 'With selected users...',
    'delete'   => 'Delete',
    'suspend'  => 'Suspend',
    'activate' => 'Activate',
    'confirm'  => 'Confirm',
    'resend'   => 'Resend confirmation'
], 'action', '');

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Go',
    'class' => 'btn btn-primary'
]);

echo html_writer::end_tag('form');

/* =========================================================
 * SELECT ALL JS
 * ========================================================= */
$PAGE->requires->js_init_code("
    document.addEventListener('DOMContentLoaded', function () {
        const master = document.querySelector('input[name=selectall]');
        if (!master) return;
        master.addEventListener('change', function () {
            document.querySelectorAll('.user-select:not([disabled])')
                .forEach(cb => cb.checked = master.checked);
        });
    });
");

/* =========================================================
 * FOOTER
 * ========================================================= */
echo $OUTPUT->footer();

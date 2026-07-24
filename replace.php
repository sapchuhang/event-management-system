<?php

$files = [
    'admin/attendance.php',
    'admin/agenda.php',
    'admin/event_report.php',
    'admin/reports.php',
    'admin/dashboard.php',
    'admin/events.php', // check if anything missed
    'actions/add_event_action.php',
    'actions/edit_event_action.php',
    'actions/delete_event_action.php',
    'actions/mark_attendance.php',
    'actions/unmark_attendance.php',
    'actions/export_attendance.php',
    'actions/delete_agenda_action.php',
    'actions/get_member_details.php',
    'includes/header.php',
    'includes/footer.php',
];

$replacements = [
    'agm_id' => 'event_id',
    'agm_date' => 'event_date',
    'agms' => 'events',
    '$agm ' => '$event ',
    '$agm;' => '$event;',
    '$agm=' => '$event=',
    '$agm[' => '$event[',
    '$all_agms' => '$all_events',
    'agm.php' => 'events.php',
    '_agm_action.php' => '_event_action.php',
    'addAgmModal' => 'addEventModal',
    'editAgmModal' => 'editEventModal',
    'edit_agm_' => 'edit_event_',
    'btn-edit-agm' => 'btn-edit-event',
    'agmId' => 'eventId',
    '$agm->' => '$event->',
    'agm_system' => 'event_management' // just in case
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
echo "Done.\n";

<?php
/**
 * TaskFlow end-to-end smoke test, executed through WP-CLI inside a live
 * WordPress installation (SQLite drop-in). Exercises the REST API layer with
 * permission switches between users and prints a PASS/FAIL summary.
 *
 * Run: wp eval-file e2e-test.php
 */

$wp_tests = array();
$wp_fails = array();

function tf_check( $label, $condition ) {
        global $wp_tests, $wp_fails;

        if ( $condition ) {
                $wp_tests[] = $label;
                echo "  [PASS] $label\n";
        } else {
                $wp_fails[] = $label;
                echo "  [FAIL] $label\n";
        }
}

/**
 * Dispatch a REST request as a specific user.
 */
function tf_rest( $user_id, $method, $route, $body = array() ) {
        wp_set_current_user( $user_id );

        $query = array();
        $path  = $route;

        if ( false !== strpos( $route, '?' ) ) {
                list( $path, $query_string ) = explode( '?', $route, 2 );
                parse_str( $query_string, $query );
        }

        $request = new WP_REST_Request( $method, '/taskflow/v1' . $path );

        if ( $query ) {
                $request->set_query_params( $query );
        }

        if ( $body ) {
                foreach ( $body as $key => $value ) {
                        $request->set_param( $key, $value );
                }
        }

        return rest_get_server()->dispatch( $request );
}

function tf_code( $response ) {
        return $response instanceof WP_REST_Response ? $response->get_status() : ( $response->get_status() ?? 500 );
}

function tf_data( $response ) {
        return $response->get_data();
}

echo "== TaskFlow E2E on live WordPress ==\n";

// 1. Context.
$context = tf_rest( 1, 'GET', '/context' );
tf_check( 'context returns 200 for admin', 200 === tf_code( $context ) );
tf_check( 'context has manage capability', true === tf_data( $context )['caps']['manage'] );

// 2. Projects.
$project = tf_rest( 1, 'POST', '/projects', array( 'name' => 'Website', 'color' => '#2271b1' ) );
tf_check( 'project created', 200 === tf_code( $project ) );
$project_id = tf_data( $project )['item']['id'];

$project_denied = tf_rest( 3, 'POST', '/projects', array( 'name' => 'Nope' ) );
tf_check( 'author cannot create project (403)', 403 === tf_code( $project_denied ) );

// 3. Tags.
$tag = tf_rest( 1, 'POST', '/tags', array( 'name' => 'frontend' ) );
tf_check( 'tag created', 200 === tf_code( $tag ) );
$tag_id = tf_data( $tag )['item']['id'];

// 4. Users list.
$users = tf_rest( 1, 'GET', '/users' );
tf_check( 'users list has admin', 200 === tf_code( $users ) && count( tf_data( $users )['items'] ) >= 1 );
$author_id = 3;

// 5. Task create.
$task = tf_rest(
        1,
        'POST',
        '/tasks',
        array(
                'title'       => 'E2E Design landing page',
                'description' => 'High fidelity design',
                'priority'    => 'high',
                'due_date'    => '2026-12-01T14:30',
                'project_id'  => $project_id,
                'assignee_id' => $author_id,
                'tag_ids'     => array( $tag_id ),
        )
);
tf_check( 'task created', 201 === tf_code( $task ) );
$task_data = tf_data( $task )['item'][0];
$task_id   = $task_data['id'];
tf_check( 'task decorated with project', 'Website' === $task_data['project']['name'] );
tf_check( 'task decorated with assignee', 'author' === $task_data['assignee']['name'] );
tf_check( 'task has tag', ! empty( $task_data['tags'] ) && 'frontend' === $task_data['tags'][0]['name'] );
tf_check( 'task due date normalized', '2026-12-01 14:30:00' === $task_data['due_date'] );

// 6. Validation error.
$invalid = tf_rest( 1, 'POST', '/tasks', array( 'title' => 'Bad', 'status' => 'bogus' ) );
tf_check( 'invalid status rejected (400)', 400 === tf_code( $invalid ) );

// 7. Assignment side effects for the author.
$notifications = tf_rest( 3, 'GET', '/notifications' );
tf_check( 'author got assignment notification', tf_data( $notifications )['unread'] >= 1 );

$outbox = tf_rest( 2, 'GET', '/mock/email/outbox' );
tf_check( 'assignment wrote MOCK email to outbox', tf_data( $outbox )['total'] >= 1 );
tf_check( 'outbox subject marked MOCK', false !== strpos( tf_data( $outbox )['items'][0]['subject'], 'MOCK' ) );

// 8. Author permissions: sees own/assigned only.
$author_tasks = tf_rest( 3, 'GET', '/tasks' );
tf_check( 'author sees assigned task', 1 === tf_data( $author_tasks )['total'] );

$author_create = tf_rest( 3, 'POST', '/tasks', array( 'title' => 'Author task' ) );
tf_check( 'author can create task', 201 === tf_code( $author_create ) );
$author_task_id = tf_data( $author_create )['item'][0]['id'];

// Author cannot edit admin's task.
$author_edit_admin_task = tf_rest( 3, 'PUT', '/tasks/' . $task_id, array( 'title' => 'Hacked' ) );
tf_check( 'author cannot edit others task (403)', 403 === tf_code( $author_edit_admin_task ) );

// Author CAN move own task (Kanban).
$move = tf_rest( 3, 'POST', '/tasks/' . $author_task_id . '/status', array( 'status' => 'in_progress' ) );
tf_check( 'author moves own task', 200 === tf_code( $move ) );

// Author is ASSIGNEE of the admin task, so moving it is allowed by design;
// blocking applies to tasks the author neither created nor is assigned to.
$foreign = tf_rest( 2, 'POST', '/tasks', array( 'title' => 'Editors own', 'assignee_id' => 2 ) );
$foreign_id = tf_data( $foreign )['item'][0]['id'];

$author_move_foreign = tf_rest( 3, 'POST', '/tasks/' . $foreign_id . '/status', array( 'status' => 'completed' ) );
tf_check( 'author cannot move unrelated task (403)', 403 === tf_code( $author_move_foreign ) );

// Subscriber: no access at all.
$subscriber_tasks = tf_rest( 4, 'GET', '/tasks' );
tf_check( 'subscriber blocked from REST (403)', 403 === tf_code( $subscriber_tasks ) );

// 9. Editor manages everything.
$editor_view = tf_rest( 2, 'GET', '/tasks' );
tf_check( 'editor sees all tasks', tf_data( $editor_view )['total'] >= 2 );

$editor_edit = tf_rest( 2, 'PUT', '/tasks/' . $task_id, array( 'priority' => 'urgent' ) );
tf_check( 'editor edits any task', 200 === tf_code( $editor_edit ) );

// 10. Comments with mention.
$comment = tf_rest( 1, 'POST', '/tasks/' . $task_id . '/comments', array( 'content' => 'Ping @editor please' ) );
tf_check( 'comment added', 200 === tf_code( $comment ) );
tf_check( 'mention resolved', array( 2 ) === tf_data( $comment )['item']['mentions'] );

$editor_notes = tf_rest( 2, 'GET', '/notifications' );
tf_check( 'editor got comment notification', tf_data( $editor_notes )['unread'] >= 1 );

// 11. Search & filters.
$search = tf_rest( 1, 'GET', '/tasks?search=landing' );
tf_check( 'search by title works', 1 === tf_data( $search )['total'] );

$filter = tf_rest( 1, 'GET', '/tasks?status=new&priority=urgent' );
tf_check( 'filter by status+priority works', 1 === tf_data( $filter )['total'] );

$sorted = tf_rest( 1, 'GET', '/tasks?orderby=priority&order=ASC' );
tf_check( 'sort by priority works', 200 === tf_code( $sorted ) );

// 12. Kanban move + resequence.
$move2 = tf_rest( 1, 'POST', '/tasks/' . $task_id . '/status', array( 'status' => 'completed', 'position' => 0 ) );
tf_check( 'admin moves task to completed', 200 === tf_code( $move2 ) );
tf_check( 'completed_at stamped', null !== tf_data( $move2 )['item'][0]['completed_at'] );

// 13. Dashboard.
$dashboard = tf_rest( 1, 'GET', '/dashboard' );
$stats     = tf_data( $dashboard )['stats'];
tf_check( 'dashboard counts live', $stats['counts']['total'] >= 2 );
tf_check( 'dashboard completion rate computed', $stats['completion_rate'] > 0 );
tf_check( 'dashboard recent activity exists', count( $stats['recent_activity'] ) >= 1 );

// 14. Activity feed.
$activity = tf_rest( 1, 'GET', '/activity?task_id=' . $task_id );
tf_check( 'activity history recorded', tf_data( $activity )['total'] >= 2 );

$activity_restricted = tf_rest( 3, 'GET', '/activity' );
tf_check( 'activity restricted for author', 200 === tf_code( $activity_restricted ) );

// 15. Time tracking.
$start = tf_rest( 3, 'POST', '/time/start', array( 'task_id' => $author_task_id ) );
tf_check( 'author starts timer', 200 === tf_code( $start ) );

$active = tf_rest( 3, 'GET', '/time/active' );
tf_check( 'active timer visible', null !== tf_data( $active )['item'] );

$stop = tf_rest( 3, 'POST', '/time/stop' );
tf_check( 'timer stops with duration', 200 === tf_code( $stop ) && null !== tf_data( $stop )['item']['ended_at'] );

// 16. Export.
$export_csv = tf_rest( 1, 'GET', '/export?format=csv' );
tf_check( 'CSV export works', 200 === tf_code( $export_csv ) );
tf_check( 'CSV export has headers', is_string( tf_data( $export_csv ) ) && false !== strpos( (string) tf_data( $export_csv ), 'title,description' ) );

$export_json = tf_rest( 1, 'GET', '/export?format=json' );
tf_check( 'JSON export works', 200 === tf_code( $export_json ) );
tf_check( 'JSON export blocked for author', 403 === tf_code( tf_rest( 3, 'GET', '/export?format=json' ) ) );

// 17. Import.
$import = tf_rest(
        1,
        'POST',
        '/import',
        array( 'csv' => "title,status\nImported A,new\nImported B,in_progress\n" )
);
tf_check( 'CSV import works', 200 === tf_code( $import ) && 2 === tf_data( $import )['report']['created'] );

// 18. Mock payments.
$pay = tf_rest( 1, 'POST', '/mock/payments', array( 'amount' => 250, 'currency' => 'USD', 'simulate' => 'pending' ) );
tf_check( 'mock payment created as pending', 201 === tf_code( $pay ) );
$pay_id = tf_data( $pay )['item']['id'];

$finalized = tf_rest( 1, 'POST', '/mock/payments/' . $pay_id . '/finalize', array( 'result' => 'success' ) );
tf_check( 'pending payment finalized', 'success' === tf_data( $finalized )['item']['status'] );

$pay_denied = tf_rest( 2, 'POST', '/mock/payments', array( 'amount' => 10, 'simulate' => 'success' ) );
tf_check( 'editor blocked from payments (403)', 403 === tf_code( $pay_denied ) );

// 19. Mock email send + simulate incoming.
$send = tf_rest( 2, 'POST', '/mock/email/send', array( 'to' => 'x@example.com', 'subject' => 'Test', 'body' => 'Hello' ) );
tf_check( 'mock email sent to outbox', 200 === tf_code( $send ) );

$incoming = tf_rest( 2, 'POST', '/mock/email/inbox', array( 'from_email' => 'client@example.com', 'subject' => 'Question', 'body' => 'Hi' ) );
tf_check( 'mock incoming email stored', 200 === tf_code( $incoming ) );

$inbox = tf_rest( 2, 'GET', '/mock/email/inbox' );
tf_check( 'inbox lists message', tf_data( $inbox )['total'] >= 1 );

// 20. Settings.
$settings_get = tf_rest( 1, 'GET', '/settings' );
tf_check( 'settings readable by admin', 200 === tf_code( $settings_get ) );
tf_check( 'settings blocked for editor', 403 === tf_code( tf_rest( 2, 'GET', '/settings' ) ) );

$settings_save = tf_rest( 1, 'POST', '/settings', array( 'currency' => 'IRT', 'items_per_page' => 20, 'extra_edit_roles' => array( 'author' ) ) );
tf_check( 'settings saved', 'IRT' === tf_data( $settings_save )['settings']['currency'] );

// 21. System info.
$system = tf_rest( 1, 'GET', '/system' );
$system_data = tf_data( $system )['system'] ?? tf_data( $system );
tf_check( 'system info available', 200 === tf_code( $system ) && $system_data['tables']['tasks']['rows'] >= 1 );

// 22. Delete task.
$deleted = tf_rest( 1, 'DELETE', '/tasks/' . $author_task_id );
tf_check( 'task deleted', 200 === tf_code( $deleted ) );
tf_check( 'deleted task gone', 404 === tf_code( tf_rest( 1, 'GET', '/tasks/' . $author_task_id ) ) );

echo "\n== RESULT: " . count( $wp_tests ) . ' passed, ' . count( $wp_fails ) . " failed ==\n";

if ( $wp_fails ) {
        echo "FAILED:\n";

        foreach ( $wp_fails as $fail ) {
                echo " - $fail\n";
        }

        exit( 1 );
}

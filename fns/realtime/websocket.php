<?php

include 'fns/registry/load.php';
include 'fns/data_cache/load.php';
include 'include/config.php';


Registry::__init();
$config->ws_instance = true;

date_default_timezone_set('Asia/Kolkata');

include 'fns/global/load.php';

$sys_settings = extract_json(['file' => 'assets/cache/settings.cache']);
$url_info = $sys_settings->site_address;

$config = (object) array_merge((array)$config, (array)$url_info);
Registry::add('config', $config);
Registry::add('settings', $sys_settings);
$GLOBALS['swoole_worker_id'] = null;

include_once 'fns/sql/swoole.php';
include 'fns/realtime/ws_process_data.php';


$ws_max_connections = $sys_settings->websocket_max_con;
$ws_host = $sys_settings->websocket_host;
$ws_port = $sys_settings->websocket_port;
$ws_protocol = $sys_settings->websocket_protocol;
$ws_ssl_certificate = $sys_settings->websocket_ssl_certificate;
$ws_private_key = $sys_settings->websocket_private_key;

$tcp_host = Registry::load('settings')->websocket_tcp_host;
$tcp_port = Registry::load('settings')->websocket_tcp_port;

$languageCache = [];
$languageCacheTimestamps = [];
$permissionsCache = [];
$permissionsCacheTimestamps = [];
$cacheTTL = 300;

$ws_clients = new Swoole\Table($ws_max_connections);
$ws_clients->column('user_id', Swoole\Table::TYPE_INT);
$ws_clients->column('fd', Swoole\Table::TYPE_INT);
$ws_clients->column('info', Swoole\Table::TYPE_STRING, 3072);
$ws_clients->column('chat_context', Swoole\Table::TYPE_STRING, 1024);
$ws_clients->column('userFpToken', Swoole\Table::TYPE_STRING, 255);
$ws_clients->create();

$ws_users = new Swoole\Table($ws_max_connections);
$ws_users->column('fds', Swoole\Table::TYPE_STRING, 256);
$ws_users->create();

$group_users = new Swoole\Table(2048);
$group_users->column('fds', Swoole\Table::TYPE_STRING, 10000);
$group_users->create();

$user_active_groups = new Swoole\Table($ws_max_connections);
$user_active_groups->column('groups', Swoole\Table::TYPE_STRING, 512);
$user_active_groups->create();

$monitoring_table = new Swoole\Table(128);
$monitoring_table->column('user_id', Swoole\Table::TYPE_INT);
$monitoring_table->create();

$ws_instance = new Swoole\Websocket\Server($ws_host, $ws_port ?: 9502);

if ($ws_protocol === 'ssl') {
    $ws_instance->set([
        'ssl_cert_file' => $ws_ssl_certificate,
        'ssl_key_file' => $ws_private_key,
    ]);
}

$ws_instance->set([
    // 'worker_num' => swoole_cpu_num() * 1.5,
    'reactor_num' => swoole_cpu_num(),
    'max_request' => 0,
    'reload_async' => true,
    'max_wait_time' => 60,
    'heartbeat_check_interval' => 30,
    'heartbeat_idle_time' => 120,

    'dispatch_mode' => 2,
    'enable_coroutine' => true,
    'open_tcp_nodelay' => true,
    'tcp_fastopen' => true,
    'max_connection' => 12000,
    'buffer_output_size' => 32 * 1024 * 1024,
]);

$tcp_listener = $ws_instance->addListener($tcp_host, $tcp_port, SWOOLE_SOCK_TCP);
$tcp_listener->set([
    'open_length_check' => true,
    'package_length_type' => 'N',
    'package_length_offset' => 0,
    'package_body_offset' => 4,
    'package_max_length' => 2 * 1024 * 1024,
    'backlog' => 2048,
]);


function getLanguageStrings($lang_id, $ttl = 300) {
    global $languageCache, $languageCacheTimestamps;

    $now = time();
    if (!isset($languageCache[$lang_id]) || ($now - ($languageCacheTimestamps[$lang_id] ?? 0)) > $ttl) {
        $languageCache[$lang_id] = extract_json([
            'file' => 'assets/cache/languages/language-' . $lang_id . '.cache'
        ]);
        $languageCacheTimestamps[$lang_id] = $now;
    }
    return $languageCache[$lang_id];
}

function getRolePermissions($role_id, $ttl = 300) {
    global $permissionsCache, $permissionsCacheTimestamps;

    $now = time();
    if (!isset($permissionsCache[$role_id]) || ($now - ($permissionsCacheTimestamps[$role_id] ?? 0)) > $ttl) {
        $permissionsCache[$role_id] = extract_json([
            'file' => 'assets/cache/site_roles.cache',
            'extract' => $role_id
        ]);
        $permissionsCache[$role_id]['role_hierarchy'] = $permissionsCache[$role_id]['role_hierarchy'] ?? 1;
        $permissionsCache[$role_id]['site_role_attribute'] = $permissionsCache[$role_id]['site_role_attribute'] ?? 'custom_site_role';
        $permissionsCacheTimestamps[$role_id] = $now;
    }
    return $permissionsCache[$role_id];
}

$ws_instance->on("open", function ($ws_instance, $request) use (&$ws_clients, &$ws_users, &$monitoring_table, &$group_users) {


    $login_session_id = $request->get['login_session_id'] ?? null;
    $access_code = $request->get['access_code'] ?? null;
    $session_time_stamp = $request->get['session_time_stamp'] ?? null;
    $joined_groups = [];

    include 'fns/variables/load.php';

    $user_data = Registry::load('current_user');

    if (Registry::load('current_user')->logged_in && !empty(Registry::load('current_user')->username)) {
        $user_id = Registry::load('current_user')->id;
        $groups_query = DB::connect()->select('group_members', ['group_id'], ['user_id' => $user_id]);
        foreach ($groups_query as $group) {
            $joined_groups[] = (string)$group['group_id'];
        }
        $user_data->joined_groups = $joined_groups;

    }

    $ws_clients->set($request->fd, [
        'fd' => $request->fd,
        'user_id' => (int)Registry::load('current_user')->id,
        'info' => json_encode($user_data),
        'userFpToken' => null,
        'chat_context' => json_encode([]),
    ]);

    if (Registry::load('current_user')->logged_in && !empty(Registry::load('current_user')->username)) {
        $user_id = Registry::load('current_user')->id;

        if (!$ws_users->exists($user_id)) {
            $ws_users->set($user_id, ['fds' => '[]']);
        }

        $current_fds = json_decode($ws_users->get($user_id)['fds'], true);

        if (!in_array($request->fd, $current_fds)) {
            $current_fds[] = (int)$request->fd;
            $ws_users->set($user_id, ['fds' => json_encode(array_values($current_fds))]);
        }

        DB::connect()->update('site_users', [
            'online_status' => 1,
            "last_seen_on" => Registry::load('current_user')->time_stamp,
            "updated_on" => Registry::load('current_user')->time_stamp,
        ], ['user_id' => $user_id]);

        $ws_data = ['update' => 'online_users'];
        process_ws_data($ws_instance, $ws_data, $ws_clients, $ws_users, $monitoring_table, $group_users);

        $accessing_group_id = $request->get['accessing_group_id'] ?? null;
        process_ws_data($ws_instance, [
            'update' => 'new_online_group_member',
            'group_id' => $accessing_group_id
        ], $ws_clients, $ws_users, $monitoring_table, $group_users);
    }

});

$tcp_listener->on("receive", function ($server, $tcp_fd, $reactor_id, $tcp_data) use (&$ws_instance, &$ws_clients, &$ws_users, &$monitoring_table, &$group_users) {
    try {
        $tcp_data = trim(substr($tcp_data, 4));
        $ws_data = json_decode($tcp_data, true);

        if (!empty($ws_data) && isset($ws_data['ws_transmit_code'])) {
            $ws_transmit_code_file = 'assets/cache/ws_transmit_code.cache';
            if (file_exists($ws_transmit_code_file)) {
                $ws_transmit_code = trim(file_get_contents($ws_transmit_code_file));
                if ($ws_data['ws_transmit_code'] === $ws_transmit_code && isset($ws_data['update'])) {
                    process_ws_data($ws_instance, $ws_data, $ws_clients, $ws_users, $monitoring_table, $group_users);
                } else {
                    echo "HTTP : Invalid Transmit Code" . "\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "WebSocket TCP Error: " . $e->getMessage() . PHP_EOL;
    }
});


$ws_instance->on("request", function ($request, $response) use (&$ws_instance, &$ws_clients, &$ws_users, &$monitoring_table, &$group_users) {
    try {
        $ws_data = json_decode($request->rawContent(), true);
        if (isset($ws_data['ws_transmit_code'])) {
            $ws_transmit_code_file = 'assets/cache/ws_transmit_code.cache';
            if (file_exists($ws_transmit_code_file)) {
                $ws_transmit_code = trim(file_get_contents($ws_transmit_code_file));
                if ($ws_data['ws_transmit_code'] === $ws_transmit_code && isset($ws_data['update'])) {
                    process_ws_data($ws_instance, $ws_data, $ws_clients, $ws_users, $monitoring_table, $group_users);
                } else {
                    echo "HTTP : Invalid Transmit Code". "\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "WebSocket Request Error: " . $e->getMessage() . PHP_EOL;
    }
});

$ws_instance->on("message", function ($ws_instance, $frame) use (&$ws_clients, &$ws_users, &$monitoring_table, &$group_users, &$user_active_groups) {
    try {
        $ws_data = json_decode($frame->data, true);
        $current_fd = (int)$frame->fd;

        if (isset($ws_data['type']) && $ws_data['type'] === "chat_context" && isset($ws_data['payload'])) {
            $payload = $ws_data['payload'];
            $process_ws_data = false;
            $current_client_data = $ws_clients->get($current_fd);
            if (!$current_client_data) return;

            $prev_chat_context = json_decode($current_client_data['chat_context'] ?? '{}', true);
            $current_user_id = (int)$current_client_data['user_id'];

            if (isset($payload['group_id'])) {
                $gid = (string)$payload['group_id'];
                $prev_gid = isset($prev_chat_context['group_id']) ? (string)$prev_chat_context['group_id'] : null;

                if ($prev_gid && $prev_gid !== $gid) {
                    if ($group_users->exists($prev_gid)) {
                        $old_fds = json_decode($group_users->get($prev_gid)['fds'], true);
                        $old_fds = array_filter($old_fds, fn($fd) => $fd !== $current_fd);
                        if (empty($old_fds)) {
                            $group_users->del($prev_gid);
                        } else {
                            $group_users->set($prev_gid, ['fds' => json_encode(array_values($old_fds))]);
                        }

                        process_ws_data($ws_instance, [
                            'update' => 'new_online_group_member',
                            'group_id' => $prev_gid
                        ], $ws_clients, $ws_users, $monitoring_table, $group_users);
                    }
                }

                $group_row = $group_users->get($gid) ?: ['fds' => '[]'];
                $fds = json_decode($group_row['fds'], true);
                if (!in_array($current_fd, $fds)) {
                    $fds[] = $current_fd;
                    $group_users->set($gid, ['fds' => json_encode(array_values($fds))]);
                }

                $u_groups = json_decode($user_active_groups->get((string)$current_fd)['groups'] ?? '[]', true);
                if (!in_array($gid, $u_groups)) {
                    $u_groups[] = $gid;
                    $user_active_groups->set((string)$current_fd, ['groups' => json_encode(array_values($u_groups))]);
                }
            }

            if (isset($payload["userFpToken"]) && empty($current_client_data['userFpToken'])) {
                
                $data = $payload; 
                
                $fp_enabled = (isset(Registry::load('settings')->fingerprint_module) && Registry::load('settings')->fingerprint_module !== 'disable');

                if ($fp_enabled) {
                    include('fns/realtime/userFpToken.php');
                }

                $current_client_data['userFpToken'] = $payload["userFpToken"];
            }

            if (isset($payload['user_id']) && $payload['user_id'] === 'all') {
                $monitoring_table->set((string)$current_fd, ['user_id' => (int)$current_client_data['user_id']]);
            } else {
                $monitoring_table->del((string)$current_fd);
            }

            if (isset($payload['user_id'], $prev_chat_context['user_id']) && (int)$payload['user_id'] !== (int)$prev_chat_context['user_id']) {
                $ws_data = ['update' => 'last_seen_by_recipient', 'receiver_id' => $payload['user_id'], 'sender_id' => $current_client_data['user_id']];
                $process_ws_data = true;
            } elseif (isset($payload['group_id'], $prev_chat_context['group_id']) && (int)$payload['group_id'] !== (int)$prev_chat_context['group_id']) {
                $ws_data = ['update' => 'new_group_video_call', 'group_id' => $payload['group_id']];
                $process_ws_data = true;
            }

            if ($process_ws_data) {
                process_ws_data($ws_instance, $ws_data, $ws_clients, $ws_users, $monitoring_table, $group_users);
            }

            $current_client_data['chat_context'] = json_encode($payload);
            $ws_clients->set($current_fd, $current_client_data);
        }
    } catch (Exception $e) {
        echo "Message Error: " . $e->getMessage() . PHP_EOL;
    }
});

$ws_instance->on('WorkerStart', function ($server, $workerId) use ($config) {
    $GLOBALS['swoole_worker_id'] = $workerId;

    DB::init((array)$config->database);

    if ($workerId === 0) {
        try {
            DB::connect()->update('site_users', ['online_status' => 0], ['online_status' => 1]);
            echo "Master Worker: Global online status reset successful.\n";
        } catch (Exception $e) {
            echo "Master Worker: Global reset failed: " . $e->getMessage() . "\n";
        }
    }
});

$ws_instance->on("close", function ($ws_instance, $current_fd) use (&$ws_clients, &$ws_users, &$monitoring_table, &$group_users, &$user_active_groups) {
    $current_fd = (int)$current_fd;

    if (!$ws_clients->exist($current_fd)) return;

    $client = $ws_clients->get($current_fd);
    $user_id = $client['user_id'];
    $is_last_connection = false;
    $monitoring_table->del((string)$current_fd);

    if ($user_active_groups->exists((string)$current_fd)) {
        $my_groups = json_decode($user_active_groups->get((string)$current_fd)['groups'] ?? '[]', true);
        foreach ($my_groups as $gid) {
            if ($group_users->exists((string)$gid)) {
                $row = $group_users->get((string)$gid);
                $fds = json_decode($row['fds'], true);
                $fds = array_filter($fds, fn($fd) => $fd != $current_fd);

                if (empty($fds)) {
                    $group_users->del((string)$gid);
                } else {
                    $group_users->set((string)$gid, ['fds' => json_encode(array_values($fds))]);
                }
            }
        }
        $user_active_groups->del((string)$current_fd);
    }

    if ($ws_users->exist($user_id)) {
        $current_fds = json_decode($ws_users->get($user_id)['fds'], true);
        $current_fds = array_filter($current_fds, fn($fd) => $fd !== $current_fd);

        if (!empty($current_fds)) {
            $ws_users->set($user_id, ['fds' => json_encode(array_values($current_fds))]);
        } else {
            $ws_users->del($user_id);
            $is_last_connection = true;
        }
    }

    $ws_clients->del($current_fd);

    if ($is_last_connection) {
        DB::connect()->update('site_users', [
            'online_status' => 0,
            "last_seen_on" => time(),
            "updated_on" => time(),
        ], ['user_id' => $user_id]);

        $ws_data = ['update' => 'online_users'];
        process_ws_data($ws_instance, $ws_data, $ws_clients, $ws_users, $monitoring_table, $group_users);
    }
});

$ws_instance->on('WorkerExit', function ($server, $workerId) {
    DB::closeConnection();
});


if (isset(Registry::load('config')->pro_version) && !empty(Registry::load('config')->pro_version)) {
    Swoole\Timer::tick(1800000, function() {
        $currentKolkataTime = date('Y-m-d H:i:s');
        if (!Registry::has('site_role_attributes')) {
            $role_attributes = extract_json(['file' => 'assets/cache/site_role_attributes.cache']);
            Registry::add('site_role_attributes', $role_attributes);
        }
        include('fns/realtime/sys_validate_memberships.php');
    });
}

$ws_instance->start();
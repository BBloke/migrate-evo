<?php

use Illuminate\Database\Schema\Blueprint;

$lang_array = ['ukrainian' => 'uk',
    'svenska' => 'sv', 'svenska-utf8' => 'sv', 'spanish' => 'es', 'spanish-utf8' => 'es', 'simple_chinese-gb2312' => 'zh', 'simple_chinese-gb2312-utf8' => 'zh',
    'russian' => 'ru', 'russian-UTF8' => 'ru', 'portuguese' => 'pt', 'portuguese-br' => 'pt-br', 'portuguese-br-utf8' => 'pt-br',
    'polish' => 'pl', 'polish-utf8' => 'pl', 'persian' => 'fa', 'norsk' => 'no', 'nederlands' => 'nl', 'nederlands-utf8' => 'nl',
    'japanese-utf8' => 'ja', 'italian' => 'it', 'hebrew' => 'he', 'german' => 'de', 'francais' => 'fr', 'francais-utf8' => 'fr',
    'finnish' => 'fi', 'english' => 'en', 'english-british' => 'en', 'danish' => 'da', 'czech' => 'cs', 'chinese' => 'zh', 'bulgarian' => 'bz'];
chdir('../');
$base_dir = getcwd();
$temp_dir = $base_dir . '/_temp' . md5(time());
$config_2_dir = $base_dir . '/core/config/database/connections/default.php';
$database_engine = 'MyISAM';

EvoInstaller::checkVersion($base_dir);
$config = EvoInstaller::checkConfig($base_dir, $config_2_dir, $database_engine);

//run unzip and install
EvoInstaller::downloadFile('https://github.com/evolution-cms/evolution/archive/3.0.zip', 'evo.zip');

$zip = new ZipArchive;
$res = $zip->open($base_dir . '/evo.zip');
$zip->extractTo($temp_dir);
$zip->close();
unlink($base_dir . '/evo.zip');

if ($handle = opendir($temp_dir)) {
    while (false !== ($name = readdir($handle))) {
        if ($name != '.' && $name != '..') $dir = $name;
    }
    closedir($handle);
}

EvoInstaller::rmdirs($base_dir . '/manager');
EvoInstaller::rmdirs($base_dir . '/vendor');
if (file_exists($base_dir . '/assets/cache/siteCache.idx.php'))
    unlink($base_dir . '/assets/cache/siteCache.idx.php');
if (file_exists($base_dir . '/core/storage/bootstrap/siteCache.idx.php')){
    unlink($base_dir . '/core/storage/bootstrap/siteCache.idx.php');
}
if (file_exists($base_dir . '/assets/cache/siteManager.php')) {
    unlink($base_dir . '/assets/cache/siteManager.php');
}
EvoInstaller::moveFiles($temp_dir . '/' . $dir, $base_dir . '/');
if ($config != '') {
    file_put_contents($config_2_dir, $config);
}
EvoInstaller::rmdirs($temp_dir);

unlink(__FILE__);


define('MANAGER_MODE', true);
define('MODX_CLI', true);
define('IN_INSTALL_MODE', true);
define('MODX_API_MODE', true);
include 'index.php';

$modx = EvolutionCMS();


try {
    $dbh = new \PDO($modx->getDatabase()->getConfig('driver') . ':host=' . $modx->getDatabase()->getConfig('host') . ';dbname=' . $modx->getDatabase()->getConfig('database'), $modx->getDatabase()->getConfig('username'), $modx->getDatabase()->getConfig('password'));
} catch (PDOException $exception) {
    echo $exception->getMessage();
    exit();
}


$serverVersion = $dbh->getAttribute(\PDO::ATTR_SERVER_VERSION);
if (stristr($serverVersion, 'MySQL'))
    if (version_compare($serverVersion, '5.6.0', '<')) {

        echo '<span class="notok">Need minimum MySQL 5.6 version</span>';
        exit();
    }
$setting_lang = \DB::table('system_settings')->where('setting_name', 'manager_language')->first();
if (isset($lang_array[$setting_lang->setting_value])) {
    \DB::table('system_settings')->where('setting_name', 'manager_language')->update(['setting_value' => $lang_array[$setting_lang->setting_value]]);
}
$setting_lang = \DB::table('system_settings')->where('setting_name', 'fe_editor_lang')->first();
if (isset($lang_array[$setting_lang->setting_value])) {
    \DB::table('system_settings')->where('setting_name', 'fe_editor_lang')->update(['setting_value' => $lang_array[$setting_lang->setting_value]]);
}
$username = \DB::table('manager_users')->pluck('username');
$emails = \DB::table('user_attributes')->pluck('email');
$checkUsername = \DB::table('web_users')->whereIn('username', $username);
$checkEmails = \DB::table('web_user_attributes')->whereIn('email', $emails);
if ($checkUsername->count() > 0 || $checkEmails->count() > 0) {
    echo 'some manager conflict with web user';
    if ($checkUsername->count() > 0) {
        echo '<table> <thead><th>Manager Id</th><th>Manager username</th><th>Web User Id</th><th>Web user username</th></thead><tbody></tbody>';
        foreach ($checkUsername->get() as $webuser) {
            $webuser = (array)$webuser;
            $user = (array)\DB::table('manager_users')->where('username', $webuser['username'])->first();
            echo '<tr><td>' . $user['id'] . '</td><td>' . $user['username'] . '</td><td>' . $webuser['id'] . '</td><td>' . $webuser['username'] . '</td></tr>';
        }
    }
    if ($checkEmails->count() > 0) {
        echo '</tbody></table>';
        echo '<table> <thead><th>Manager Id</th><th>Manager email</th><th>Web User Id</th><th>Web user email</th></thead><tbody></tbody>';
        foreach ($checkEmails->get() as $webuser) {
            $webuser = (array)$webuser;
            $user = (array)\DB::table('user_attributes')->where('email', $webuser['email'])->first();
            echo '<tr><td>' . $user['internalKey'] . '</td><td>' . $user['email'] . '</td><td>' . $webuser['internalKey'] . '</td><td>' . $webuser['email'] . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    exit();
}

$users = \DB::table('manager_users')->get();

foreach ($users as $user) {

    $userArray = (array)$user;

    $userAttributes = \DB::table('user_attributes')->where('internalKey', $userArray['id'])->first();

    if (!is_null($userAttributes)) {
        $userAttributes = (array)$userAttributes;
    } else {
        $userAttributes = [];
    }

    $userSettings = \DB::table('user_settings')->where('user', $userArray['id'])->get();
    $userMemberGroup = \DB::table('member_groups')->where('member', $userArray['id'])->get();
    $oldId = $userArray['id'];
    unset($userArray['id']);
    unset($userAttributes['id']);
    $id = \DB::table('web_users')->insertGetId($userArray);
    $userAttributes['internalKey'] = $id;

    \DB::table('web_user_attributes')->insert($userAttributes);
    $arraySetting = [];
    foreach ($userSettings as $setting) {
        $setting = (array)$setting;
        $setting['user'] = $id;
        $arraySetting[] = $setting;
    }
    foreach ($userMemberGroup as $group) {
        $group = (array)$group;
        $group['member'] = $id;
        \DB::table('member_groups')->insert($group);
    }
    \DB::table('member_groups')->where('member', $oldId)->delete();

    \DB::table('web_user_settings')->where('webuser', $oldId)->delete();

    foreach ($arraySetting as $setting)
        \DB::table('user_settings')->insert($setting);
}

$managerGroup = \DB::table('membergroup_names')->pluck('id', 'name')->toArray();

$userGroups = \DB::table('webgroup_names')->pluck('name', 'id')->toArray();

$oldNewGroup = [];
foreach ($userGroups as $key => $group) {
    if (isset($managerGroup[$group])) {
        $oldNewGroup[$key] = $managerGroup[$group];
    } else {
        $oldNewGroup[$key] = \DB::table('membergroup_names')->insertGetId(['name' => $group]);
    }
}
$newAccess = [];
$oldWebGroupAccess = \DB::table('webgroup_access')->get();
foreach ($oldWebGroupAccess as $access) {
    $access = (array)$access;
    $newAccess[] = ['membergroup' => $oldNewGroup[$access['webgroup']], 'documentgroup' => $access['documentgroup']];
}
foreach ($newAccess as $access)
    \DB::table('membergroup_access')->insert($access);

$newWebGroupsUsers = [];
$oldWebGroupsUsers = \DB::table('web_groups')->get();
foreach ($oldWebGroupsUsers as $user) {
    $user = (array)$user;
    $newWebGroupsUsers[] = ['user_group' => $oldNewGroup[$user['webgroup']], 'member' => $user['webuser']];
}
foreach ($newWebGroupsUsers as $user) {
    \DB::table('member_groups')->insert($user);
}


if (!Schema::hasTable('migrations_install')) {

    $sql = "DROP TABLE IF EXISTS " . $modx->db->getFullTableName('migrations_install');
    $modx->db->query($sql);
    $sql = "

CREATE TABLE " . $modx->db->getFullTableName('migrations_install') . " (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $modx->db->query($sql);

    $sql2 = "

INSERT INTO " . $modx->db->getFullTableName('migrations_install') . " (`id`, `migration`, `batch`) VALUES
(1,	'2018_06_29_182342_create_active_user_locks_table',	1),
(2,	'2018_06_29_182342_create_active_user_sessions_table',	1),
(3,	'2018_06_29_182342_create_active_users_table',	1),
(4,	'2018_06_29_182342_create_categories_table',	1),
(5,	'2018_06_29_182342_create_document_groups_table',	1),
(6,	'2018_06_29_182342_create_documentgroup_names_table',	1),
(7,	'2018_06_29_182342_create_event_log_table',	1),
(8,	'2018_06_29_182342_create_manager_log_table',	1),
(9,	'2018_06_29_182342_create_manager_users_table',	1),
(10,	'2018_06_29_182342_create_member_groups_table',	1),
(11,	'2018_06_29_182342_create_membergroup_access_table',	1),
(12,	'2018_06_29_182342_create_membergroup_names_table',	1),
(13,	'2018_06_29_182342_create_site_content_table',	1),
(14,	'2018_06_29_182342_create_site_htmlsnippets_table',	1),
(15,	'2018_06_29_182342_create_site_module_access_table',	1),
(16,	'2018_06_29_182342_create_site_module_depobj_table',	1),
(17,	'2018_06_29_182342_create_site_modules_table',	1),
(18,	'2018_06_29_182342_create_site_plugin_events_table',	1),
(19,	'2018_06_29_182342_create_site_plugins_table',	1),
(20,	'2018_06_29_182342_create_site_snippets_table',	1),
(21,	'2018_06_29_182342_create_site_templates_table',	1),
(22,	'2018_06_29_182342_create_site_tmplvar_access_table',	1),
(23,	'2018_06_29_182342_create_site_tmplvar_contentvalues_table',	1),
(24,	'2018_06_29_182342_create_site_tmplvar_templates_table',	1),
(25,	'2018_06_29_182342_create_site_tmplvars_table',	1),
(26,	'2018_06_29_182342_create_system_eventnames_table',	1),
(27,	'2018_06_29_182342_create_system_settings_table',	1),
(28,	'2018_06_29_182342_create_user_attributes_table',	1),
(29,	'2018_06_29_182342_create_user_roles_table',	1),
(30,	'2018_06_29_182342_create_user_settings_table',	1),
(31,	'2018_06_29_182342_create_web_groups_table',	1),
(32,	'2018_06_29_182342_create_web_user_attributes_table',	1),
(33,	'2018_06_29_182342_create_web_user_settings_table',	1),
(34,	'2018_06_29_182342_create_web_users_table',	1),
(35,	'2018_06_29_182342_create_webgroup_access_table',	1),
(36,	'2018_06_29_182342_create_webgroup_names_table',	1);";

    $modx->db->query($sql2);

}

if (!Schema::hasTable('permissions_groups')) {

    Schema::create('permissions_groups', function (Blueprint $table) {
        $table->integer('id', true);
        $table->string('name');
        $table->string('lang_key')->default('');
        $table->timestamps();
    });
    \DB::table('migrations_install')->insert(['migration' => '2018_06_29_182342_create_permissions_groups_table', 'batch' => 1]);
    $insertArray = [
        ['id' => 1, 'name' => 'General', 'lang_key' => 'page_data_general'],
        ['id' => 2, 'name' => 'Content Management', 'lang_key' => 'role_content_management'],
        ['id' => 3, 'name' => 'File Management', 'lang_key' => 'role_file_management'],
        ['id' => 4, 'name' => 'Category Management', 'lang_key' => 'category_management'],
        ['id' => 5, 'name' => 'Module Management', 'lang_key' => 'role_module_management'],
        ['id' => 6, 'name' => 'Template Management', 'lang_key' => 'role_template_management'],
        ['id' => 7, 'name' => 'Snippet Management', 'lang_key' => 'role_snippet_management'],
        ['id' => 8, 'name' => 'Chunk Management', 'lang_key' => 'role_chunk_management'],
        ['id' => 9, 'name' => 'Plugin Management', 'lang_key' => 'role_plugin_management'],
        ['id' => 10, 'name' => 'User Management', 'lang_key' => 'role_user_management'],
        ['id' => 11, 'name' => 'Permissions', 'lang_key' => 'role_udperms'],
        ['id' => 12, 'name' => 'Role Management', 'lang_key' => 'role_role_management'],
        ['id' => 13, 'name' => 'Events Log Management', 'lang_key' => 'role_eventlog_management'],
        ['id' => 14, 'name' => 'Config Management', 'lang_key' => 'role_config_management'],
    ];
    \DB::table('permissions_groups')->insert($insertArray);
}

if (!Schema::hasTable('permissions')) {

    Schema::create('permissions', function (Blueprint $table) {
        $table->integer('id', true);
        $table->string('name');
        $table->string('key');
        $table->string('lang_key')->default('');
        $table->integer('group_id')->nullable();
        $table->integer('disabled')->nullable();
        $table->timestamps();
    });
    \DB::table('migrations_install')->insert(['migration' => '2018_06_29_182342_create_permissions_table', 'batch' => 1]);
    $insertArray = [
        ['name' => 'Request manager frames', 'lang_key' => 'role_frames', 'key' => 'frames', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'Request manager intro page', 'lang_key' => 'role_home', 'key' => 'home', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'Log out of the manager', 'lang_key' => 'role_logout', 'key' => 'logout', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'View help pages', 'lang_key' => 'role_help', 'key' => 'help', 'disabled' => 0, 'group_id' => 1],
        ['name' => 'View action completed screen', 'lang_key' => 'role_actionok', 'key' => 'action_ok', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'View error dialog', 'lang_key' => 'role_errors', 'key' => 'error_dialog', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'View the about page', 'lang_key' => 'role_about', 'key' => 'about', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'View credits', 'lang_key' => 'role_credits', 'key' => 'credits', 'disabled' => 1, 'group_id' => 1],
        ['name' => 'Change password', 'lang_key' => 'role_change_password', 'key' => 'change_password', 'disabled' => 0, 'group_id' => 1],
        ['name' => 'Save password', 'lang_key' => 'role_save_password', 'key' => 'save_password', 'disabled' => 0, 'group_id' => 1],

    ];
    \DB::table('permissions')->insert($insertArray);
    $insertArray = [
        ['name' => 'View a Resource\'s data', 'key' => 'view_document', 'lang_key' => 'role_view_docdata', 'disabled' => 1, 'group_id' => 2],
        ['name' => 'Create new Resources', 'key' => 'new_document', 'lang_key' => 'role_create_doc', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Edit a Resource', 'key' => 'edit_document', 'lang_key' => 'role_edit_doc', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Change Resource-Type', 'key' => 'change_resourcetype', 'lang_key' => 'role_change_resourcetype', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Save Resources', 'key' => 'save_document', 'lang_key' => 'role_save_doc', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Publish Resources', 'key' => 'publish_document', 'lang_key' => 'role_publish_doc', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Delete Resources', 'key' => 'delete_document', 'lang_key' => 'role_delete_doc', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Permanently purge deleted Resources', 'key' => 'empty_trash', 'lang_key' => 'role_empty_trash', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'Empty the site\'s cache', 'key' => 'empty_cache', 'lang_key' => 'role_cache_refresh', 'disabled' => 0, 'group_id' => 2],
        ['name' => 'View Unpublished Resources', 'key' => 'view_unpublished', 'lang_key' => 'role_view_unpublished', 'disabled' => 0, 'group_id' => 2],

    ];
    \DB::table('permissions')->insert($insertArray);
    $insertArray = [
        ['name' => 'Use the file manager (full root access)', 'key' => 'file_manager', 'lang_key' => 'role_file_manager', 'disabled' => 0, 'group_id' => 3],
        ['name' => 'Manage assets/files', 'key' => 'assets_files', 'lang_key' => 'role_assets_files', 'disabled' => 0, 'group_id' => 3],
        ['name' => 'Manage assets/images', 'key' => 'assets_images', 'lang_key' => 'role_assets_images', 'disabled' => 0, 'group_id' => 3],

        ['name' => 'Use the Category Manager', 'key' => 'category_manager', 'lang_key' => 'role_category_manager', 'disabled' => 0, 'group_id' => 4],

        ['name' => 'Create new Module', 'key' => 'new_module', 'lang_key' => 'role_new_module', 'disabled' => 0, 'group_id' => 5],
        ['name' => 'Edit Module', 'key' => 'edit_module', 'lang_key' => 'role_edit_module', 'disabled' => 0, 'group_id' => 5],
        ['name' => 'Save Module', 'key' => 'save_module', 'lang_key' => 'role_save_module', 'disabled' => 0, 'group_id' => 5],
        ['name' => 'Delete Module', 'key' => 'delete_module', 'lang_key' => 'role_delete_module', 'disabled' => 0, 'group_id' => 5],
        ['name' => 'Run Module', 'key' => 'exec_module', 'lang_key' => 'role_run_module', 'disabled' => 0, 'group_id' => 5],
        ['name' => 'List Module', 'key' => 'list_module', 'lang_key' => 'role_list_module', 'disabled' => 0, 'group_id' => 5],

        ['name' => 'Create new site Templates', 'key' => 'new_template', 'lang_key' => 'role_create_template', 'disabled' => 0, 'group_id' => 6],
        ['name' => 'Edit site Templates', 'key' => 'edit_template', 'lang_key' => 'role_edit_template', 'disabled' => 0, 'group_id' => 6],
        ['name' => 'Save Templates', 'key' => 'save_template', 'lang_key' => 'role_save_template', 'disabled' => 0, 'group_id' => 6],
        ['name' => 'Delete Templates', 'key' => 'delete_template', 'lang_key' => 'role_delete_template', 'disabled' => 0, 'group_id' => 6],


    ];
    \DB::table('permissions')->insert($insertArray);

    $insertArray = [
        ['name' => 'Create new Snippets', 'key' => 'new_snippet', 'lang_key' => 'role_create_snippet', 'disabled' => 0, 'group_id' => 7],
        ['name' => 'Edit Snippets', 'key' => 'edit_snippet', 'lang_key' => 'role_edit_snippet', 'disabled' => 0, 'group_id' => 7],
        ['name' => 'Save Snippets', 'key' => 'save_snippet', 'lang_key' => 'role_save_snippet', 'disabled' => 0, 'group_id' => 7],
        ['name' => 'Delete Snippets', 'key' => 'delete_snippet', 'lang_key' => 'role_delete_snippet', 'disabled' => 0, 'group_id' => 7],

        ['name' => 'Create new Chunks', 'key' => 'new_chunk', 'lang_key' => 'role_create_chunk', 'disabled' => 0, 'group_id' => 8],
        ['name' => 'Edit Chunks', 'key' => 'edit_chunk', 'lang_key' => 'role_edit_chunk', 'disabled' => 0, 'group_id' => 8],
        ['name' => 'Save Chunks', 'key' => 'save_chunk', 'lang_key' => 'role_save_chunk', 'disabled' => 0, 'group_id' => 8],
        ['name' => 'Delete Chunks', 'key' => 'delete_chunk', 'lang_key' => 'role_delete_chunk', 'disabled' => 0, 'group_id' => 8],

        ['name' => 'Create new Plugins', 'key' => 'new_plugin', 'lang_key' => 'role_create_plugin', 'disabled' => 0, 'group_id' => 9],
        ['name' => 'Edit Plugins', 'key' => 'edit_plugin', 'lang_key' => 'role_edit_plugin', 'disabled' => 0, 'group_id' => 9],
        ['name' => 'Save Plugins', 'key' => 'save_plugin', 'lang_key' => 'role_save_plugin', 'disabled' => 0, 'group_id' => 9],
        ['name' => 'Delete Plugins', 'key' => 'delete_plugin', 'lang_key' => 'role_delete_plugin', 'disabled' => 0, 'group_id' => 9],

        ['name' => 'Create new users', 'key' => 'new_user', 'lang_key' => 'role_new_user', 'disabled' => 0, 'group_id' => 10],
        ['name' => 'Edit users', 'key' => 'edit_user', 'lang_key' => 'role_edit_user', 'disabled' => 0, 'group_id' => 10],
        ['name' => 'Save users', 'key' => 'save_user', 'lang_key' => 'role_save_user', 'disabled' => 0, 'group_id' => 10],
        ['name' => 'Delete users', 'key' => 'delete_user', 'lang_key' => 'role_delete_user', 'disabled' => 0, 'group_id' => 10],

        ['name' => 'Access permissions', 'key' => 'access_permissions', 'lang_key' => 'role_access_persmissions', 'disabled' => 0, 'group_id' => 11],
        ['name' => 'Web access permissions', 'key' => 'web_access_permissions', 'lang_key' => 'role_web_access_persmissions', 'disabled' => 0, 'group_id' => 11],

    ];
    \DB::table('permissions')->insert($insertArray);

    $insertArray = [
        ['name' => 'Create new roles', 'key' => 'new_role', 'lang_key' => 'role_new_role', 'disabled' => 0, 'group_id' => 12],
        ['name' => 'Edit roles', 'key' => 'edit_role', 'lang_key' => 'role_edit_role', 'disabled' => 0, 'group_id' => 12],
        ['name' => 'Save roles', 'key' => 'save_role', 'lang_key' => 'role_save_role', 'disabled' => 0, 'group_id' => 12],
        ['name' => 'Delete roles', 'key' => 'delete_role', 'lang_key' => 'role_delete_role', 'disabled' => 0, 'group_id' => 12],

        ['name' => 'View event log', 'key' => 'view_eventlog', 'lang_key' => 'role_view_eventlog', 'disabled' => 0, 'group_id' => 13],
        ['name' => 'Delete event log', 'key' => 'delete_eventlog', 'lang_key' => 'role_delete_eventlog', 'disabled' => 0, 'group_id' => 13],

        ['name' => 'View system logs', 'key' => 'logs', 'lang_key' => 'role_view_logs', 'disabled' => 0, 'group_id' => 14],
        ['name' => 'Change site settings', 'key' => 'settings', 'lang_key' => 'role_edit_settings', 'disabled' => 0, 'group_id' => 14],
        ['name' => 'Use the Backup Manager', 'key' => 'bk_manager', 'lang_key' => 'role_bk_manager', 'disabled' => 0, 'group_id' => 14],
        ['name' => 'Remove Locks', 'key' => 'remove_locks', 'lang_key' => 'role_remove_locks', 'disabled' => 0, 'group_id' => 14],
        ['name' => 'Display Locks', 'key' => 'display_locks', 'lang_key' => 'role_display_locks', 'disabled' => 0, 'group_id' => 14],

    ];
    \DB::table('permissions')->insert($insertArray);
}

if (!Schema::hasTable('role_permissions')) {

    Schema::create('role_permissions', function (Blueprint $table) {
        $table->integer('id', true);
        $table->string('permission');
        $table->integer('role_id');
        $table->timestamps();
    });
    \DB::table('migrations_install')->insert(['migration' => '2018_06_29_182342_create_role_permissions_table', 'batch' => 1]);
    $result = \EvolutionCMS\Models\UserRole::get()->toArray();
    foreach ($result as $role) {
        $id = $role['id'];
        unset($role['id']);
        unset($role['name']);
        unset($role['description']);
        foreach ($role as $key => $value) {
            if ($value == 1)
                \DB::table('role_permissions')->insert(['permission' => $key, 'role_id' => $id]);
        }

        if ($role['exec_module'] == 1) {
            \DB::table('role_permissions')->insert(['permission' => 'list_module', 'role_id' => $id]);
        }
        if ($role['new_chunk'] == 1) {
            \DB::table('role_permissions')->insert(['permission' => 'access_permissions', 'role_id' => $id]);
        }
    }

}

$cnt = \DB::table('system_eventnames')->where('name', 'OnBeforeUserSave')->count();
if ($cnt == 0) {
    \DB::table('system_eventnames')->insert(array(
        63 =>
            array(
                'name' => 'OnBeforeUserSave',
                'service' => 1,
                'groupname' => 'Users',
            ),
    ));
}

$cnt = \DB::table('system_eventnames')->where('name', 'OnUserSave')->count();
if ($cnt == 0) {
    \DB::table('system_eventnames')->insert(array(
        63 =>
            array(
                'name' => 'OnUserSave',
                'service' => 1,
                'groupname' => 'Users',
            ),
    ));
}

$cnt = \DB::table('system_eventnames')->where('name', 'OnBeforeUserDelete')->count();
if ($cnt == 0) {
    \DB::table('system_eventnames')->insert(array(
        63 =>
            array(
                'name' => 'OnBeforeUserDelete',
                'service' => 1,
                'groupname' => 'Users',
            ),
    ));
}

$cnt = \DB::table('system_eventnames')->where('name', 'OnUserDelete')->count();
if ($cnt == 0) {
    \DB::table('system_eventnames')->insert(array(
        63 =>
            array(
                'name' => 'OnUserDelete',
                'service' => 1,
                'groupname' => 'Users',
            ),
    ));
}
file_put_contents($base_dir . '/core/.install', time());

header('Location: /install/');

//echo "Site ready to update, please download latest version from github and unpack to server";


class EvoInstaller
{
    static public function downloadFile($url, $path)
    {
        $newfname = $path;
        $rs = file_get_contents($url);
        if ($rs) $rs = file_put_contents($newfname, $rs);
        return $rs;
    }

    static public function rmdirs($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object))
                        self::rmdirs($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    static public function moveFiles($src, $dest)
    {
        $path = realpath($src);
        $dest = realpath($dest);
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($objects as $name => $object) {
            $startsAt = substr(dirname($name), strlen($path));
            self::mmkDir($dest . $startsAt);
            if ($object->isDir()) {
                self::mmkDir($dest . substr($name, strlen($path)));
            }

            if (is_writable($dest . $startsAt) && $object->isFile()) {
                rename((string)$name, $dest . $startsAt . '/' . basename($name));
            }
        }
    }

    static public function mmkDir($folder, $perm = 0777)
    {
        if (!is_dir($folder)) {
            mkdir($folder, $perm);
        }
    }

    static public function checkVersion($base_dir)
    {
        if (file_exists($base_dir . '/core/factory/version.php')) {
            $data = include $base_dir . '/core/factory/version.php';
            if (version_compare($data['version'], '2.0.4', '<')) {
                echo 'Please update to version 2.0.4 before start this script';
                exit();
            } else {
                return;
            }

        }
        if (file_exists($base_dir . '/manager/includes/version.inc.php')) {
            include $base_dir . '/manager/includes/version.inc.php';
            if (version_compare($modx_version, '1.4.12', '<')) {
                echo 'Please update to version 1.4.12 before start this script';
                exit();
            }
        }
    }

    static public function checkConfig($base_dir, $config_2_dir, $database_engine)
    {
        if (file_exists($config_2_dir)) {
            return '';
        }
        $config_file_1_4 = $base_dir . '/manager/includes/config.inc.php';
        if (!file_exists($config_file_1_4)) {
            echo 'config file not exists';
            exit();
        }
        include $config_file_1_4;
        $database_connection_charset_ = 'utf8_general_ci';
        if ($database_connection_charset == 'utf8') {
            $database_connection_charset_ = 'utf8_general_ci';
        }
        if ($database_connection_charset == 'utf8mb4') {
            $database_connection_charset_ = 'utf8mb4_general_ci';
        }
        if ($database_connection_charset == 'cp1251') {
            $database_connection_charset_ = 'cp1251_general_ci';
        }
        $arr_config['[+database_type+]'] = 'mysql';
        $arr_config['[+database_server+]'] = $database_server;
        $arr_config['[+database_port+]'] = 3306;
        $arr_config['[+dbase+]'] = str_replace('`', '', $dbase);
        $arr_config['[+user_name+]'] = $database_user;
        $arr_config['[+password+]'] = $database_password;
        $arr_config['[+connection_charset+]'] = $database_connection_charset;
        $arr_config['[+connection_collation+]'] = $database_connection_charset_;
        $arr_config['[+table_prefix+]'] = $table_prefix;
        $arr_config['[+connection_method+]'] = $database_connection_method;
        $arr_config['[+database_engine+]'] = $database_engine;

        $str = "<?php
return [
    'driver' => env('DB_TYPE', '[+database_type+]'), 
    'host' => env('DB_HOST', '[+database_server+]'), 
    'port' => env('DB_PORT', '[+database_port+]'), 
    'database' => env('DB_DATABASE', '[+dbase+]'), 
    'username' => env('DB_USERNAME', '[+user_name+]'), 
    'password' => env('DB_PASSWORD', '[+password+]'), 
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => env('DB_CHARSET', '[+connection_charset+]'), 
    'collation' => env('DB_COLLATION', '[+connection_collation+]'),
    'prefix' => env('DB_PREFIX', '[+table_prefix+]'),
    'method' => env('DB_METHOD', '[+connection_method+]'), 
    'strict' => env('DB_STRICT', false),
    'engine' => env('DB_ENGINE', '[+database_engine+]'),
    'options' => [
        PDO::ATTR_STRINGIFY_FETCHES => true,
    ]
];";
        $str = str_replace(array_keys($arr_config), array_values($arr_config), $str);

        return $str;
    }
}
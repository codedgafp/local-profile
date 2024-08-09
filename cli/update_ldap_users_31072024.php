<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * 
 * update ldap users with new properties isSuspended, moodleId and affiliatedEntity
 * @package    core
 * @subpackage cli
 * @copyright  2023 Edunao SAS (contact@edunao.com)
 * @author     Adrien Jamot <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define('CLI_SCRIPT', true);

global $DB, $CFG;
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/auth/ldap/auth.php');
require_once($CFG->dirroot . '/user/lib.php');

$total_users = $DB->count_records('user', ['deleted' => 0, 'auth' => 'ldap_syncplus']);
$batch_size = 50;

$fieldmainentity = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);
$fieldrolementor = $DB->get_record('user_info_field', ['shortname' => 'roleMentor']);


for ($offset = 0; $offset < $total_users; $offset += $batch_size) {
    $users = $DB->get_records('user', ['deleted' => 0, 'auth' => 'ldap_syncplus'], '', '*', $offset, $batch_size);

    foreach ($users as $user) {
        $auth = get_auth_plugin('ldap_syncplus');
        $ldap_connection = $auth->ldap_connect();
        if (!$ldap_connection) {
            error_log("Impossible de se connecter au serveur LDAP pour l'utilisateur {$user->username}");
            continue;
        }

        $user_dn = "uid={$user->username}," . get_config('auth_ldap_syncplus', 'contexts');
        if(!$auth->ldap_find_userdn($ldap_connection, core_text::convert($user->username, 'utf-8', get_config('ldapencoding', 'utf-8')))) {
            if(!$auth->user_create($user, $user->password)) error_log(get_string('auth_ldap_create_error', 'auth_ldap'));
            continue;
        }

        $user->profile_field_mainentity = $DB->get_record('user_info_data', ['fieldid' => $fieldmainentity->id, 'userid' => $user->id])->data ?? null;
        $user->profile_field_roleMentor = $DB->get_record('user_info_data', ['fieldid' => $fieldrolementor->id, 'userid' => $user->id])->data ?? null;
        if(!$auth->user_update($user, $user)) error_log("Erreur de mise à jour LDAP pour l'utilisateur {$user->username}: {$ldap_error}");
        
        $auth->ldap_close();
    }

    echo "Traitement du lot d'utilisateurs de $offset à " . ($offset + $batch_size) . " terminé.\n";
}

echo "Mise à jour des utilisateurs LDAP terminée.";
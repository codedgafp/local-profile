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
 * Form for editing a users profile
 *
 * This file is derived from the native user/editadvanced_form.php file
 *
 * @package    local_profile
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Adrien Jamot <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/editadvanced_form.php');

/**
 * Class local_profile_user_form.
 * Extends the native user form class
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_profile_user_form extends user_editadvanced_form {

    /**
     * Define the form.
     */
    public function definition() {
        global $CFG, $COURSE, $DB;

        $mform = $this->_form;
        $editoroptions = null;
        $filemanageroptions = null;

        if (!is_array($this->_customdata)) {
            throw new coding_exception('invalid custom data for user_edit_form');
        }
        $editoroptions = $this->_customdata['editoroptions'];
        $filemanageroptions = $this->_customdata['filemanageroptions'];
        $user = $this->_customdata['user'];
        
        $externalrole = $DB->get_record('role', array('shortname' => 'utilisateurexterne'), '*', MUST_EXIST);
        $user->externaluser = 0;
        $isexternal = $DB->get_record('role_assignments', array('userid' => $user->id, 'roleid' => $externalrole->id));
        if($isexternal) $user->externaluser = 1;
     
 
        $userid = $user->id;


        // Accessibility: "Required" is bad legend text.
        $strgeneral = get_string('general');

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', core_user::get_property_type('id'));
        $mform->addElement('hidden', 'course', $COURSE->id);
        $mform->setType('course', PARAM_INT);

        // Print the required moodle fields first.
        $mform->addElement('header', 'moodle', $strgeneral);

        $auths = core_component::get_plugin_list('auth');
        $enabled = get_string('pluginenabled', 'core_plugin');
        $disabled = get_string('plugindisabled', 'core_plugin');
        $authoptions = [$enabled => [], $disabled => []];
        $cannotchangepass = [];
        $cannotchangeusername = [];
        foreach ($auths as $auth => $unused) {
            $authinst = get_auth_plugin($auth);

            if (!$authinst->is_internal()) {
                $cannotchangeusername[] = $auth;
            }

            $passwordurl = $authinst->change_password_url();
            if (!($authinst->can_change_password() && empty($passwordurl))) {
                // This is unlikely but we can not create account without password
                // when plugin uses passwords, we need to set it initially at least.
                if (!($userid < 1 && $authinst->is_internal())) {
                    $cannotchangepass[] = $auth;
                }
            }
            if (is_enabled_auth($auth)) {
                $authoptions[$enabled][$auth] = get_string('pluginname', "auth_{$auth}");
            } else {
                $authoptions[$disabled][$auth] = get_string('pluginname', "auth_{$auth}");
            }
        }

        $purpose = user_edit_map_field_purpose($userid, 'username');
        $mform->addElement('text', 'username', get_string('username'), 'size="20"' . $purpose);
        $mform->addHelpButton('username', 'username', 'auth');
        $mform->setType('username', PARAM_RAW);

        if ($userid !== -1) {
            $mform->disabledIf('username', 'auth', 'in', $cannotchangeusername);
        }

        $mform->addElement('selectgroups', 'auth', get_string('chooseauthmethod', 'auth'), $authoptions);
        $mform->addHelpButton('auth', 'chooseauthmethod', 'auth');

        $mform->addElement('advcheckbox', 'suspended', get_string('suspended', 'auth'));
        $mform->addHelpButton('suspended', 'suspended', 'auth');

        $mform->addElement('advcheckbox', 'externaluser', get_string('externaluser', 'local_profile'));
    
        $mform->addElement('checkbox', 'createpassword', get_string('createpassword', 'auth'));
        $mform->disabledIf('createpassword', 'auth', 'in', $cannotchangepass);

        if (!empty($CFG->passwordpolicy)) {
            $mform->addElement('static', 'passwordpolicyinfo', '', print_password_policy());
        }

        $purpose = user_edit_map_field_purpose($userid, 'password');
        $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'), 'size="20"' . $purpose);
        $mform->addHelpButton('newpassword', 'newpassword');
        $mform->setType('newpassword', core_user::get_property_type('password'));
        $mform->disabledIf('newpassword', 'createpassword', 'checked');

        $mform->disabledIf('newpassword', 'auth', 'in', $cannotchangepass);

        // Check if the user has active external tokens.
        if ($userid && empty($CFG->passwordchangetokendeletion) && $tokens = webservice::get_active_tokens($userid)) {
            $services = '';
            foreach ($tokens as $token) {
                $services .= format_string($token->servicename) . ',';
            }
            $services = get_string('userservices', 'webservice', rtrim($services, ','));
            $mform->addElement('advcheckbox', 'signoutofotherservices', get_string('signoutofotherservices'), $services);
            $mform->addHelpButton('signoutofotherservices', 'signoutofotherservices');
            $mform->disabledIf('signoutofotherservices', 'newpassword', 'eq', '');
            $mform->setDefault('signoutofotherservices', 1);
        }

        $mform->addElement('advcheckbox', 'preference_auth_forcepasswordchange', get_string('forcepasswordchange'));
        $mform->addHelpButton('preference_auth_forcepasswordchange', 'forcepasswordchange');
        $mform->disabledIf('preference_auth_forcepasswordchange', 'createpassword', 'checked');

        // Shared fields.
        useredit_shared_definition($mform, $editoroptions, $filemanageroptions, $user);

        // Update the cancel pending email modification.
        if (($user->id > 0) && !empty($CFG->emailchangeconfirmation) && !empty($user->preference_newemail)) {
            $emailpending = $mform->getElement('emailpending');

            $personalcontext = context_user::instance($user->id);
            if (has_capability('moodle/user:editprofile', $personalcontext)) {
                $notice = get_string('emailchangepending', 'auth', $user);
                $notice .= '<br /><a href="' . $CFG->wwwroot . '/user/edit.php?cancelemailchange=1&amp;id=' . $user->id . '">'
                           . get_string('emailchangecancel', 'auth') . '</a>';
            } else {
                $notice = $user->email . ' (' . get_string('emailchangepending', 'local_profile', $user) . ')';
            }

            $emailpending->setValue($notice);
        }

        // Next the customisable profile fields.
        local_profile_get_additional_field_definition($mform, $userid);

        if ($userid == -1) {
            $btnstring = get_string('createuser');
        } else {
            $btnstring = get_string('updatemyprofile');
        }

        $this->add_action_buttons(true, $btnstring);
        $this->set_data($user);
    }
}



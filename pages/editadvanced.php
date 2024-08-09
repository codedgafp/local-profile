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
 * Allows you to edit a users profile
 * This file is derived from the native user/editadvanced.php file
 *
 * @package    local_profile
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Adrien Jamot <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/gdlib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/local/profile/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/profile/forms/editadvanced_form.php');


$id = optional_param('id', $USER->id, PARAM_INT);    // User id; -1 if creating new user.
$course = optional_param('course', SITEID, PARAM_INT);   // Course id (defaults to Site).

// PATCH EDUNAO.
$returnto = optional_param('returnto', null, PARAM_RAW);  // Code determining where to return to after save.
$mainentity = optional_param('mainentity', null, PARAM_RAW);  // Default main entity for the new user.
// END patch.

$PAGE->set_url('/local/profile/pages/editadvanced.php', ['course' => $course, 'id' => $id]);

// Check if user has capability for update user profile.
if ($id > 0 && !\local_mentor_core\profile_api::has_profile_config_access($id) && !is_siteadmin()) {
    require_login();
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('notaccess', 'local_mentor_core'));
    $PAGE->navbar->add(get_string('notaccesstitle', 'local_mentor_core'));
    $PAGE->set_heading(get_string('notaccess', 'local_mentor_core'));
    echo $OUTPUT->header();
    \core\notification::error(get_string('notaccess', 'local_mentor_core'));
    echo $OUTPUT->footer();
    die;
} else {
    $course = $DB->get_record('course', ['id' => $course], '*', MUST_EXIST);

    if (!empty($USER->newadminuser)) {
        // Ignore double clicks, we must finish all operations before cancelling request.
        ignore_user_abort(true);

        $PAGE->set_course($SITE);
        $PAGE->set_pagelayout('maintenance');
    } else {
        if ($course->id == SITEID) {
            require_login();
            $PAGE->set_context(context_system::instance());
        } else {
            require_login($course);
        }
        $PAGE->set_pagelayout('admin');
    }

    if ($course->id == SITEID) {
        $coursecontext = context_system::instance();   // SYSTEM context.
    } else {
        $coursecontext = context_course::instance($course->id);   // Course context.
    }

    $systemcontext = context_system::instance();

    // Patch Edunao.
    if ($entities = \local_mentor_core\entity_api::get_managed_entities(null, true)) {
        $entity = current($entities);
        $systemcontext = context_coursecat::instance($entity->id);
    }

    // End patch.

    if ($id == -1) {

        // Creating new user.
        $user = new stdClass();
        $user->id = -1;
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->deleted = 0;
        $user->timezone = '99';
        require_capability('moodle/user:create', $systemcontext);

        // Set the page titles.
        $PAGE->set_title(get_string('adduser', 'local_profile'));
        $PAGE->set_heading(get_string('adduser', 'local_profile'));

    } else {

        // Editing existing user.
        require_capability('moodle/user:update', $systemcontext);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        $PAGE->set_context(context_user::instance($user->id));
        $PAGE->navbar->includesettingsbase = true;
        if ($user->id != $USER->id) {
            $PAGE->navigation->extend_for_user($user);
        } else {
            if ($node = $PAGE->navigation->find('myprofile', navigation_node::TYPE_ROOTNODE)) {
                $node->force_open();
            }
        }
    }

    // Remote users cannot be edited.
    if ($user->id != -1 && is_mnet_remote_user($user)) {
        redirect($CFG->wwwroot . "/user/view.php?id=$id&course={$course->id}");
    }

    if ($user->id != $USER->id && is_siteadmin($user) && !is_siteadmin($USER)) {  // Only admins may edit other admins.
        throw new \moodle_exception('useradmineditadmin');
    }

    if (isguestuser($user->id)) { // The real guest user can not be edited.
        throw new \moodle_exception('guestnoeditprofileother');
    }

    if ($user->deleted) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('userdeleted'));
        echo $OUTPUT->footer();
        die;
    }

    // Load user preferences.
    useredit_load_preferences($user);

    // Load custom profile fields data.
    profile_load_data($user);

    // User interests.
    $user->interests = core_tag_tag::get_item_tags_array('core', 'user', $id);

    if ($user->id !== -1) {
        $usercontext = context_user::instance($user->id);
        $editoroptions = [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => false,
            'forcehttps' => false,
            'context' => $usercontext,
        ];

        $user = file_prepare_standard_editor($user, 'description', $editoroptions, $usercontext, 'user', 'profile', 0);
    } else {
        $usercontext = null;
        // This is a new user, we don't want to add files here.
        $editoroptions = [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'trusttext' => false,
            'forcehttps' => false,
            'context' => $coursecontext,
        ];
    }

    // Prepare filemanager draft area.
    $draftitemid = 0;
    $filemanagercontext = $editoroptions['context'];
    $filemanageroptions = [
        'maxbytes' => $CFG->maxbytes,
        'subdirs' => 0,
        'maxfiles' => 1,
        'accepted_types' => 'optimised_image',
    ];
    file_prepare_draft_area($draftitemid, $filemanagercontext->id, 'user', 'newicon', 0, $filemanageroptions);
    $user->imagefile = $draftitemid;

    if (!empty($mainentity)) {
        $entity = \local_mentor_core\entity_api::get_entity($mainentity);
        $user->profile_field_mainentity = $entity->name;
    }

    // Create form.
    $userform = new local_profile_user_form(new moodle_url($PAGE->url, ['returnto' => $returnto]),
        [
            'editoroptions' => $editoroptions,
            'filemanageroptions' => $filemanageroptions,
            'user' => $user,
        ]);

    // Deciding where to send the user back in most cases.

    if ($returnto === 'profile') {

        if ($course->id != SITEID) {
            $returnurl = new moodle_url(' / user / view . php', ['id' => $user->id, 'course' => $course->id]);
        } else {
            $returnurl = new moodle_url(' / user / profile . php', ['id' => $user->id]);
        }
    } else if (!empty($returnto)) {
        $returnurl = $returnto;
    } else {
        $returnurl = new moodle_url(' / user / preferences . php', ['userid' => $user->id]);
    }

    if ($userform->is_cancelled()) {
        redirect($returnurl);
    } else if ($usernew = $userform->get_data()) {
        $usercreated = false;

        if (empty($usernew->auth)) {
            // User editing self.
            $authplugin = get_auth_plugin($user->auth);
            unset($usernew->auth); // Can not change/remove.
        } else {
            $authplugin = get_auth_plugin($usernew->auth);
        }

        $usernew->timemodified = time();
        $createpassword = false;

        if ($usernew->id == -1) {
            unset($usernew->id);
            $createpassword = !empty($usernew->createpassword);
            unset($usernew->createpassword);
            $usernew = file_postupdate_standard_editor($usernew, 'description', $editoroptions, null, 'user', 'profile',
                null);
            $usernew->username = local_mentor_core_mail_to_username($usernew->email);

            $usernew->mnethostid = $CFG->mnet_localhost_id; // Always local user.
            $usernew->confirmed = 1;
            $usernew->timecreated = time();
            if ($authplugin->is_internal()) {
                if ($createpassword || empty($usernew->newpassword)) {
                    $usernew->password = '';
                } else {
                    $usernew->password = hash_internal_user_password($usernew->newpassword);
                }
            } else {
                $usernew->password = AUTH_PASSWORD_NOT_CACHED;

                // Patch Mentor : si pas de mot de passe fourni.
                if ($createpassword || empty($usernew->newpassword)) {
                    $usernew->newpassword = '';
                }
                // Fin patch.
            }

            // Patch mentor : création du compte dans le LDAP.
            if (!$authplugin->user_create($usernew, $usernew->newpassword)) {
                throw new \moodle_exception('cannotupdateuseronexauth', '', '', $user->auth);
            }
            // Fin patch.

            $usernew->id = user_create_user($usernew, false, false);

            if (!$authplugin->is_internal() && $authplugin->can_change_password()
                && !empty($usernew->newpassword) && !$authplugin->user_update_password($usernew, $usernew->newpassword)
            ) {
                // Do not stop here, we need to finish user creation.
                debugging(get_string('cannotupdatepasswordonextauth', '', $usernew->auth), DEBUG_NONE);
            }
            $usercreated = true;
        } else {
            
            $usernew = file_postupdate_standard_editor($usernew, 'description', $editoroptions, $usercontext, 'user',
                'profile', 0);
               
            // Handle external user checkbox
            if(isset($usernew->externaluser)){
                $externalrole =  \local_mentor_core\profile_api::get_role_by_name('utilisateurexterne');
                $fieldrole = $DB->get_record('user_info_field', ['shortname' => 'roleMentor']);
                $context = context_system::instance();
                if($usernew->externaluser == '1'){
                    role_assign($externalrole->id, $usernew->id, $context->id);
                    $usernew->profile_field_roleMentor = $externalrole->shortname;
                } else {
                    role_unassign($externalrole->id, $usernew->id, $context->id); 
                    $newuserfieldrolementor = $DB->get_record('user_info_data', ['userid' => $usernew->id, 'fieldid' => $fieldrole->id]);
                    $usernew->profile_field_roleMentor = $newuserfieldrolementor->data;
                }
            }
          
            // Pass a true old $user here.
            if (!$authplugin->user_update($user, $usernew)) {
                // Auth update failed.
                throw new \moodle_exception('cannotupdateuseronexauth', 'error', $user->auth);
            }
            
            user_update_user($usernew, false, false);

            // Account is suspended, send email to user.
            if ($user->suspended == 0 && $usernew->suspended == 1) {
                $supportuser = \core_user::get_support_user();

                $object = get_string('disabledaccountobject', 'local_profile');

                $a = new stdClass();
                $a->wwwroot = $CFG->wwwroot;

                // Get the content of the email.
                $content = get_string('disabledaccountcontent', 'local_profile', $a);
                $contenthtml = text_to_html($content, false, false, true);

                email_to_user($user, $supportuser, $object, $content, $contenthtml);
            }

            // Account is unsuspended, send email to user.
            if ($user->suspended == 1 && $usernew->suspended == 0) {
                $supportuser = \core_user::get_support_user();

                $object = get_string('enabledaccountobject', 'local_profile');

                $a = new stdClass();
                $a->wwwroot = $CFG->wwwroot;
                $a->forgetpasswordurl = $CFG->wwwroot . '/login/forgot_password.php';

                // Get the content of the email.
                $content = get_string('enabledaccountcontent', 'local_profile', $a);
                $contenthtml = text_to_html($content, false, false, true);

                // Suspended users cannot receive emails.
                $emailuser = clone($user);
                $emailuser->suspended = 0;

                email_to_user($emailuser, $supportuser, $object, $content, $contenthtml);
            }

            // Set new password if specified.
            if (!empty($usernew->newpassword) && $authplugin->can_change_password()) {
                if (!$authplugin->user_update_password($usernew, $usernew->newpassword)) {
                    throw new \moodle_exception('cannotupdatepasswordonextauth', 'error', $usernew->auth);
                }
                unset_user_preference('create_password', $usernew); // Prevent cron from generating the password.

                if (!empty($CFG->passwordchangelogout)) {
                    // We can use SID of other user safely here because they are unique,.
                    // the problem here is we do not want to logout admin here when changing own password.
                    \core\session\manager::kill_user_sessions($usernew->id, session_id());
                }
                if (!empty($usernew->signoutofotherservices)) {
                    webservice::delete_user_ws_tokens($usernew->id);
                }
            }

            // Force logout if user just suspended.
            if (isset($usernew->suspended) && $usernew->suspended && !$user->suspended) {
                \core\session\manager::kill_user_sessions($user->id);
            }
        }

        $usercontext = context_user::instance($usernew->id);

        // Update preferences.
        useredit_update_user_preference($usernew);

        // Fix auth_forcepasswordchange permission.
        if (property_exists($usernew, 'preference_auth_forcepasswordchange')) {
            set_user_preference('auth_forcepasswordchange', $usernew->preference_auth_forcepasswordchange, $usernew->id);
        }

        // Update tags.
        if (empty($USER->newadminuser) && isset($usernew->interests)) {
            useredit_update_interests($usernew, $usernew->interests);
        }

        // Update user picture.
        if (empty($USER->newadminuser)) {
            core_user::update_picture($usernew, $filemanageroptions);
        }

        // Update mail bounces.
        useredit_update_bounces($user, $usernew);

        // Update forum track preference.
        useredit_update_trackforums($user, $usernew);

        // Save custom profile fields data.
        profile_save_data($usernew);
       
        // Reload from db.
        $usernewreload = $DB->get_record('user', ['id' => $usernew->id]);

        if ($createpassword) {
            setnew_password_and_mail($usernewreload);
            unset_user_preference('create_password', $usernewreload);
            set_user_preference('auth_forcepasswordchange', 1, $usernewreload);
        }

        // Trigger update/create event, after all fields are stored.
        if ($usercreated) {
            \core\event\user_created::create_from_userid($usernewreload->id)->trigger();
        } else {

            // Create data for user_updated event.
            // WARNING : other event data must be compatible with json encoding.
            $otherdata = json_encode(
                [
                    'old' => $user,
                    'new' => $usernew,
                ]
            );
            $data = [
                'objectid' => $usernew->id,
                'relateduserid' => $usernew->id,
                'context' => \context_user::instance($usernew->id),
                'other' => $otherdata,
            ];

            // Create and trigger event.
            \core\event\user_updated::create($data)->trigger();
        }

        if ($user->id == $USER->id) {
            // Override old $USER session variable.
            foreach ((array) $usernewreload as $variable => $value) {
                if ($variable === 'description' || $variable === 'password') {
                    // These are not set for security nad perf reasons.
                    continue;
                }
                $USER->$variable = $value;
            }
            // Preload custom fields.
            profile_load_custom_fields($USER);

            if (!empty($USER->newadminuser)) {
                unset($USER->newadminuser);
                // Apply defaults again - some of them might depend on admin user info, backup, roles, etc.
                admin_apply_default_settings(null, false);
                // Admin account is fully configured - set flag here in case the redirect does not work.
                unset_config('adminsetuppending');
                // Redirect to admin/ to continue with installation.
                redirect("$CFG->wwwroot/$CFG->admin/");
            } else if (empty($SITE->fullname)) {
                // Somebody double clicked when editing admin user during install.
                redirect("$CFG->wwwroot/$CFG->admin/");
            } else {
                redirect($returnurl);
            }
        } else {
            \core\session\manager::gc(); // Remove stale sessions.
           
            // Patch Edunao.
            if (!empty($returnurl)) {
                redirect($returnurl);
            }
            // End Patch.
            redirect("$CFG->wwwroot/$CFG->admin/user.php");
        }
        // Never reached..
    }

    // Add page JS.
    $PAGE->requires->strings_for_js([
        'warning',
        'suspenduserwarning',
        'unsuspenduserwarning',
        'changemainentity',
        'changesecondaryentities',
        'userreceivenotification',
        'wanttocontinue',
        'externaluser',
    ], 'local_profile');
    $PAGE->requires->js_call_amd('local_profile/profile', 'init');

    // Display page header.
    if ($user->id == -1 || ($user->id != $USER->id)) {
        if ($user->id == -1) {
            echo $OUTPUT->header();
        } else {
            $streditmyprofile = get_string('editmyprofile');
            $userfullname = fullname($user, true);
            $PAGE->set_heading($userfullname);
            $PAGE->set_title("$course->shortname: $streditmyprofile - $userfullname");
            echo $OUTPUT->header();
            echo $OUTPUT->heading($userfullname);
        }
    } else if (!empty($USER->newadminuser)) {
        $strinstallation = get_string('installation', 'install');
        $strprimaryadminsetup = get_string('primaryadminsetup');

        $PAGE->navbar->add($strprimaryadminsetup);
        $PAGE->set_title($strinstallation);
        $PAGE->set_heading($strinstallation);
        $PAGE->set_cacheable(false);

        echo $OUTPUT->header();
        echo $OUTPUT->box(get_string('configintroadmin', 'admin'), 'generalbox boxwidthnormal boxaligncenter');
        echo ' < br />';
    } else {
        $streditmyprofile = get_string('editmyprofile');
        $strparticipants = get_string('participants');
        $strnewuser = get_string('newuser');
        $userfullname = fullname($user, true);

        $PAGE->set_title("$course->shortname: $streditmyprofile");
        $PAGE->set_heading($userfullname);

        echo $OUTPUT->header();
        echo $OUTPUT->heading($streditmyprofile);
    }

    // Finally display THE form.
    $userform->display();

    require_once($CFG->dirroot . '/local/mentor_specialization/lib.php');

    // Add the regions and departments list to load it into the dropdown lists.
    echo '<div id="regions" style="display:none;">' . json_encode(local_mentor_specialization_get_regions_and_departments()) .
         '</div>';
}

// And proper footer.
echo $OUTPUT->footer();


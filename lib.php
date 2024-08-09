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
 * PLugin library
 *
 * @package    local_profile
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     nabil <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get profile additional field
 * This function is a copy of the standard profile_definition function
 *
 * @param local_profile_user_form $mform
 * @param int $userid
 * @throws coding_exception
 */
function local_profile_get_additional_field_definition($mform, $userid = 0) {
    $categories = profile_get_user_fields_with_data_by_category($userid);

    foreach ($categories as $categoryid => $fields) {
        // Check first if *any* fields will be displayed.
        $fieldstodisplay = [];

        foreach ($fields as $formfield) {
            $fieldstodisplay[] = $formfield;
        }

        if (empty($fieldstodisplay)) {
            continue;
        }

        // Display the header and the fields.
        $mform->addElement('header', 'category_' . $categoryid, format_string($fields[0]->get_category_name()));
        foreach ($fieldstodisplay as $formfield) {

            if (!$formfield->is_visible()) {
                continue;
            }

            $formfield->edit_field_add($mform);

            $formfield->edit_field_set_default($mform);
            $formfield->edit_field_set_required($mform);

            if ($formfield->is_required()) {
                $mform->addRule($formfield->inputname, get_string('required'), 'required', null, 'client');
            }
        }
    }
}

/**
 * Delete existed fields
 *
 * @param string $shortname
 * @return bool
 * @throws dml_exception
 */
function local_profile_remove_user_info_field_if_exist($shortname) {
    global $DB;
    return $DB->delete_records('user_info_field', ['shortname' => $shortname]);
}


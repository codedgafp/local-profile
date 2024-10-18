/**
 * User profile JS
 */
define([
    'jquery',
    'jqueryui',
    'local_mentor_core/mentor'
], function ($, ui, mentor) {
    return {
        init: function () {
            var that = this;
            this.userDisabled = $('#id_suspended').is(':checked');
            var oldMainEntity = $('#id_profile_field_mainentity').val();
            var oldSecondaryEntities = $('#id_profile_field_secondaryentities').val();

            // Manage suspended user account.
            $('.mform').on('submit', function (eventForm) {

                if (
                    // Cancel button.
                    eventForm.originalEvent.submitter.id === 'id_cancel' ||
                    // Required field.
                    $(eventForm.target).find('.is-invalid').length
                ) {
                    return;
                }

                eventForm.preventDefault();

                var message = '';
                var isDisabled = $('#id_suspended').is(':checked');

                if (that.userDisabled && (that.userDisabled != isDisabled)) {
                    // Unsuspended user.
                    message += '<div>' + M.util.get_string('unsuspenduserwarning', 'local_profile') + '</div>';
                } else if (!that.userDisabled && (that.userDisabled != isDisabled)) {
                    // Suspended user.
                    message += '<div>' + M.util.get_string('suspenduserwarning', 'local_profile') + '</div>';
                }

                // Check if is same main entity.
                if (oldMainEntity !== $('#id_profile_field_mainentity').val()) {
                    // Main entity has been changed.
                    message += '<div>' + M.util.get_string('changemainentity', 'local_profile') + '</div>';
                }

                // Check if is same secondary entities.
                var newSecondaryEntities = $('#id_profile_field_secondaryentities').val();
                if (
                    !((oldSecondaryEntities.length === newSecondaryEntities.length) &&
                        oldSecondaryEntities.every(function (element, index) {
                            return element === newSecondaryEntities[index];
                        }))
                ) {
                    // Secondary entities have been changed.
                    message += '<div>' + M.util.get_string('changesecondaryentities', 'local_profile') + '</div>';
                }

                if (message.length) {
                    message += '<div>' + M.util.get_string('userreceivenotification', 'local_profile') + '</div>';
                    that.warningPopup(message, eventForm);
                } else {
                    eventForm.target.submit();
                }
            });
        },
        /**
         * Open warning user modal.
         * @param {string} message
         * @param eventForm
         */
        warningPopup: function (message, eventForm) {
            mentor.dialog($('' +
                    '<div class="warning-popup-message">' +
                    message + '<div>' + M.util.get_string('wanttocontinue', 'local_profile') + '</div>' +
                    '</div>'
                ), {
                    title: M.util.get_string('warning', 'local_profile'),
                    width: 500,
                    buttons: [
                        {
                            // Confirm button
                            text: M.util.get_string('yes', 'moodle'),
                            class: "btn btn-primary",
                            click: function () {
                                eventForm.target.submit();
                            }
                        },
                        {
                            // Cancel button
                            text: M.util.get_string('cancel', 'moodle'),
                            class: "btn btn-secondary",
                            click: function () {//Just close the modal
                                $(this).dialog("destroy");
                            }
                        }
                    ]
                }
            );
        },
    };
});
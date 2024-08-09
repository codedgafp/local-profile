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
 * Lang strings
 *
 * @package    local_profile
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Profil utilisateur';
$string['adduser'] = 'Ajouter un utilisateur';
$string['emailchangepending']
    = 'Modification en cours. Un lien de validation a été envoyé à l\'adresse : {$a->preference_newemail}.';

$string['warning'] = 'Avertissement';
$string['suspenduserwarning']
    = 'Attention, vous allez suspendre un compte utilisateur. L\'utilisateur ne pourra plus se connecter à Mentor.
Si vous ne souhaitez pas continuer, veuillez cliquer sur Annuler et décocher la case Compte suspendu.';
$string['unsuspenduserwarning']
    = 'Attention, vous allez réactiver un compte utilisateur.';
$string['externaluser'] = 'Utilisateur externe';
$string['changemainentity']
    = 'Attention, vous allez modifier l\'entité principale de l\'utilisateur. Vous ne pourrez plus modifier cet utilisateur si vous n\'êtes pas administrateur de la nouvelle entité principale.
Si vous ne souhaitez pas continuer, veuillez cliquer sur Annuler et remettre à jour l\'entité principale avec sa valeur précédente.';
$string['changesecondaryentities']
    = 'Attention, vous allez modifier une ou plusieurs entités secondaires de l\'utilisateur.';
$string['userreceivenotification']
    = 'L\'utilisateur recevra une notification des modifications effectuées sur son profil.';
$string['wanttocontinue'] = 'Voulez-vous continuer ?';
$string['disabledaccountobject'] = 'Mentor : Suspension de votre compte';
$string['disabledaccountcontent'] = 'Bonjour,

Votre compte utilisateur sur la plateforme Mentor ({$a->wwwroot}) a été suspendu par un administrateur.

Si vous avez besoin d\'accéder de nouveau à Mentor, veuillez contacter votre gestionnaire ou responsable de formation.

L\'équipe du programme Mentor';
$string['enabledaccountobject'] = 'Mentor : Réactivation de votre compte';
$string['enabledaccountcontent'] = 'Bonjour,

Votre compte utilisateur sur la plateforme Mentor ({$a->wwwroot}) a été réactivé par un administrateur.

Si vous rencontrez des difficultés d\'accès, veuillez contacter votre gestionnaire ou responsable de formation.

Vous pouvez aussi utiliser le lien mot de passe oublié : {$a->forgetpasswordurl}

L\'équipe du programme Mentor';
$string['mainentity'] = 'Entité de rattachement principale';
$string['secondaryentities'] = 'Entité(s) de rattachement secondaire(s)';

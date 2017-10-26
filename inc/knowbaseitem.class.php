<?php
/**
 * @version $Id$
 -------------------------------------------------------------------------
 LICENSE

 This file is part of PDF plugin for GLPI.

 PDF is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 PDF is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @package   pdf
 @authors   Nelly Mahu-Lasson, Remi Collet
 @copyright Copyright (c) 2009-2017 PDF plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/pdf
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
*/


class PluginPdfKnowbaseItem extends PluginPdfCommon {


   static $rightname = "plugin_pdf";


   function __construct(CommonGLPI $obj=NULL) {
      $this->obj = ($obj ? $obj : new KnowbaseItem());
   }


   function defineAllTabs($options=[]) {

      $onglets = parent::defineAllTabs($options);
      unset($onglets['KnowbaseItem$3']);
      return $onglets;
   }


   static function pdfMain(PluginPdfSimplePDF $pdf, KnowbaseItem $item){
      global $DB;

      $ID = $item->getField('id');

      if (!Session::haveRightsOr('knowbase',
                                 [READ, KnowbaseItem::READFAQ, KnowbaseItem::KNOWBASEADMIN])) {
         return false;
      }

      $knowbaseitemcategories_id = $item->getField('knowbaseitemcategories_id');
      $fullcategoryname = Html::clean(getTreeValueCompleteName("glpi_knowbaseitemcategories",
                                                   $knowbaseitemcategories_id));

      $question = Html::clean(Toolbox::unclean_cross_side_scripting_deep(
                  html_entity_decode($item->getField('name'),
                                          ENT_QUOTES, "UTF-8")));

      $answer = Html::clean(Toolbox::unclean_cross_side_scripting_deep(
                  html_entity_decode($item->getField('answer'), ENT_QUOTES, "UTF-8")));

      $pdf->setColumnsSize(100);

      if (Toolbox::strlen($fullcategoryname) > 0) {
         $pdf->displayTitle('<b>'.__('Category name').'</b>');
         $pdf->displayLine($fullcategoryname);
      }

      if (Toolbox::strlen($question) > 0) {
         $pdf->displayTitle('<b>'.__('Subject').'</b>');
         $pdf->displayText('', $question, 5);
      } else {
         $pdf->displayTitle('<b>'.__('No question found', 'pdf').'</b>');
      }

      if (Toolbox::strlen($answer) > 0) {
         $pdf->displayTitle('<b>'.__('Content').'</b>');
         $pdf->displayText('', $answer, 5);
      } else {
         $pdf->displayTitle('<b>'.__('No answer found').'</b>');
      }

      $pdf->setColumnsSize(50,15,15,10,10);
      $pdf->displayTitle(__('Writer'), __('Creation date'), __('Last update'), __('FAQ'),
                         _n('View', 'Views', 2));
      $pdf->displayLine(getUserName($item->fields["users_id"]),
                        Html::convDateTime($item->fields["date"]),
                        Html::convDateTime($item->fields["date_mod"]),
                        Dropdown::getYesNo($item->fields["is_faq"]),
                        $item->fields["view"]);

      $pdf->displaySpace();
   }


   static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab) {

      switch ($tab) {
         case 'KnowbaseItem$1' :
            self::pdfMain($pdf, $item);
            break;

         case 'KnowbaseItem$2' :
            self::pdfCible($pdf, $item);
            break;

         case 'Document$1' :
            PluginPdfDocument::pdfForItem($pdf, $item);
            break;

         default :
            return false;
      }
      return true;
   }

   /**
    * @since version 0.85
   **/
   static function pdfCible(PluginPdfSimplePDF $pdf, KnowbaseItem $item) {
      global $DB;

      $ID = $item->getField('id');

      if (!Session::haveRightsOr('knowbase',
                                 [READ, KnowbaseItem::READFAQ, KnowbaseItem::KNOWBASEADMIN])) {
         return false;
      }

      $users    = KnowbaseItem_User::getUsers($ID);
      $entities = Entity_KnowbaseItem::getEntities($ID);
      $groups   = Group_KnowbaseItem::getGroups($ID);
      $profiles = KnowbaseItem_Profile::getProfiles($ID);

      $nb = $item->countVisibilities();
      if ($nb) {
         $pdf->setColumnsSize(100);
         $pdf->displayTitle(_n('Target','Targets',$nb));

         $pdf->setColumnsSize(30,70);
         $pdf->displayTitle(__('Type'),__('Name'));

         $recursive = '';
         if (count($entities)) {
            foreach ($entities as $key => $val) {
               foreach ($val as $data) {
                  if ($data['is_recursive']) {
                     $recursive = "(". __('R').")";
                  }
                  $pdf->displayLine(__('Entity'),
                                    sprintf(__('%1s %2s'),
                                            Dropdown::getDropdownName("glpi_entities",
                                                                      $data["entities_id"]),
                                            $recursive));
               }
            }
         }

         if (count($profiles)) {
            foreach ($profiles as $key => $val) {
               foreach ($val as $data) {
                  if ($data['is_recursive']) {
                     $recursive = "(". __('R').")";
                  }
                  $names       = Dropdown::getDropdownName('glpi_profiles', $data['profiles_id']);
                  if ($data['entities_id'] >= 0) {
                     $profilename = sprintf(__('%1$s / %2$s'), $names,
                                            Dropdown::getDropdownName('glpi_entities',
                                                                      $data['entities_id']));
                     if ($data['is_recursive']) {
                        $profilename = sprintf(__('%1$s %2$s'), $profilename, $recursive);
                     }
                  }
                  $pdf->displayLine(__('Profile'), $profilename);
               }
            }
         }

         if (count($groups)) {
            foreach ($groups as $key => $val) {
               foreach ($val as $data) {
                  if ($data['is_recursive']) {
                     $recursive = "(". __('R').")";
                  }
                  $names = Dropdown::getDropdownName('glpi_groups', $data['groups_id']);
                  if ($data['entities_id'] >= 0) {
                     $groupname = sprintf(__('%1$s / %2$s'), $names,
                                          Dropdown::getDropdownName('glpi_entities',
                                                                    $data['entities_id']));
                     if ($data['is_recursive']) {
                        $groupname = sprintf(__('%1$s %2$s'), $groupname, $recursive);
                     }
                  }
                  $pdf->displayLine(__('Group'), $groupname);
               }
            }
         }

         if (count($users)) {
            foreach ($users as $key => $val) {
               foreach ($val as $data) {
                  $pdf->displayLine(__('User'), getUserName($data['users_id']));
               }
            }
         }

      }
   }
}
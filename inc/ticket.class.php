<?php
/**
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
 @copyright Copyright (c) 2009-2022 PDF plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/pdf
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
*/


class PluginPdfTicket extends PluginPdfCommon
{


   static $rightname = "plugin_pdf";


   function __construct(CommonGLPI $obj = NULL)
   {
      $this->obj = ($obj ? $obj : new Ticket());
   }

   static function formatDate($val, $format): string
   {
      if ($val == null)
         return '';

      $result = '';

      try {
         $dateTime = new DateTime($val);

         switch ($format) {
            case 'datetime':
               $result = $dateTime->format('d/m/Y H:i');
               break;
            case 'time':
               $result = $dateTime->format('H:i');
               break;
            case 'date':
               $result = $dateTime->format('d/m/Y');
               break;
         }


         return $result;
      } catch (\Throwable $th) {
         return $val;
      }
   }


   static function getAdditionalFieldsDropdownValue($id, $val, $fieldName, $field): string
   {
      global $DB;

      if ($val) {
         try {
            $dropdownId = $val["plugin_fields_" . $fieldName . "dropdowns_id"];


            $query = "SELECT * FROM glpi_plugin_fields_{$fieldName}dropdowns WHERE id = $dropdownId limit 1";

            $res = $DB->query($query);

            $result = $DB->fetchAssoc($res);

            // die(json_encode(["plugin_fields_" . $fieldName . "dropdowns_id", $val, $dropdownId, $query, $result]));

            if (array_key_exists('completename', $result)) {
               return $result['completename'];
            }
         } catch (\Throwable $th) {
         }
      }

      return '';
   }

   static function getAdditionalFieldsForTicket(Ticket $ticket, array &$fieldValues = [], array &$fieldsInfo = []): void
   {
      $plugin = new Plugin();

      if ($plugin->isActivated('fields')) {
         global $DB;
         $ID = $ticket->getField('id');
         $fieldsContainer = new PluginFieldsContainer();
         $fieldsContainers = $fieldsContainer->find([
            'itemtypes' => ['LIKE', '%"Ticket"%'],
            'is_active' => 1,
         ]);

         foreach ($fieldsContainers as $fieldsC) {
            $containerId = $fieldsC['id'];
            $containerName = $fieldsC['name'] . 's';
            $fields = new PluginFieldsField();

            $fieldsCFields = $fields->find(
               [
                  'plugin_fields_containers_id' => $containerId,
                  'is_active' => 1,
               ],
               ['ranking asc']
            );

            $first = null;

            foreach ($fieldsCFields as $field) {
               $fieldName = $field['name'];
               $fieldsInfo[$fieldName] = $field;
               $fieldType = $field['type'];

               try {
                  $query = "SELECT * FROM glpi_plugin_fields_ticket$containerName WHERE items_id = $ID limit 1";

                  $res = $DB->query($query);

                  $result = $DB->fetchAssoc($res);

                  // if(!array_key_exists($fieldName, $result)) return;

                  switch ($fieldType) {
                     case 'datetime':
                     case 'date':
                     case 'time':
                        $result = [$fieldName => self::formatDate($result[$fieldName], $fieldType)];
                        break;
                     case 'dropdown':
                        $result = [$fieldName => self::getAdditionalFieldsDropdownValue($ID, $result, $fieldName, $field)];
                        // die(json_encode([$result,$fieldName]));
                        break;
                  }

                  $fieldValues[$fieldName] = $result[$fieldName];
               } catch (\Throwable $th) {
                  $fieldValues[$fieldName] = $th->getMessage();
               }
            }
         }

         $soluce = $DB->request(
            'glpi_itilsolutions',
            [
               'itemtype' => 'Ticket',
               'items_id' => $ID,
               'ORDER' => ' date_Creation DESC ',
            ]
         );

         foreach ($soluce as $row) {
            $fieldValues['solutions'][] = $row;
         }

      }
   }


   static function pdfMain(PluginPdfSimplePDF $pdf, Ticket $ticket)
   {
      global $DB;
      $dbu = new DbUtils();

      $infouser = isset($_REQUEST['item']['_inforequester_']);

      $ID = $ticket->getField('id');

      if (!$ticket->can($ID, READ)) {
         return false;
      }

      $pdf->setColumnsSize(100);

      $entity = Entity::getById($ticket->getField('entities_id'));


      $pdf->setColumnsSize(25, 50, 25);
      $pdf->displayTitle(
         "<strong><i>Número de registro</i></strong><br>$ID",
         '<strong><i>Cliente</i></strong><br>' . $entity->getField('name'),
         "<strong><i>Fecha</i></strong><br>" . self::formatDate($ticket->getField('date_creation'), 'date')
      );


      $additionalFieldsInfo = [];
      $additionalFieldsValues = [];

      self::getAdditionalFieldsForTicket($ticket, $additionalFieldsValues, $additionalFieldsInfo);

      $entityAddress = implode(', ', array_filter([
         $entity->getField('address') ?? null,
         $entity->getField('postcode') ?? null,
         $entity->getField('town') ?? null,
         $entity->getField('state') ?? null,
         $entity->getField(field: 'country') ?? null
      ], fn($d) => $d != null));

      if (sizeof($additionalFieldsInfo)) {
         $pdf->setColumnsSize(100);
         $pdf->displaySpace();
         $pdf->displayTitle("<strong><i>Datos del aviso</i></strong>");
         $pdf->setColumnsSize(50, 50);

         $pdf->displayLine("<strong><i>Provincia:</i></strong>&nbsp;" . $additionalFieldsValues['provinciafield'], "<strong><i>Población:</i></strong>&nbsp;" . $additionalFieldsValues['poblacinfield']);

         $pdf->displayLine("<strong><i>Nº Llamada:</i></strong>&nbsp;" . $additionalFieldsValues['nllamadafield'], "<strong><i>Nº Llamada Fab.:</i></strong>&nbsp;" . $additionalFieldsValues['nllamadafabfield']);

         $pdf->displayLine("<strong><i>Fecha de cita:</i></strong>&nbsp;" . $additionalFieldsValues['fechadecitafield'], "<strong><i>Contacto:</i></strong>&nbsp;" . $additionalFieldsValues['contactofield']);

         $pdf->displayLine("<strong><i>Teléfono:</i></strong>&nbsp;" . $entity->getField('phonenumber'), "<strong><i>Correo electrónico:</i></strong>&nbsp;" . $entity->getField('email'));

         $pdf->setColumnsSize(100);

         $pdf->displayText('<strong><i>Dirección:</i></strong>', $entityAddress, 2, 2);

         $pdf->displaySpace();
         $pdf->displayTitle("<strong><i>Datos de la intervención</i></strong>");
         $pdf->setColumnsSize(50, 50);

         $pdf->displayLine("<strong><i>Hora Inicio:</i></strong>&nbsp;" . self::formatDate($additionalFieldsValues['fechadeiniciofield'], 'time'), "<strong><i>Hora Fin:&nbsp;</i></strong>" . self::formatDate($additionalFieldsValues['fechadefinalizacinfield'], 'time'));


         // if ($additionalFieldsValues['fechadeiniciofield2'] || $additionalFieldsValues['fechadefinalizacinfield2']) {
         $pdf->displayLine("<strong><i>Hora Inicio 2:</i></strong>&nbsp;" . self::formatDate($additionalFieldsValues['fechadeiniciofield2'], 'time'), "<strong><i>Hora Fin 2:&nbsp;</i></strong>" . self::formatDate($additionalFieldsValues['fechadefinalizacinfield2'], 'time'));
         // }

         // if ($additionalFieldsValues['fechadeiniciofield3'] || $additionalFieldsValues['fechadefinalizacinfield3']) {
         $pdf->displayLine("<strong><i>Hora Inicio 3:</i></strong>&nbsp;" . self::formatDate($additionalFieldsValues['fechadeiniciofield3'], 'time'), "<strong><i>Hora Fin 3:&nbsp;</i></strong>" . self::formatDate($additionalFieldsValues['fechadefinalizacinfield3'], 'time'));
         // }
         $pdf->displayLine("<strong><i>Kilometraje:</i></strong>&nbsp;" . $additionalFieldsValues['kilometrajefield'], "<strong><i>Tpo. Desplazamiento:</i></strong>&nbsp;" . $additionalFieldsValues['tpodesplazamientofield']);

         $pdf->displaySpace();
         $pdf->setColumnsSize(100);
         $pdf->displayText("<strong><i>Avería</i></strong>", "<br>" . $additionalFieldsValues['averafield'], 3, 3);

         $pdf->displaySpace();

         if ($additionalFieldsValues['descripcindelaintervencinfield'] != null && $additionalFieldsValues['descripcindelaintervencinfield'] != '') {
            $pdf->displayText("<strong><i>Descripción de la intervención</i></strong>", "<br>" . $additionalFieldsValues['descripcindelaintervencinfield'], 5, 5);
         } else {
            $pdf->displayText("<strong><i>Descripción de la intervención</i></strong>", "<br>" . trim($additionalFieldsValues['solutions'][0]['content']), 5, 5);
         }


         $pdf->displaySpace();
         $pdf->displayText("<strong><i>Material empleado</i></strong>", "<br>" . $additionalFieldsValues['materialempleadofield'], 5, 5);

         $pdf->displaySpace();
         $pdf->displayText("<strong><i>Observaciones</i></strong>", "<br>" . $additionalFieldsValues['observacionefieldtwo'], 3, 3);
      }

      // Assign to
      $users = [];
      $listusers = '';
      $assign = '<b><i>' . sprintf(
         __('%1$s: %2$s') . "</i></b>",
         __('Assigned to technicians'),
         $listusers
      );

      foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {
         if ($d['users_id']) {
            $tmp = Toolbox::stripTags($dbu->getUserName($d['users_id']));
            // if ($d['alternative_email']) {
            //    $tmp .= ' ('.$d['alternative_email'].')';
            // }
         } else {
            // $tmp = $d['alternative_email'];
         }
         $users[] = $tmp;
      }
      if (count($users)) {
         $listusers = implode('<br>', $users);
      }

      $pdf->displaySpace();
      $pdf->setColumnsSize(50, 50);

      $pdf->displayCustomLine(miny: 3, msgs: ["<strong><i>Técnicos</i></strong><br>" . $listusers, "<strong><i>Firma del cliente</i></strong><br><br><br>"]);


   }


   static function pdfStat(PluginPdfSimplePDF $pdf, Ticket $ticket)
   {
      $now = time();
      $date_creation = strtotime($ticket->fields['date']);
      $date_takeintoaccount = $date_creation + $ticket->fields['takeintoaccount_delay_stat'];

      if ($date_takeintoaccount == $date_creation) {
         $date_takeintoaccount = 0;
      }

      $pdf->setColumnsSize(100);
      $pdf->displayTitle("<b>" . _n('Date', 'Dates', 2) . "</b>");

      $pdf->setColumnsSize(50, 50);
      $pdf->setColumnsAlign('right', 'left');

      $pdf->displayLine(Html::convDateTime($ticket->fields['date']), __('Opening date'));

      if (!empty($ticket->fields['internal_time_to_own'])) {
         $pdf->displayLine(
            Html::convDateTime($ticket->fields['internal_time_to_own']),
            __('Internal time to own')
         );
      }
      if (!empty($ticket->fields['takeintoaccount_delay_stat'])) {
         $pdf->displayLine(
            Html::convDateTime(date("Y-m-d H:i:s", $date_takeintoaccount)),
            __('Take into account')
         );
      }
      if (!empty($ticket->fields['time_to_own'])) {
         $pdf->displayLine(Html::convDateTime($ticket->fields['time_to_own']), __('Time to own'));
      }
      if (!empty($ticket->fields['internal_time_to_resolve'])) {
         $pdf->displayLine(
            Html::convDateTime($ticket->fields['internal_time_to_resolve']),
            __('Internal time to resolve')
         );
      }
      if (!empty($ticket->fields['time_to_resolve'])) {
         $pdf->displayLine(
            Html::convDateTime($ticket->fields['time_to_resolve']),
            __('Time to resolve')
         );
      }
      if (
         in_array($ticket->fields["status"], $ticket->getSolvedStatusArray())
         || in_array($ticket->fields["status"], $ticket->getClosedStatusArray())
      ) {
         $pdf->displayLine(Html::convDateTime($ticket->fields['solvedate']), __('Resolution date'));
      }
      if (in_array($ticket->fields["status"], $ticket->getClosedStatusArray())) {
         $pdf->displayLine(Html::convDateTime($ticket->fields['closedate']), __('Closing date'));
      }

      $pdf->setColumnsSize(100);
      $pdf->displayTitle("<b>" . _n('Time', 'Times', 2) . "</b>");

      $pdf->setColumnsSize(50, 50);
      if ($ticket->fields['takeintoaccount_delay_stat'] > 0) {
         $pdf->displayLine(
            __('Take into account'),
            Toolbox::stripTags(Html::timestampToString($ticket->fields['takeintoaccount_delay_stat'], 0))
         );
      }

      if (
         in_array($ticket->fields["status"], $ticket->getSolvedStatusArray())
         || in_array($ticket->fields["status"], $ticket->getClosedStatusArray())
      ) {
         if ($ticket->fields['solve_delay_stat'] > 0) {
            $pdf->displayLine(
               __('Solution'),
               Toolbox::stripTags(Html::timestampToString($ticket->fields['solve_delay_stat'], 0))
            );
         }
      }
      if (in_array($ticket->fields["status"], $ticket->getClosedStatusArray())) {
         if ($ticket->fields['close_delay_stat'] > 0) {
            $pdf->displayLine(
               __('Closing'),
               Toolbox::stripTags(Html::timestampToString($ticket->fields['close_delay_stat'], 1))
            );
         }
      }
      if ($ticket->fields['waiting_duration'] > 0) {
         $pdf->displayLine(
            __('Pending'),
            Toolbox::stripTags(Html::timestampToString($ticket->fields['waiting_duration'], 0))
         );
      }

      $pdf->displaySpace();
   }


   function defineAllTabsPDF($options = [])
   {

      $onglets = parent::defineAllTabsPDF($options);
      unset($onglets['ProjectTask_Ticket$1']);
      unset($onglets['Itil_Project$1']);

      if (
         Session::haveRight('ticket', Ticket::READALL) // for technician
         || Session::haveRight('followup', ITILFollowup::SEEPRIVATE)
         || Session::haveRight('task', TicketTask::SEEPRIVATE)
      ) {
         $onglets['_private_'] = __('Private');
      }

      if (Session::haveRight('user', READ)) {
         $onglets['_inforequester_'] = __('Requester information', 'pdf');
      }

      return $onglets;
   }


   static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
   {

      $private = isset($_REQUEST['item']['_private_']);

      switch ($tab) {
         case '_private_':
            // nothing to export, just a flag
            break;

         case '_inforequester_':
            break;

         case 'Ticket$main': // 0.90+
            self::pdfMain($pdf, $item);
            // PluginPdfItilFollowup::pdfForItem($pdf, $item, $private);
            // PluginPdfTicketTask::pdfForTicket($pdf, $item, $private);
            if (Session::haveRight('document', READ)) {
               // PluginPdfDocument::pdfForItem($pdf, $item);
            }
            // PluginPdfITILSolution::pdfForItem($pdf, $item);
            break;

         case 'TicketValidation$1': // 0.85
            PluginPdfTicketValidation::pdfForTicket($pdf, $item);
            break;

         case 'TicketCost$1':
            PluginPdfCommonItilCost::pdfForItem($pdf, $item);
            break;

         case 'Ticket$3':
            PluginPdfTicketSatisfaction::pdfForTicket($pdf, $item);
            break;

         case 'Problem_Ticket$1':
            PluginPdfProblem_Ticket::pdfForTicket($pdf, $item);
            break;

         case 'Ticket$4':
            self::pdfStat($pdf, $item);
            break;

         case 'Item_Ticket$1':
            PluginPdfItem_Ticket::pdfForTicket($pdf, $item);
            break;

         case 'Change_Ticket$1':
            if (Change::canView()) {
               PluginPdfChange_Ticket::pdfForTicket($pdf, $item);
            }
            break;

         case 'Ticket_Contract$1':
            PluginPdfTicket_Contract::pdfForTicket($pdf, $item);
            break;

         default:
            return false;
      }
      return true;
   }


}

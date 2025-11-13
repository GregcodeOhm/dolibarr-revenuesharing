<?php
/**
 * Outil de listing des factures impayées par collaborateur
 * Génère un HTML pour envoi par email
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Main include file not found");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
dol_include_once('/revenuesharing/class/revenuesharing_collaborator.class.php');

// Security check
if (!$user->admin && empty($user->rights->revenuesharing->read)) {
    accessforbidden();
}

$form = new Form($db);
$collaborator_id = GETPOST('collaborator_id', 'int');
$action = GETPOST('action', 'alpha');
$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');

// Action : envoyer l'email
// Note: On ne passe pas le HTML via POST pour éviter la protection anti-injection
// On le régénère côté serveur à partir des données de la facture
if ($action == 'send_email' && $collaborator_id > 0) {
    $email_to = GETPOST('email_to', 'email');
    $email_subject = GETPOST('email_subject', 'restricthtml');

    if (!empty($email_to)) {
        // Régénérer le contenu HTML de l'email côté serveur
        // (même logique que plus bas, mais on l'exécute avant l'affichage)
        require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

        // Récupérer les infos du collaborateur
        $sql_collab = "SELECT c.label, u.email FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
        $sql_collab .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
        $sql_collab .= " WHERE c.rowid = ".(int)$collaborator_id;
        $resql_collab = $db->query($sql_collab);
        $collaborator = $db->fetch_object($resql_collab);
        $collaborator_fullname = $collaborator->label;

        // Requête pour récupérer les factures impayées
        $sql_invoices = "SELECT
            f.rowid,
            f.ref,
            f.datef,
            f.date_lim_reglement,
            f.total_ht,
            f.total_tva,
            f.total_ttc,
            f.paye,
            f.fk_statut,
            s.nom as client_name,
            s.rowid as client_id,
            fe.intervenant,
            (f.total_ttc - COALESCE((SELECT SUM(amount) FROM ".MAIN_DB_PREFIX."paiement_facture pf WHERE pf.fk_facture = f.rowid), 0)) as reste_a_payer
        FROM ".MAIN_DB_PREFIX."facture f
        LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
        WHERE YEAR(f.datef) = ".(int)$year."
        AND fe.intervenant = '".$db->escape($collaborator_fullname)."'
        AND f.fk_statut IN (1, 2)
        AND f.paye = 0
        HAVING reste_a_payer > 0
        ORDER BY f.date_lim_reglement ASC, f.datef DESC";

        $resql_invoices = $db->query($sql_invoices);

        if ($resql_invoices) {
            $num_invoices = $db->num_rows($resql_invoices);
            $total_ttc = 0;
            $total_reste = 0;
            $invoices_data = array();

            while ($obj = $db->fetch_object($resql_invoices)) {
                $invoices_data[] = $obj;
                $total_ttc += $obj->total_ttc;
                $total_reste += $obj->reste_a_payer;
            }

            // Générer le HTML (fonction appelée plus loin)
            $email_body = generateEmailHTML($collaborator_fullname, $num_invoices, $total_ttc, $total_reste, $invoices_data, $db, $year);

            // Configuration de l'expéditeur
            $from = $conf->global->MAIN_MAIL_EMAIL_FROM;
            $from_name = $conf->global->MAIN_INFO_SOCIETE_NOM;

            // Créer l'objet mail
            $mail = new CMailFile(
                $email_subject,
                $email_to,
                $from,
                $email_body,
                array(),  // attachments
                array(),  // files_mime
                array(),  // file_names
                '',       // cc
                '',       // bcc
                0,        // deliveryreceipt
                1,        // msgishtml (1 = HTML, 0 = text)
                '',       // errors_to
                '',       // css
                '',       // trackid
                '',       // moreinheader
                'standard' // sendcontext
            );

            $result = $mail->sendfile();

            if ($result) {
                setEventMessages('Email envoyé avec succès à '.$email_to, null, 'mesgs');
            } else {
                setEventMessages('Erreur lors de l\'envoi de l\'email : '.$mail->error, null, 'errors');
            }
        } else {
            setEventMessages('Erreur lors de la récupération des factures', null, 'errors');
        }
    } else {
        setEventMessages('Email destinataire manquant', null, 'errors');
    }
}

// Fonction pour générer le HTML de l'email
function generateEmailHTML($collaborator_fullname, $num, $total_ttc_unpaid, $total_reste_a_payer, $invoices, $db, $year) {
    $email_html_rows = '';

    foreach ($invoices as $invoice) {
        $today = time();
        $due_date = $db->jdate($invoice->date_lim_reglement);
        $days_late = 0;
        $row_bg = '';

        if ($due_date) {
            $days_late = floor(($today - $due_date) / 86400);
            if ($days_late > 60) {
                $row_bg = ' style="background: #ffebee;"';
            } elseif ($days_late > 30) {
                $row_bg = ' style="background: #fff3e0;"';
            } elseif ($days_late > 0) {
                $row_bg = ' style="background: #fff9c4;"';
            }
        }

        $days_display = '-';
        if ($days_late > 0) {
            $days_display = '<strong style="color: #d32f2f;">'.$days_late.' jours</strong>';
        }

        $statut_email = '';
        if ($invoice->reste_a_payer >= $invoice->total_ttc) {
            $statut_email = 'Impayée';
        } else {
            $statut_email = 'Partiellement payée';
        }

        $email_html_rows .= '
                <tr'.$row_bg.'>
                    <td>'.dol_escape_htmltag($invoice->ref).'</td>
                    <td>'.dol_escape_htmltag($invoice->client_name).'</td>
                    <td>'.dol_print_date($db->jdate($invoice->datef), 'day').'</td>
                    <td>'.($due_date ? dol_print_date($due_date, 'day') : '-').'</td>
                    <td class="text-right">'.$days_display.'</td>
                    <td class="text-right">'.price($invoice->total_ttc, 0, '', 1, -1, -1, 'EUR').'</td>
                    <td class="text-right"><strong style="color: #d32f2f;">'.price($invoice->reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</strong></td>
                    <td class="text-center">'.$statut_email.'</td>
                </tr>';
    }

    return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures impayées</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 700px;
            table-layout: fixed;
        }
        th, td {
            padding: 12px 8px;
            border: 1px solid #dee2e6;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
            white-space: nowrap;
        }
        /* Largeurs de colonnes spécifiques */
        th:nth-child(1), td:nth-child(1) { width: 8%; } /* Réf. */
        th:nth-child(2), td:nth-child(2) { width: 22%; } /* Client */
        th:nth-child(3), td:nth-child(3) { width: 10%; } /* Date */
        th:nth-child(4), td:nth-child(4) { width: 8%; } /* Éch. */
        th:nth-child(5), td:nth-child(5) { width: 9%; min-width: 80px; } /* Retard */
        th:nth-child(6), td:nth-child(6) { width: 13%; min-width: 100px; } /* Montant TTC */
        th:nth-child(7), td:nth-child(7) { width: 14%; min-width: 110px; } /* Reste à payer */
        th:nth-child(8), td:nth-child(8) { width: 16%; min-width: 130px; } /* Statut */

        .text-right {
            text-align: right;
            white-space: nowrap;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 5px;
            padding: 15px;
            margin-top: 30px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            color: #6c757d;
            font-size: 12px;
        }

        @media only screen and (max-width: 600px) {
            .container {
                padding: 10px;
            }
            .header {
                padding: 20px 15px;
            }
            .header h1 {
                font-size: 18px;
            }
            .content {
                padding: 15px;
            }
            table {
                font-size: 12px;
                min-width: 500px;
            }
            th, td {
                padding: 8px 4px;
            }
            .info-box {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>État des factures impayées</h1>
        </div>

        <div class="content">
            <p>Bonjour '.dol_escape_htmltag($collaborator_fullname).',</p>

            <p>Voici un récapitulatif de vos factures en attente de paiement. Vous avez actuellement <strong>'.$num.' facture'.($num > 1 ? 's' : '').'</strong> pour un reste à payer de <strong>'.price($total_reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</strong>.</p>

            <h3 style="color: #667eea; margin-top: 30px;">Détail des factures :</h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Réf.</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Éch.</th>
                            <th class="text-right">Retard</th>
                            <th class="text-right">Montant TTC</th>
                            <th class="text-right">Reste à payer</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>'.$email_html_rows.'
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="5" class="text-right">TOTAL</td>
                            <td class="text-right">'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</td>
                            <td class="text-right" style="color: #d32f2f;">'.price($total_reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="info-box">
                <p style="margin: 0;"><strong>ℹ️ Information :</strong></p>
                <p style="margin: 10px 0 0 0;">Ce document est fourni à titre informatif. Pour toute question concernant ces factures, n\'hésitez pas à nous contacter.</p>
            </div>
        </div>

        <div class="footer">
            <p>Ce message a été généré automatiquement le '.dol_print_date(time(), 'dayhour').'</p>
        </div>
    </div>
</body>
</html>';
}

llxHeader('', 'Factures impayées par collaborateur');

print load_fiche_titre('📧 Factures impayées par collaborateur', '', 'generic');

// Sélection du collaborateur
print '<div class="fichecenter">';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 20px; margin-bottom: 20px;">';
print '<table class="border centpercent">';

// Année
print '<tr>';
print '<td width="30%"><label>Année</label></td>';
print '<td>';
print '<select name="year" class="flat">';
for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
    print '<option value="'.$y.'"'.($year == $y ? ' selected' : '').'>'.$y.'</option>';
}
print '</select>';
print '</td>';
print '</tr>';

// Collaborateur
print '<tr>';
print '<td><label>Collaborateur</label></td>';
print '<td>';
print '<select name="collaborator_id" class="flat minwidth200" required>';
print '<option value="">-- Choisir un collaborateur --</option>';

$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $selected = ($collaborator_id == $obj->rowid) ? ' selected' : '';
        print '<option value="'.$obj->rowid.'"'.$selected.'>'.dol_escape_htmltag($obj->label).'</option>';
    }
}
print '</select>';
print '</td>';
print '</tr>';

print '</table>';
print '<div style="text-align: center; margin-top: 15px;">';
print '<input type="submit" class="button" value="🔍 Rechercher les factures impayées">';
print '</div>';
print '</div>';
print '</form>';

// Si un collaborateur est sélectionné, afficher les factures impayées
if ($collaborator_id > 0) {

    // Récupérer les infos du collaborateur
    $sql_collab = "SELECT c.label, u.email FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
    $sql_collab .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql_collab .= " WHERE c.rowid = ".(int)$collaborator_id;
    $resql_collab = $db->query($sql_collab);
    $collaborator = $db->fetch_object($resql_collab);
    $collaborator_fullname = $collaborator->label;
    $collaborator_email = $collaborator->email;

    print '<h3>Factures impayées pour '.$collaborator_fullname.' ('.date('Y').')</h3>';

    // Requête pour récupérer les factures impayées et partiellement payées du collaborateur
    // Note: le champ intervenant contient le label du collaborateur (string), pas son ID
    $sql = "SELECT
        f.rowid,
        f.ref,
        f.datef,
        f.date_lim_reglement,
        f.total_ht,
        f.total_tva,
        f.total_ttc,
        f.paye,
        f.fk_statut,
        s.nom as client_name,
        s.rowid as client_id,
        fe.intervenant,
        (f.total_ttc - COALESCE((SELECT SUM(amount) FROM ".MAIN_DB_PREFIX."paiement_facture pf WHERE pf.fk_facture = f.rowid), 0)) as reste_a_payer
    FROM ".MAIN_DB_PREFIX."facture f
    LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
    LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
    WHERE YEAR(f.datef) = ".(int)$year."
    AND fe.intervenant = '".$db->escape($collaborator_fullname)."'
    AND f.fk_statut IN (1, 2)
    AND f.paye = 0
    HAVING reste_a_payer > 0
    ORDER BY f.date_lim_reglement ASC, f.datef DESC";

    $resql = $db->query($sql);

    if ($resql) {
        $num = $db->num_rows($resql);

        // Debug: afficher la requête et le nombre de résultats
        if ($num == 0) {
            print '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
            print '<strong>Debug info:</strong><br>';
            print 'Collaborateur recherché : <code>'.$collaborator_fullname.'</code><br>';
            print 'Année : '.$year.'<br>';
            print 'Requête SQL : <pre style="font-size: 11px; background: white; padding: 10px; overflow-x: auto;">'.htmlspecialchars($sql).'</pre>';

            // Essayer sans le filtre intervenant pour voir combien de factures impayées il y a au total
            $sql_test = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."facture f WHERE YEAR(f.datef) = ".(int)$year." AND f.fk_statut = 1 AND f.paye = 0";
            $resql_test = $db->query($sql_test);
            if ($resql_test) {
                $obj_test = $db->fetch_object($resql_test);
                print 'Total factures impayées (tous collaborateurs) : '.$obj_test->nb.'<br>';
            }

            // Vérifier si intervenant existe dans extrafields
            $sql_test2 = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE YEAR(f.datef) = ".(int)$year."
                AND fe.intervenant = '".$db->escape($collaborator_fullname)."'
                AND f.fk_statut = 1";
            $resql_test2 = $db->query($sql_test2);
            if ($resql_test2) {
                $obj_test2 = $db->fetch_object($resql_test2);
                print 'Factures validées pour ce collaborateur (payées ou non) : '.$obj_test2->nb.'<br>';
            }

            print '</div>';
        }

        if ($num > 0) {

            // Calculer les totaux
            $total_ht_unpaid = 0;
            $total_ttc_unpaid = 0;
            $total_reste_a_payer = 0;
            $invoices = array();

            while ($obj = $db->fetch_object($resql)) {
                $invoices[] = $obj;
                $total_ht_unpaid += $obj->total_ht;
                $total_ttc_unpaid += $obj->total_ttc;
                $total_reste_a_payer += $obj->reste_a_payer;
            }

            // Afficher les KPIs
            print '<div style="display: flex; gap: 15px; margin-bottom: 20px;">';

            print '<div style="flex: 1; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            print '<div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Nombre de factures impayées</div>';
            print '<div style="font-size: 32px; font-weight: bold;">'.$num.'</div>';
            print '</div>';

            print '<div style="flex: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            print '<div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Total HT impayé</div>';
            print '<div style="font-size: 32px; font-weight: bold;">'.price($total_ht_unpaid, 0, '', 1, -1, -1, 'EUR').'</div>';
            print '</div>';

            print '<div style="flex: 1; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            print '<div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Reste à payer</div>';
            print '<div style="font-size: 32px; font-weight: bold;">'.price($total_reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</div>';
            print '</div>';

            print '</div>';

            // Tableau des factures
            print '<div style="background: white; border: 1px solid #dee2e6; border-radius: 5px; overflow: hidden; margin-bottom: 20px;">';
            print '<table class="tagtable liste" style="width: 100%;">';
            print '<tr class="liste_titre">';
            print '<th>Réf.</th>';
            print '<th>Client</th>';
            print '<th>Date facture</th>';
            print '<th>Éch.</th>';
            print '<th class="right">Retard (j)</th>';
            print '<th class="right">Montant TTC</th>';
            print '<th class="right">Reste à payer</th>';
            print '<th>Statut</th>';
            print '</tr>';

            foreach ($invoices as $invoice) {
                $today = time();
                $due_date = $db->jdate($invoice->date_lim_reglement);
                $days_late = 0;
                $late_class = '';

                if ($due_date) {
                    $days_late = floor(($today - $due_date) / 86400);
                    if ($days_late > 60) {
                        $late_class = 'style="background: #ffebee;"';
                    } elseif ($days_late > 30) {
                        $late_class = 'style="background: #fff3e0;"';
                    } elseif ($days_late > 0) {
                        $late_class = 'style="background: #fff9c4;"';
                    }
                }

                print '<tr '.$late_class.'>';
                print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invoice->rowid.'" target="_blank">'.$invoice->ref.'</a></td>';
                print '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$invoice->client_id.'" target="_blank">'.$invoice->client_name.'</a></td>';
                print '<td>'.dol_print_date($db->jdate($invoice->datef), 'day').'</td>';
                print '<td>'.($due_date ? dol_print_date($due_date, 'day') : '-').'</td>';
                print '<td class="right">';
                if ($days_late > 0) {
                    print '<strong style="color: #d32f2f;">'.$days_late.' jours</strong>';
                } else {
                    print '-';
                }
                print '</td>';
                print '<td class="right">'.price($invoice->total_ttc, 0, '', 1, -1, -1, 'EUR').'</td>';
                print '<td class="right"><strong style="color: #d32f2f;">'.price($invoice->reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</strong></td>';

                // Statut : déterminé par le reste à payer
                $statut_label = '';
                if ($invoice->reste_a_payer >= $invoice->total_ttc) {
                    $statut_label = '<span class="badge badge-danger">Impayée</span>';
                } else {
                    $statut_label = '<span class="badge badge-warning">Partiellement payée</span>';
                }
                print '<td>'.$statut_label.'</td>';
                print '</tr>';
            }

            print '<tr class="liste_total">';
            print '<td colspan="5" class="right"><strong>TOTAL</strong></td>';
            print '<td class="right"><strong>'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
            print '<td class="right"><strong style="color: #d32f2f;">'.price($total_reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
            print '<td></td>';
            print '</tr>';

            print '</table>';
            print '</div>';

            // Section d'envoi d'email
            print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 5px; padding: 20px; margin-top: 20px;">';
            print '<h3 style="margin-top: 0;">📧 Envoyer l\'email au collaborateur</h3>';

            print '<button id="generateEmailBtn" class="button" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 10px 20px; cursor: pointer;">Générer l\'aperçu</button>';

            print '<div id="emailPreview" style="margin-top: 20px; display: none;">';
            print '<h4>Aperçu de l\'email :</h4>';
            print '<div id="emailContent" style="border: 2px solid #90caf9; padding: 20px; background: white; border-radius: 5px; max-height: 400px; overflow-y: auto;"></div>';

            // Formulaire d'envoi
            print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="margin-top: 20px; background: #f8f9fa; padding: 20px; border-radius: 5px;">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="action" value="send_email">';
            print '<input type="hidden" name="collaborator_id" value="'.$collaborator_id.'">';
            print '<input type="hidden" name="year" value="'.$year.'">';
            // Note: email_body n'est plus envoyé via POST, il est régénéré côté serveur

            print '<table class="border centpercent">';
            print '<tr>';
            print '<td width="20%"><label>Email destinataire *</label></td>';
            print '<td><input type="email" name="email_to" value="'.dol_escape_htmltag($collaborator_email).'" required class="flat minwidth300" placeholder="email@example.com"></td>';
            print '</tr>';
            print '<tr>';
            print '<td><label>Objet *</label></td>';
            print '<td><input type="text" name="email_subject" value="État des factures impayées - '.$collaborator_fullname.'" required class="flat minwidth300"></td>';
            print '</tr>';
            print '</table>';

            print '<div style="text-align: center; margin-top: 15px;">';
            print '<button type="submit" class="button" style="background: #4caf50; color: white; border: none; padding: 12px 30px; cursor: pointer; font-size: 16px;">✉️ Envoyer l\'email via Dolibarr</button>';
            print '</div>';
            print '</form>';

            print '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
            print '<button id="copyHtmlBtn" class="button" style="background: #ff9800; color: white; border: none; padding: 10px 20px; cursor: pointer;">📋 Copier le HTML (manuel)</button>';
            print '<span style="margin-left: 10px; color: #666; font-size: 12px;">Pour envoi manuel via un autre client email</span>';
            print '</div>';

            print '</div>';
            print '</div>';

            // Générer le HTML de l'email côté PHP
            $email_html_rows = '';
            foreach ($invoices as $invoice) {
                $today = time();
                $due_date = $db->jdate($invoice->date_lim_reglement);
                $days_late = 0;
                $row_bg = '';

                if ($due_date) {
                    $days_late = floor(($today - $due_date) / 86400);
                    if ($days_late > 60) {
                        $row_bg = ' style="background: #ffebee;"';
                    } elseif ($days_late > 30) {
                        $row_bg = ' style="background: #fff3e0;"';
                    } elseif ($days_late > 0) {
                        $row_bg = ' style="background: #fff9c4;"';
                    }
                }

                $days_display = '-';
                if ($days_late > 0) {
                    $days_display = '<strong style="color: #d32f2f;">'.$days_late.' jours</strong>';
                }

                $statut_email = '';
                if ($invoice->reste_a_payer >= $invoice->total_ttc) {
                    $statut_email = 'Impayée';
                } else {
                    $statut_email = 'Partiellement payée';
                }

                $email_html_rows .= '
                <tr'.$row_bg.'>
                    <td>'.$invoice->ref.'</td>
                    <td>'.dol_escape_htmltag($invoice->client_name).'</td>
                    <td>'.dol_print_date($db->jdate($invoice->datef), 'day').'</td>
                    <td>'.($due_date ? dol_print_date($due_date, 'day') : '-').'</td>
                    <td class="text-right">'.$days_display.'</td>
                    <td class="text-right">'.price($invoice->total_ttc, 0, '', 1, -1, -1, 'EUR').'</td>
                    <td class="text-right"><strong style="color: #d32f2f;">'.price($invoice->reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</strong></td>
                    <td class="text-center">'.$statut_email.'</td>
                </tr>';
            }

            $email_html_template = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures impayées</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 700px;
            table-layout: fixed;
        }
        th, td {
            padding: 12px 8px;
            border: 1px solid #dee2e6;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
            white-space: nowrap;
        }
        /* Largeurs de colonnes spécifiques */
        th:nth-child(1), td:nth-child(1) { width: 8%; } /* Réf. */
        th:nth-child(2), td:nth-child(2) { width: 22%; } /* Client */
        th:nth-child(3), td:nth-child(3) { width: 10%; } /* Date */
        th:nth-child(4), td:nth-child(4) { width: 8%; } /* Éch. */
        th:nth-child(5), td:nth-child(5) { width: 9%; min-width: 80px; } /* Retard */
        th:nth-child(6), td:nth-child(6) { width: 13%; min-width: 100px; } /* Montant TTC */
        th:nth-child(7), td:nth-child(7) { width: 14%; min-width: 110px; } /* Reste à payer */
        th:nth-child(8), td:nth-child(8) { width: 16%; min-width: 130px; } /* Statut */

        .text-right {
            text-align: right;
            white-space: nowrap;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 5px;
            padding: 15px;
            margin-top: 30px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            color: #6c757d;
            font-size: 12px;
        }

        /* Responsive pour mobile */
        @media only screen and (max-width: 600px) {
            .container {
                padding: 10px;
            }
            .header {
                padding: 20px 15px;
            }
            .header h1 {
                font-size: 18px;
            }
            .content {
                padding: 15px;
            }
            table {
                font-size: 12px;
                min-width: 500px;
            }
            th, td {
                padding: 8px 4px;
            }
            .info-box {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>État des factures impayées</h1>
        </div>

        <div class="content">
            <p>Bonjour '.dol_escape_htmltag($collaborator_fullname).',</p>

            <p>Voici un récapitulatif de vos factures en attente de paiement. Vous avez actuellement <strong>'.$num.' facture'.($num > 1 ? 's' : '').'</strong> pour un reste à payer de <strong>'.price($total_reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</strong>.</p>

            <h3 style="color: #667eea; margin-top: 30px;">Détail des factures :</h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Réf.</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Éch.</th>
                            <th class="text-right">Retard</th>
                            <th class="text-right">Montant TTC</th>
                            <th class="text-right">Reste à payer</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>'.$email_html_rows.'
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="5" class="text-right">TOTAL</td>
                            <td class="text-right">'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</td>
                            <td class="text-right" style="color: #d32f2f;">'.price($total_reste_a_payer, 0, '', 1, -1, -1, 'EUR').'</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="info-box">
                <p style="margin: 0;"><strong>ℹ️ Information :</strong></p>
                <p style="margin: 10px 0 0 0;">Ce document est fourni à titre informatif. Pour toute question concernant ces factures, n\'hésitez pas à nous contacter.</p>
            </div>
        </div>

        <div class="footer">
            <p>Ce message a été généré automatiquement le '.dol_print_date(time(), 'dayhour').'</p>
        </div>
    </div>
</body>
</html>';

            // JavaScript pour gérer l'affichage
            ?>
            <script>
            var emailHtmlTemplate = <?php echo json_encode($email_html_template); ?>;

            document.getElementById("generateEmailBtn").addEventListener("click", function() {
                // Afficher l'aperçu
                document.getElementById("emailContent").innerHTML = emailHtmlTemplate;
                document.getElementById("emailPreview").style.display = "block";

                // Remplir le champ caché pour l'envoi via formulaire
                // Note: Le HTML n'est plus stocké dans un champ caché, il est régénéré côté serveur lors de l'envoi
            });

            document.getElementById("copyHtmlBtn").addEventListener("click", function() {
                // Créer un textarea temporaire pour copier le HTML
                var tempTextarea = document.createElement("textarea");
                tempTextarea.value = emailHtmlTemplate;
                tempTextarea.style.position = "fixed";
                tempTextarea.style.opacity = "0";
                document.body.appendChild(tempTextarea);
                tempTextarea.select();

                try {
                    document.execCommand("copy");
                    alert("HTML copié dans le presse-papiers !");
                } catch(err) {
                    alert("Erreur lors de la copie : " + err);
                }

                document.body.removeChild(tempTextarea);
            });
            </script>
            <?php

        } else {
            print '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 5px; text-align: center;">';
            print '<h3 style="margin: 0;">✅ Aucune facture impayée</h3>';
            print '<p style="margin: 10px 0 0 0;">Le collaborateur '.$collaborator_fullname.' n\'a pas de factures impayées pour l\'année '.$year.'.</p>';
            print '</div>';
        }

    } else {
        print '<div class="error">Erreur SQL: '.$db->lasterror().'</div>';
    }
}

print '</div>';

llxFooter();
$db->close();

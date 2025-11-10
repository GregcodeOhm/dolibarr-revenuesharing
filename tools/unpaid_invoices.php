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
dol_include_once('/revenuesharing/class/revenuesharing_collaborator.class.php');

// Security check
if (!$user->admin && empty($user->rights->revenuesharing->read)) {
    accessforbidden();
}

$form = new Form($db);
$collaborator_id = GETPOST('collaborator_id', 'int');
$action = GETPOST('action', 'alpha');
$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');

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

    // Requête pour récupérer les factures impayées du collaborateur
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
        fe.intervenant
    FROM ".MAIN_DB_PREFIX."facture f
    LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
    LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
    WHERE YEAR(f.datef) = ".(int)$year."
    AND fe.intervenant = ".(int)$collaborator_id."
    AND f.fk_statut = 1
    AND f.paye = 0
    ORDER BY f.date_lim_reglement ASC, f.datef DESC";

    $resql = $db->query($sql);

    if ($resql) {
        $num = $db->num_rows($resql);

        if ($num > 0) {

            // Calculer les totaux
            $total_ht_unpaid = 0;
            $total_ttc_unpaid = 0;
            $invoices = array();

            while ($obj = $db->fetch_object($resql)) {
                $invoices[] = $obj;
                $total_ht_unpaid += $obj->total_ht;
                $total_ttc_unpaid += $obj->total_ttc;
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
            print '<div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Total TTC impayé</div>';
            print '<div style="font-size: 32px; font-weight: bold;">'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</div>';
            print '</div>';

            print '</div>';

            // Tableau des factures
            print '<div style="background: white; border: 1px solid #dee2e6; border-radius: 5px; overflow: hidden; margin-bottom: 20px;">';
            print '<table class="tagtable liste" style="width: 100%;">';
            print '<tr class="liste_titre">';
            print '<th>Référence</th>';
            print '<th>Client</th>';
            print '<th>Date facture</th>';
            print '<th>Échéance</th>';
            print '<th class="right">Retard (jours)</th>';
            print '<th class="right">Total HT</th>';
            print '<th class="right">Total TTC</th>';
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
                print '<td class="right">'.price($invoice->total_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
                print '<td class="right">'.price($invoice->total_ttc, 0, '', 1, -1, -1, 'EUR').'</td>';
                print '<td><span class="badge badge-warning">Impayée</span></td>';
                print '</tr>';
            }

            print '<tr class="liste_total">';
            print '<td colspan="5" class="right"><strong>TOTAL</strong></td>';
            print '<td class="right"><strong>'.price($total_ht_unpaid, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
            print '<td class="right"><strong>'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
            print '<td></td>';
            print '</tr>';

            print '</table>';
            print '</div>';

            // Bouton pour générer le HTML
            print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 5px; padding: 20px; margin-top: 20px;">';
            print '<h3 style="margin-top: 0;">📧 Générer l\'email pour le collaborateur</h3>';
            print '<button id="generateEmailBtn" class="button" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 10px 20px; cursor: pointer;">Générer le contenu HTML</button>';
            print ' <button id="copyHtmlBtn" class="button" style="display: none; background: #4caf50; color: white; border: none; padding: 10px 20px; cursor: pointer;">📋 Copier le HTML</button>';
            print '<div id="emailPreview" style="margin-top: 20px; display: none;">';
            print '<h4>Aperçu de l\'email :</h4>';
            print '<div id="emailContent" style="border: 2px solid #90caf9; padding: 20px; background: white; border-radius: 5px;"></div>';
            print '<h4 style="margin-top: 20px;">Code HTML à copier :</h4>';
            print '<textarea id="htmlCode" style="width: 100%; height: 200px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></textarea>';
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

                $email_html_rows .= '
                <tr'.$row_bg.'>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">'.$invoice->ref.'</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">'.dol_escape_htmltag($invoice->client_name).'</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">'.dol_print_date($db->jdate($invoice->datef), 'day').'</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">'.($due_date ? dol_print_date($due_date, 'day') : '-').'</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">'.$days_display.'</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;"><strong>'.price($invoice->total_ttc, 0, '', 1, -1, -1, 'EUR').'</strong></td>
                </tr>';
            }

            $email_html_template = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures impayées</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0;">
        <h1 style="margin: 0; font-size: 24px;">Rappel - Factures impayées</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Bonjour '.dol_escape_htmltag($collaborator_fullname).',</p>

        <p>Nous vous informons que vous avez actuellement <strong>'.$num.' facture'.($num > 1 ? 's' : '').' impayée'.($num > 1 ? 's' : '').'</strong> pour un montant total de <strong>'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</strong>.</p>

        <h3 style="color: #667eea; margin-top: 30px;">Détail des factures :</h3>

        <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: #667eea; color: white;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Référence</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Client</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Date</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Échéance</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">Retard</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">Montant TTC</th>
                </tr>
            </thead>
            <tbody>'.$email_html_rows.'
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="5" style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">TOTAL</td>
                    <td style="padding: 12px; text-align: right; border: 1px solid #dee2e6; color: #d32f2f;">'.price($total_ttc_unpaid, 0, '', 1, -1, -1, 'EUR').'</td>
                </tr>
            </tfoot>
        </table>

        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin-top: 30px;">
            <p style="margin: 0;"><strong>⚠️ Action requise :</strong></p>
            <p style="margin: 10px 0 0 0;">Merci de régulariser votre situation dans les meilleurs délais. En cas de questions, n\'hésitez pas à nous contacter.</p>
        </div>

        <p style="margin-top: 30px;">Cordialement,</p>
        <p style="margin: 5px 0;"><strong>L\'équipe Ohmnibus</strong></p>
    </div>

    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #6c757d; font-size: 12px;">
        <p>Ce message a été généré automatiquement le '.dol_print_date(time(), 'dayhour').'</p>
    </div>
</body>
</html>';

            // JavaScript pour gérer l'affichage
            ?>
            <script>
            var emailHtmlTemplate = <?php echo json_encode($email_html_template); ?>;

            document.getElementById("generateEmailBtn").addEventListener("click", function() {
                document.getElementById("emailContent").innerHTML = emailHtmlTemplate;
                document.getElementById("htmlCode").value = emailHtmlTemplate;
                document.getElementById("emailPreview").style.display = "block";
                document.getElementById("copyHtmlBtn").style.display = "inline-block";
            });

            document.getElementById("copyHtmlBtn").addEventListener("click", function() {
                var htmlCode = document.getElementById("htmlCode");
                htmlCode.select();
                document.execCommand("copy");
                alert("HTML copié dans le presse-papiers !");
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

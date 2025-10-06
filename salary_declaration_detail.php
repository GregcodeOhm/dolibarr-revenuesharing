<?php
/**
 * Page de détail d'une déclaration de salaire
 * Fichier: /htdocs/custom/revenuesharing/salary_declaration_detail.php
 */

require_once '../../main.inc.php';
require_once './lib/metiers_son.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

if ($id <= 0) {
    setEventMessages('ID déclaration manquant', null, 'errors');
    header('Location: salary_declarations_list.php');
    exit;
}

// Actions
if ($action == 'delete' && $confirm == 'yes') {
    $db->begin();
    $error = 0;
    
    // Vérifier que la déclaration n'est pas payée
    $sql_check = "SELECT status FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration WHERE rowid = ".(int)$id;
    $resql_check = $db->query($sql_check);
    if ($resql_check) {
        $status_obj = $db->fetch_object($resql_check);
        if ($status_obj->status == 3) { // Statut payé
            setEventMessages('Impossible de supprimer une déclaration payée', null, 'errors');
            $error++;
        }
        $db->free($resql_check);
    }
    
    if (!$error) {
        // Supprimer les détails
        $sql_del_details = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail WHERE fk_declaration = ".(int)$id;
        if (!$db->query($sql_del_details)) {
            $error++;
        }
        
        // Supprimer la déclaration
        $sql_del_main = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration WHERE rowid = ".(int)$id;
        if (!$db->query($sql_del_main)) {
            $error++;
        }
    }
    
    if (!$error) {
        $db->commit();
        setEventMessages('Déclaration supprimée avec succès', null, 'mesgs');
        header('Location: salary_declarations_list.php');
        exit;
    } else {
        $db->rollback();
        setEventMessages('Erreur lors de la suppression', null, 'errors');
    }
}

if ($action == 'validate') {
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration 
                   SET status = 2, date_modification = NOW(), fk_user_modif = ".$user->id."
                   WHERE rowid = ".(int)$id." AND status = 1";
    
    if ($db->query($sql_update)) {
        setEventMessages('Déclaration validée avec succès', null, 'mesgs');
    } else {
        setEventMessages('Erreur lors de la validation', null, 'errors');
    }
}

if ($action == 'setpaid') {
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration 
                   SET status = 3, date_modification = NOW(), fk_user_modif = ".$user->id."
                   WHERE rowid = ".(int)$id." AND status = 2";
    
    if ($db->query($sql_update)) {
        setEventMessages('Déclaration marquée comme payée', null, 'mesgs');
    } else {
        setEventMessages('Erreur lors du marquage comme payé', null, 'errors');
    }
}

if ($action == 'reopen') {
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration 
                   SET status = 1, date_modification = NOW(), fk_user_modif = ".$user->id."
                   WHERE rowid = ".(int)$id." AND status = 2";
    
    if ($db->query($sql_update)) {
        setEventMessages('Déclaration remise en brouillon', null, 'mesgs');
    } else {
        setEventMessages('Erreur lors de la réouverture', null, 'errors');
    }
}

// Charger la déclaration
$sql = "SELECT d.*, c.label as collaborator_name, u.login as created_by
        FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
        LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = d.fk_collaborator
        LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = d.fk_user_creat
        WHERE d.rowid = ".(int)$id;

$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    setEventMessages('Déclaration non trouvée', null, 'errors');
    header('Location: salary_declarations_list.php');
    exit;
}

$declaration = $db->fetch_object($resql);
$db->free($resql);

// Charger les détails
$sql_details = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail 
               WHERE fk_declaration = ".(int)$id." ORDER BY work_date";
$resql_details = $db->query($sql_details);
$details = array();
if ($resql_details) {
    while ($detail = $db->fetch_object($resql_details)) {
        $details[] = $detail;
    }
    $db->free($resql_details);
}

llxHeader('', 'Détail Déclaration de Salaire', '');

// Confirmation de suppression en haut
if ($action == 'delete') {
    print '<div class="center" style="margin: 20px 0; padding: 20px; background: #f8d7da; border: 2px solid #f5c6cb; border-radius: 8px;">';
    print '<div style="font-size: 1.5em; color: #721c24; margin-bottom: 15px;"><strong>Confirmation de suppression</strong></div>';
    print '<div style="font-size: 1.1em; margin-bottom: 10px;">Êtes-vous sûr de vouloir supprimer cette déclaration de salaire ?</div>';
    print '<div style="font-size: 1.0em; color: #721c24; margin-bottom: 15px;"><strong>Cette action est irréversible !</strong></div>';
    print '<div style="display: flex; gap: 15px; justify-content: center;">';
    print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&confirm=yes" class="button" style="background: #dc3545; color: white; padding: 10px 20px; font-size: 1.1em; border-radius: 5px;">Confirmer la suppression</a>';
    print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'" class="button" style="background: #6c757d; color: white; padding: 10px 20px; font-size: 1.1em; border-radius: 5px;">Annuler</a>';
    print '</div>';
    print '</div>';
}

print load_fiche_titre('Détail de la Déclaration de Salaire', '', 'generic');

// Statuts
$statuts = array(
    1 => array('label' => 'Brouillon', 'color' => '#ffc107', 'bg' => '#fff3cd'),
    2 => array('label' => 'Validée', 'color' => '#28a745', 'bg' => '#d4edda'),
    3 => array('label' => ' Payée', 'color' => '#007cba', 'bg' => '#cce5f0')
);

$current_status = $statuts[$declaration->status];
$months = array(1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre');

// Informations principales
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';

// Colonne gauche - Informations générales
print '<div>';
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
print '<h3 style="margin: 0 0 15px 0; color: #007cba;"> Informations Générales</h3>';

print '<div style="margin-bottom: 10px;"><strong>Collaborateur :</strong> '.$declaration->collaborator_name.'</div>';
print '<div style="margin-bottom: 10px;"><strong>Période :</strong> '.$months[$declaration->declaration_month].' '.$declaration->declaration_year.'</div>';
print '<div style="margin-bottom: 10px;"><strong>Statut :</strong> <span style="background: '.$current_status['bg'].'; color: '.$current_status['color'].'; padding: 4px 8px; border-radius: 4px; font-weight: bold;">'.$current_status['label'].'</span></div>';
print '<div style="margin-bottom: 10px;"><strong>Créé le :</strong> '.dol_print_date($db->jdate($declaration->date_creation), 'daytext').'</div>';
print '<div style="margin-bottom: 10px;"><strong>Créé par :</strong> '.$declaration->created_by.'</div>';

if ($declaration->date_modification) {
    print '<div style="margin-bottom: 10px;"><strong>Modifié le :</strong> '.dol_print_date($db->jdate($declaration->date_modification), 'daytext').'</div>';
}

print '</div>';

// Note privée
if ($declaration->note_private) {
    print '<div style="background: #e8f5e8; padding: 15px; border-radius: 8px;">';
    print '<h4 style="margin: 0 0 10px 0; color: #2d7d2d;">Note Privée</h4>';
    print '<div>'.nl2br(htmlspecialchars($declaration->note_private)).'</div>';
    print '</div>';
}

print '</div>';

// Colonne droite - Résumé financier
print '<div>';
print '<div style="background: #e8f5e8; padding: 15px; border-radius: 8px;">';
print '<h3 style="margin: 0 0 15px 0; color: #2d7d2d;">💶 Résumé Financier</h3>';

// Calculer le total d'heures
$total_heures = 0;
foreach ($details as $detail) {
    $total_heures += isset($detail->nb_heures) ? floatval($detail->nb_heures) : 8.00;
}

print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
print '<div><strong>Nombre de jours :</strong><br><span style="font-size: 1.2em; color: #2d7d2d;">'.$declaration->total_days.'</span></div>';
print '<div><strong>Total heures :</strong><br><span style="font-size: 1.2em; color: #2d7d2d;">'.number_format($total_heures, 1, ',', ' ').' h</span></div>';
print '</div>';

print '<hr style="margin: 10px 0;">';

print '<div style="margin-bottom: 10px;"><strong>Cachet unitaire :</strong> <span style="font-size: 1.1em; font-weight: bold;">'.price($declaration->cachet_brut_unitaire).' €</span></div>';
print '<div style="margin-bottom: 10px;"><strong>Total cachets bruts :</strong> <span style="font-size: 1.2em; color: #28a745; font-weight: bold;">'.price($declaration->total_cachets).' €</span></div>';

if ($declaration->masse_salariale > 0) {
    print '<div style="margin-bottom: 10px;"><strong>Masse salariale :</strong> <span style="font-size: 1.2em; color: #fd7e14; font-weight: bold;">'.price($declaration->masse_salariale).' €</span></div>';
}

if ($declaration->solde_utilise > 0) {
    print '<div style="margin-bottom: 10px;"><strong>Solde utilisé :</strong> <span style="font-size: 1.2em; color: #dc3545; font-weight: bold;">'.price($declaration->solde_utilise).' €</span></div>';
}

// Calculer le solde restant du collaborateur
$sql_balance = "SELECT 
    COALESCE(SUM(amount), 0) as gross_balance
    FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
    WHERE fk_collaborator = ".(int)$declaration->fk_collaborator." AND status = 1";

$resql_balance = $db->query($sql_balance);
$gross_balance = 0;
if ($resql_balance) {
    $balance_info = $db->fetch_object($resql_balance);
    $gross_balance = (float)$balance_info->gross_balance;
    $db->free($resql_balance);
}

$sql_used = "SELECT 
    COALESCE(SUM(solde_utilise), 0) as total_used
    FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration 
    WHERE fk_collaborator = ".(int)$declaration->fk_collaborator." 
    AND status IN (1, 2, 3)";

$resql_used = $db->query($sql_used);
$total_used = 0;
if ($resql_used) {
    $used_info = $db->fetch_object($resql_used);
    $total_used = (float)$used_info->total_used;
    $db->free($resql_used);
}

$balance_remaining = $gross_balance - $total_used;
$balance_color = $balance_remaining >= 0 ? '#2d7d2d' : '#dc3545';

print '<hr style="margin: 10px 0;">';
print '<div style="margin-bottom: 10px;"><strong>Solde total collaborateur :</strong> <span style="font-size: 1.1em; color: #007cba;">'.price($gross_balance).' €</span></div>';
print '<div style="margin-bottom: 10px;"><strong>Total utilisé (toutes déclarations) :</strong> <span style="font-size: 1.1em; color: #dc3545;">'.price($total_used).' €</span></div>';
print '<div style="margin-bottom: 10px;"><strong>Solde restant disponible :</strong> <span style="font-size: 1.3em; color: '.$balance_color.'; font-weight: bold;">'.price($balance_remaining).' €</span></div>';

print '</div>';
print '</div>';

print '</div>';

// Actions selon le statut
print '<div class="tabsAction">';

if ($declaration->status == 1) { // Brouillon
    print '<a href="salary_declaration_form.php?id='.$id.'" class="butAction"> Modifier</a>';
    print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=validate" class="butAction">Valider</a>';
}

if ($declaration->status == 2) { // Validée
    print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=setpaid" class="butAction"> Marquer Payée</a>';
    print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=reopen" class="butActionSecondary">Remettre en Brouillon</a>';
}

if ($declaration->status == 3) { // Payée
    print '<span style="background: #cce5f0; padding: 8px 12px; border-radius: 4px; color: #007cba;"> Déclaration Payée - Aucune modification possible</span>';
}

print '<a href="salary_declaration_export.php?id='.$id.'&format=pdf" class="butAction"> Export PDF</a>';

if ($declaration->status != 3) { // Pas payée
    print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=delete" class="butActionDelete"> Supprimer</a>';
}

print '<a href="salary_declarations_list.php" class="butAction"> Retour Liste</a>';
print '</div>';

// Détail des jours travaillés
if (!empty($details)) {
    print '<div style="margin-top: 20px;">';
    print '<h3>Détail des Jours Travaillés</h3>';
    
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Date</th>';
    print '<th class="center">Jour</th>';
    print '<th class="center">Contrat</th>';
    print '<th class="center">Métier du Son</th>';
    print '<th class="center">Heures</th>';
    print '<th class="center">Nb Cachets</th>';
    print '<th class="center">Cachet Brut</th>';
    print '</tr>';
    
    $total_displayed = 0;
    foreach ($details as $detail) {
        print '<tr class="oddeven">';
        
        // Date
        $date = dol_print_date($db->jdate($detail->work_date), 'day');
        print '<td>'.$date.'</td>';
        
        // Jour de la semaine
        $day_name = date('l', $db->jdate($detail->work_date));
        $day_names = array(
            'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        );
        $jour = $day_names[$day_name] ?? $day_name;
        print '<td class="center">'.$jour.'</td>';
        
        // Type de contrat
        print '<td class="center">'.$detail->type_contrat.'</td>';
        
        // Métier du son
        $metier_label = getMetierSonLabel($detail->metier_son);
        print '<td class="center">'.$metier_label.'</td>';
        
        // Heures
        $heures = isset($detail->nb_heures) ? floatval($detail->nb_heures) : 8.00;
        print '<td class="center">'.number_format($heures, 1, ',', ' ').' h</td>';
        
        // Nb cachets
        print '<td class="center">'.number_format($detail->nb_cachets, 1).'</td>';
        
        // Cachet brut
        print '<td class="center">'.price($detail->cachet_brut).' €</td>';
        
        print '</tr>';
        
        $total_displayed += $detail->cachet_brut;
    }
    
    // Ligne de total
    print '<tr class="liste_total">';
    print '<td colspan="6" class="right"><strong>TOTAL :</strong></td>';
    print '<td class="center"><strong>'.price($total_displayed).' €</strong></td>';
    print '</tr>';
    
    print '</table>';
    print '</div>';
    print '</div>';
}


llxFooter();
$db->close();
?>
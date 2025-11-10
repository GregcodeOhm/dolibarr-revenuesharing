<?php
/**
 * Fichier de traitement des exports de comptes collaborateurs
 * Fichier: /htdocs/custom/revenuesharing/export_account.php
 */

require_once '../../main.inc.php';
require_once './class/export_account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$format = GETPOST('format', 'alpha'); // 'pdf' ou 'excel'
$filter_type = GETPOST('filter_type', 'alpha');
$filter_year = GETPOST('filter_year', 'int');
$show_previsionnel = GETPOST('show_previsionnel', 'int') ? true : false;

// Email parameters
$email_to = GETPOST('email_to', 'email');
$email_subject = GETPOST('email_subject', 'restricthtml');
$email_message = GETPOST('email_message', 'restricthtml');

if ($id <= 0) {
    setEventMessages('ID collaborateur manquant', null, 'errors');
    header('Location: account_list.php');
    exit;
}

// Vérification du token CSRF
if ($action == 'export' && !newToken('check')) {
    setEventMessages('Token de sécurité invalide', null, 'errors');
    header('Location: account_detail.php?id='.$id);
    exit;
}

if ($action == 'send_email' && $format == 'pdf') {

    // Envoyer le relevé par email
    $export = new ExportAccount($db);
    $export->collaborator_id = $id;

    try {
        if (!$export->loadCollaboratorData($id)) {
            throw new Exception('Impossible de charger les données du collaborateur');
        }

        if (!$export->loadTransactions($filter_type, $filter_year, $show_previsionnel)) {
            throw new Exception('Impossible de charger les transactions');
        }

        $export->loadCAData($filter_year);

        // Générer le PDF dans un fichier temporaire
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

        $tmp_dir = $conf->revenuesharing->dir_temp;
        if (!is_dir($tmp_dir)) {
            dol_mkdir($tmp_dir);
        }

        $filename = 'releve_compte_'.$export->collaborator_data->label.'_'.dol_now().'.pdf';
        $filepath = $tmp_dir.'/'.$filename;

        // Générer le PDF
        $pdf_content = $export->generatePDFContent($filter_type, $filter_year, $show_previsionnel);
        file_put_contents($filepath, $pdf_content);

        // Préparer l'email
        $from = $conf->global->MAIN_MAIL_EMAIL_FROM;
        if (empty($from)) {
            $from = 'noreply@'.$_SERVER['HTTP_HOST'];
        }

        $sendcontext = 'standard';
        $trackid = 'revenuesharing'.$id;

        // Créer l'email avec pièce jointe
        $mail = new CMailFile(
            $email_subject,
            $email_to,
            $from,
            $email_message,
            array($filepath),
            array(),
            array(),
            '',
            '',
            0,
            0,
            '',
            '',
            $trackid,
            '',
            $sendcontext
        );

        $result = $mail->sendfile();

        // Supprimer le fichier temporaire
        @unlink($filepath);

        if ($result) {
            setEventMessages('Email envoyé avec succès à '.$email_to, null, 'mesgs');
        } else {
            setEventMessages('Erreur lors de l\'envoi de l\'email: '.$mail->error, null, 'errors');
        }

        header('Location: account_detail.php?id='.$id);
        exit;

    } catch (Exception $e) {
        setEventMessages('Erreur: '.$e->getMessage(), null, 'errors');
        header('Location: account_detail.php?id='.$id);
        exit;
    }

} elseif ($action == 'export' && in_array($format, array('pdf', 'excel'))) {

    $export = new ExportAccount($db);
    $export->collaborator_id = $id;

    try {
        if ($format == 'pdf') {
            $result = $export->exportToPDF($filter_type, $filter_year, $show_previsionnel);
        } else {
            $result = $export->exportToExcel($filter_type, $filter_year, $show_previsionnel);
        }

        // Les méthodes d'export appellent exit() directement après envoi du fichier
        // Si on arrive ici, c'est qu'il y a eu une erreur dans la génération
        setEventMessages('Erreur lors de la génération du fichier', null, 'errors');
        header('Location: account_detail.php?id='.$id);
        exit;

    } catch (Exception $e) {
        setEventMessages('Erreur lors de l\'export: '.$e->getMessage(), null, 'errors');
        header('Location: account_detail.php?id='.$id);
        exit;
    }

} else {
    // Action non reconnue, retour à la liste
    header('Location: account_detail.php?id='.$id);
    exit;
}
?>
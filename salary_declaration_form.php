<?php
/**
 * Formulaire de déclaration de salaires pour intermittents
 * Fichier: /htdocs/custom/revenuesharing/salary_declaration_form.php
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
$collaborator_id = GETPOST('collaborator_id', 'int');
$declaration_month = GETPOST('declaration_month', 'int') ? GETPOST('declaration_month', 'int') : date('n');
$declaration_year = GETPOST('declaration_year', 'int') ? GETPOST('declaration_year', 'int') : date('Y');

// AJAX - Récupération du solde collaborateur
if ($action == 'get_collaborator_solde') {
    $collaborator_id = GETPOST('collaborator_id', 'int');
    $response = array('success' => false, 'solde' => 0, 'solde_formatted' => '0,00 €');

    if ($collaborator_id > 0) {
        // Calculer le solde du collaborateur
        $sql = "SELECT COALESCE(SUM(amount), 0) as solde
                FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                WHERE fk_collaborator = ".(int)$collaborator_id." AND status = 1";

        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $solde = (float)$obj->solde;

            $response = array(
                'success' => true,
                'solde' => $solde,
                'solde_formatted' => price($solde).' €'
            );
            $db->free($resql);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ACTION SUPPRESSION
if ($action == 'delete' && $id > 0) {
    if (!$user->admin) {
        accessforbidden('Seuls les administrateurs peuvent supprimer les déclarations');
    }

    $confirm = GETPOST('confirm', 'alpha');

    if ($confirm == 'yes') {
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
}

// Variables pour le mode édition
$declaration = null;
$declaration_details = array();
$is_edit_mode = ($id > 0);

if ($is_edit_mode) {
    // Charger la déclaration existante
    $sql = "SELECT d.*, c.label as collaborator_name
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = d.fk_collaborator
            WHERE d.rowid = ".(int)$id;
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $declaration = $db->fetch_object($resql);

        // Vérifier si la déclaration est payée
        if ($declaration->status == 3) {
            setEventMessages('Cette déclaration est payée et ne peut plus être modifiée', null, 'errors');
            header('Location: salary_declaration_detail.php?id='.$id);
            exit;
        }

        $collaborator_id = $declaration->fk_collaborator;
        $declaration_month = $declaration->declaration_month;
        $declaration_year = $declaration->declaration_year;
        $db->free($resql);

        // Charger les détails
        $sql_details = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                       WHERE fk_declaration = ".(int)$id." ORDER BY work_date";
        $resql_details = $db->query($sql_details);
        if ($resql_details) {
            while ($detail = $db->fetch_object($resql_details)) {
                $declaration_details[] = $detail;
            }
            $db->free($resql_details);
        }
    }
}

// Traitement du formulaire
if ($action == 'save') {
    $collaborator_id = GETPOST('collaborator_id', 'int');
    $declaration_month = GETPOST('declaration_month', 'int');
    $declaration_year = GETPOST('declaration_year', 'int');
    $cachet_brut_unitaire = price2num(GETPOST('cachet_brut_unitaire', 'alpha'));
    $masse_salariale = price2num(GETPOST('masse_salariale', 'alpha'));
    $solde_utilise = price2num(GETPOST('solde_utilise', 'alpha'));
    $note_private = GETPOST('note_private', 'restricthtml');

    // Récupérer les dates sélectionnées avec leurs métiers et heures
    $selected_dates = array();
    $selected_days_array = GETPOST('selected_dates', 'array');
    $metiers_array = GETPOST('metiers', 'array');
    $heures_array = GETPOST('heures', 'array');

    if (!empty($selected_days_array)) {
        foreach ($selected_days_array as $day) {
            $day = (int)$day;
            $metier = isset($metiers_array[$day]) ? $metiers_array[$day] : 'technicien_son';
            $heures = isset($heures_array[$day]) ? (float)$heures_array[$day] : 8.0;

            // Validation
            if ($day >= 1 && $day <= 31) {
                $selected_dates[] = array(
                    'day' => $day,
                    'metier' => $metier,
                    'heures' => $heures
                );
            }
        }
    }

    if (empty($collaborator_id) || empty($declaration_month) || empty($declaration_year)) {
        setEventMessages('Veuillez remplir tous les champs obligatoires', null, 'errors');
    } elseif (empty($selected_dates)) {
        setEventMessages('Veuillez sélectionner au moins une date de travail', null, 'errors');
    } else {
        $db->begin();
        $error = 0;

        try {
            if ($is_edit_mode) {
                // Mise à jour
                $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration SET
                        cachet_brut_unitaire = ".$cachet_brut_unitaire.",
                        masse_salariale = ".$masse_salariale.",
                        solde_utilise = ".$solde_utilise.",
                        note_private = '".$db->escape($note_private)."',
                        total_days = ".count($selected_dates).",
                        total_cachets = ".($cachet_brut_unitaire * count($selected_dates)).",
                        date_modification = NOW(),
                        fk_user_modif = ".$user->id."
                        WHERE rowid = ".$id;

                if (!$db->query($sql)) {
                    $error++;
                }

                // Supprimer les anciens détails
                $sql_del = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail WHERE fk_declaration = ".$id;
                if (!$db->query($sql_del)) {
                    $error++;
                }

                $declaration_id = $id;
            } else {
                // Vérifier l'unicité
                $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                             WHERE fk_collaborator = ".$collaborator_id." AND declaration_month = ".$declaration_month." AND declaration_year = ".$declaration_year;
                $resql_check = $db->query($sql_check);
                if ($resql_check && $db->num_rows($resql_check) > 0) {
                    setEventMessages('Une déclaration existe déjà pour ce collaborateur et cette période', null, 'errors');
                    $error++;
                    $db->free($resql_check);
                } else {
                    // Validation et nettoyage des valeurs
                    $safe_cachet_unitaire = (float)$cachet_brut_unitaire;
                    $safe_masse_salariale = (float)$masse_salariale;
                    $safe_solde_utilise = (float)$solde_utilise;
                    $safe_note = $db->escape($note_private);
                    $safe_total_cachets = $safe_cachet_unitaire * count($selected_dates);

                    // Insertion avec valeurs sécurisées
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                            (fk_collaborator, declaration_month, declaration_year, total_days, total_cachets,
                             cachet_brut_unitaire, masse_salariale, solde_utilise, note_private, fk_user_creat)
                            VALUES (".(int)$collaborator_id.", ".(int)$declaration_month.", ".(int)$declaration_year.", ".count($selected_dates).",
                                   ".$safe_total_cachets.", ".$safe_cachet_unitaire.",
                                   ".$safe_masse_salariale.", ".$safe_solde_utilise.", '".$safe_note."', ".(int)$user->id.")";

                    // Log sécurisé sans données sensibles
                    if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
                        error_log('Insertion déclaration salaire - Collaborateur ID: '.$collaborator_id.', Période: '.$declaration_month.'/'.$declaration_year);
                    }

                    if (!$db->query($sql)) {
                        $error++;
                        if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
                            error_log('Erreur insertion déclaration salaire: '.$db->lasterror());
                        }
                        setEventMessages('Erreur SQL détaillée : '.$db->lasterror(), null, 'errors');
                    } else {
                        $declaration_id = $db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_salary_declaration");
                    }
                }
            }

            // Insérer les nouveaux détails
            if (!$error && !empty($declaration_id)) {
                // Vérifier si les colonnes metier_son et nb_heures existent
                $sql_check_metier = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail LIKE 'metier_son'";
                $resql_check_metier = $db->query($sql_check_metier);
                $has_metier_column = ($resql_check_metier && $db->num_rows($resql_check_metier) > 0);
                if ($resql_check_metier) $db->free($resql_check_metier);

                $sql_check_heures = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail LIKE 'nb_heures'";
                $resql_check_heures = $db->query($sql_check_heures);
                $has_heures_column = ($resql_check_heures && $db->num_rows($resql_check_heures) > 0);
                if ($resql_check_heures) $db->free($resql_check_heures);

                foreach ($selected_dates as $day_data) {
                    $day = $day_data['day'];
                    $metier = $day_data['metier'];
                    $heures = $day_data['heures'];
                    $work_date = $declaration_year.'-'.sprintf('%02d', $declaration_month).'-'.sprintf('%02d', $day);

                    // Construire la requête selon les colonnes disponibles
                    $columns = "fk_declaration, work_date, cachet_brut, nb_cachets, type_contrat";
                    $values = $declaration_id.", '".$work_date."', ".$cachet_brut_unitaire.", 1, 'CDDU'";

                    if ($has_metier_column) {
                        $columns .= ", metier_son";
                        $values .= ", '".$db->escape($metier)."'";
                    }

                    if ($has_heures_column) {
                        $columns .= ", nb_heures";
                        $values .= ", ".(float)$heures;
                    }

                    $sql_detail = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                                   (".$columns.")
                                   VALUES (".$values.")";

                    if (!$db->query($sql_detail)) {
                        $error++;
                        if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
                            error_log('Erreur insertion détail déclaration - Jour: '.$day.', Erreur: '.$db->lasterror());
                        }
                        break;
                    } else {
                        // Log sécurisé du succès
                        if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
                            error_log('Insertion détail déclaration OK - Jour: '.$day.', Métier: '.$metier);
                        }
                    }
                }
            }

            if (!$error) {
                $db->commit();
                setEventMessages('Déclaration sauvegardée avec succès', null, 'mesgs');
                header('Location: salary_declarations_list.php');
                exit;
            } else {
                $db->rollback();
                // Le message d'erreur spécifique a déjà été affiché plus haut
            }

        } catch (Exception $e) {
            $db->rollback();
            setEventMessages('Erreur : '.$e->getMessage(), null, 'errors');
            if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
                error_log('Erreur déclaration salaire: '.$e->getMessage());
            }
        }

        // Debug uniquement si erreur non traitée
        if ($error > 0 && defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
            error_log('Erreur SQL déclaration: '.$db->lasterror());
        }
    }
}

llxHeader('', $is_edit_mode ? 'Modifier Déclaration' : 'Nouvelle Déclaration', '');

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

$title = $is_edit_mode ? ' Modifier Déclaration de Salaires' : ' Nouvelle Déclaration de Salaires';
print load_fiche_titre($title, '', 'generic');

// Informations sur les intermittents
print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #856404;">Information Intermittents du Spectacle</h4>';
print '<p style="margin: 0;"><strong>CDDU :</strong> Contrat de travail à Durée Déterminée d\'Usage spécifique aux intermittents du spectacle.</p>';
print '<p style="margin: 5px 0 0 0;"><strong>Cachet :</strong> Rémunération journalière brute de l\'intermittent pour une prestation.</p>';
print '</div>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="declarationForm">';
if ($is_edit_mode) {
    print '<input type="hidden" name="id" value="'.$id.'">';
}
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">';

// === COLONNE GAUCHE ===
print '<div>';

// Sélection collaborateur et période
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
print '<h3 style="margin: 0 0 15px 0; color: #007cba;"> Collaborateur et Période</h3>';

print '<div style="margin-bottom: 15px;">';
print '<label for="collaborator_id" style="display: block; font-weight: bold; margin-bottom: 5px;">Collaborateur * :</label>';
print '<select name="collaborator_id" id="collaborator_id" class="minwidth300" required>';
print '<option value="">-- Sélectionner un collaborateur --</option>';

$sql_collabs = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
$resql_collabs = $db->query($sql_collabs);
if ($resql_collabs) {
    while ($collab = $db->fetch_object($resql_collabs)) {
        $selected = ($collab->rowid == $collaborator_id) ? ' selected' : '';
        print '<option value="'.$collab->rowid.'"'.$selected.'>'.$collab->label.'</option>';
    }
    $db->free($resql_collabs);
}
print '</select>';
print '</div>';

print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
print '<div>';
print '<label for="declaration_month" style="display: block; font-weight: bold; margin-bottom: 5px;">Mois * :</label>';
print '<select name="declaration_month" id="declaration_month" required>';
$months = array(1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre');
foreach ($months as $num => $name) {
    $selected = ($num == $declaration_month) ? ' selected' : '';
    print '<option value="'.$num.'"'.$selected.'>'.$name.'</option>';
}
print '</select>';
print '</div>';

print '<div>';
print '<label for="declaration_year" style="display: block; font-weight: bold; margin-bottom: 5px;">Année * :</label>';
print '<select name="declaration_year" id="declaration_year" required>';
for ($y = date('Y'); $y >= date('Y') - 2; $y--) {
    $selected = ($y == $declaration_year) ? ' selected' : '';
    print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
}
print '</select>';
print '</div>';
print '</div>';

print '</div>';

// Solde disponible
print '<div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 15px;" id="soldeInfo">';
print '<h4 style="margin: 0 0 10px 0; color: #2d7d2d;">Solde Disponible</h4>';
print '<div id="soldeDisplay">Sélectionnez un collaborateur pour voir son solde</div>';
print '</div>';

// Paramètres financiers
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">';
print '<h3 style="margin: 0 0 15px 0; color: #007cba;">Paramètres Financiers</h3>';

print '<div style="margin-bottom: 15px;">';
print '<label for="cachet_brut_unitaire" style="display: block; font-weight: bold; margin-bottom: 5px;">Cachet brut unitaire (€) * :</label>';
print '<input type="number" name="cachet_brut_unitaire" id="cachet_brut_unitaire" step="0.01" min="0" class="minwidth150" value="'.($declaration ? $declaration->cachet_brut_unitaire : '').'" required>';
print '<div style="font-size: 0.9em; color: #666; margin-top: 3px;">Rémunération journalière brute par cachet</div>';
print '</div>';

print '<div style="margin-bottom: 15px;">';
print '<label for="masse_salariale" style="display: block; font-weight: bold; margin-bottom: 5px;">Masse salariale employeur (€) :</label>';
print '<input type="number" name="masse_salariale" id="masse_salariale" step="0.01" min="0" class="minwidth150" value="'.($declaration ? $declaration->masse_salariale : '').'">';
print '<div style="font-size: 0.9em; color: #666; margin-top: 3px;">Coût total employeur (salaire + charges) - Calculé automatiquement</div>';
print '</div>';

print '<div style="margin-bottom: 15px;">';
print '<label for="solde_utilise" style="display: block; font-weight: bold; margin-bottom: 5px;">Solde utilisé (€) :</label>';
print '<input type="number" name="solde_utilise" id="solde_utilise" step="0.01" min="0" class="minwidth150" value="'.($declaration ? $declaration->solde_utilise : '').'">';
print '<div style="font-size: 0.9em; color: #666; margin-top: 3px;">Montant prélevé du compte collaborateur = Masse salariale (calculé automatiquement)</div>';
print '</div>';

print '</div>';

print '</div>';

// === COLONNE DROITE ===
print '<div>';

// Calendrier de sélection
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">';
print '<h3 style="margin: 0 0 15px 0; color: #007cba;">Calendrier - Sélection des jours travaillés</h3>';

// Sélecteur de métier par défaut
print '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
print '<label style="font-weight: bold; display: block; margin-bottom: 5px;"> Métier par défaut (IDCC 2642) :</label>';
print '<select id="defaultMetier" onchange="updateDefaultMetier()" style="width: 100%; padding: 5px;">';
$metiers_son = getMetiersSonIDCC2642();
foreach ($metiers_son as $key => $label) {
    print '<option value="'.$key.'">'.$label.'</option>';
}
print '</select>';
print '<div style="font-size: 0.9em; color: #666; margin-top: 3px;">Ce métier sera appliqué automatiquement aux nouveaux jours sélectionnés</div>';
print '</div>';

// Sélecteur d'heures par défaut
print '<div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
print '<label style="font-weight: bold; display: block; margin-bottom: 5px;">Heures par défaut :</label>';
print '<select id="defaultHeures" onchange="updateDefaultHeures()" style="width: 100%; padding: 5px;">';
$durees_courantes = array(
    '2.00' => '2h - Prestation courte',
    '4.00' => '4h - Demi-journée',
    '6.00' => '6h - Matinée/Soirée',
    '8.00' => '8h - Journée standard (défaut)',
    '10.00' => '10h - Journée longue',
    '12.00' => '12h - Journée très longue'
);
foreach ($durees_courantes as $heures => $description) {
    $selected = ($heures == '8.00') ? ' selected' : '';
    print '<option value="'.$heures.'"'.$selected.'>'.$description.'</option>';
}
print '</select>';
print '<div style="font-size: 0.9em; color: #666; margin-top: 3px;">Ces heures seront appliquées automatiquement aux nouveaux jours sélectionnés</div>';
print '</div>';

print '<div id="calendar-container" style="margin: 15px 0; min-height: 300px; border: 1px solid #ddd; border-radius: 8px; padding: 10px; background: white;">';
print '<div style="text-align: center; color: #666; padding: 20px;">Chargement du calendrier...</div>';
print '</div>';

print '<div style="margin-top: 15px; background: #fff3cd; padding: 10px; border-radius: 4px;">';
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: center;">';
print '<span><strong>Jours sélectionnés :</strong> <span id="selectedDaysCount">0</span></span>';
print '<span><strong>Total heures :</strong> <span id="totalHeures">0,0 h</span></span>';
print '<span><strong>Total cachets :</strong> <span id="totalCachets">0,00 €</span></span>';
print '</div>';
print '</div>';

print '<div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 5px;">';
print '<button type="button" onclick="selectAllDays()" class="button buttonxs">Tout sélectionner</button>';
print '<button type="button" onclick="clearAllDays()" class="button buttonxs">Tout désélectionner</button>';
print '<button type="button" onclick="applyDefaultsToAll()" class="button buttonxs" style="background: #007cba; color: white;">Appliquer défauts à tous</button>';
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    print '<button type="button" onclick="debugSelectedDays()" class="button buttonxs" style="background: #ffc107; color: black;">🐛 Debug heures</button>';
}
print '</div>';

// Section détail des métiers et heures par jour
print '<div style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin-top: 15px;" id="metiersDetails" style="display: none;">';
print '<h4 style="margin: 0 0 10px 0; color: #4a5568;">Métiers et Heures par jour sélectionné</h4>';
print '<div style="font-size: 0.9em; color: #666; margin-bottom: 10px;">Personnalisez le métier et les heures pour chaque jour de travail</div>';
print '<div id="metiersContainer">';
print '<!-- Les sélecteurs de métiers et heures apparaîtront ici -->';
print '</div>';
print '</div>';

print '</div>';

print '</div>';

print '</div>';

// Note privée
print '<div style="margin: 20px 0;">';
print '<label for="note_private" style="display: block; font-weight: bold; margin-bottom: 5px;">Note privée :</label>';
print '<textarea name="note_private" id="note_private" rows="3" class="centpercent">'.($declaration ? $declaration->note_private : '').'</textarea>';
print '</div>';

print '<div class="center">';
print '<input type="submit" value="Sauvegarder" class="button" id="saveButton">';
if ($is_edit_mode) {
    print ' <a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=delete" class="button" style="background: #dc3545; color: white;" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer cette déclaration ?\\nCette action est irréversible.\')">Supprimer</a>';
}
print ' <a href="salary_declarations_list.php" class="button"> Retour</a>';
print '</div>';

print '</form>';

// CSS et JavaScript pour le calendrier
?>
<style>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
    margin-top: 10px;
}
.calendar-header {
    text-align: center;
    font-weight: bold;
    padding: 8px;
    background: #007cba;
    color: white;
    border-radius: 3px;
    font-size: 0.9em;
}
.calendar-day {
    text-align: center;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
.calendar-day:hover {
    background: #e3f2fd;
    border-color: #007cba;
}
.calendar-day.selected {
    background: #2d7d2d;
    color: white;
    border-color: #2d7d2d;
}
.calendar-day.disabled {
    background: #f5f5f5;
    color: #ccc;
    cursor: not-allowed;
    opacity: 0.5;
}
.calendar-day.weekend {
    background: #ffeaa7;
}
.calendar-day.weekend.selected {
    background: #e17055;
}
</style>

<!-- Injection des données existantes pour le mode édition -->
<script>
<?php if ($is_edit_mode && !empty($declaration_details)): ?>
// Données existantes pour le calendrier en mode édition
window.existingDeclarationDays = [
<?php foreach ($declaration_details as $detail): ?>
    {
        day: <?php echo date('j', strtotime($detail->work_date)); ?>,
        metier: '<?php echo isset($detail->metier_son) ? $detail->metier_son : 'technicien_son'; ?>',
        heures: <?php echo isset($detail->nb_heures) ? $detail->nb_heures : 8.0; ?>
    },
<?php endforeach; ?>
];
console.log('📅 Mode édition détecté - Données injectées:', window.existingDeclarationDays);
<?php else: ?>
// Mode création - pas de données existantes
window.existingDeclarationDays = [];
console.log('📝 Mode création - Nouveau formulaire');
<?php endif; ?>
</script>

<!-- Inclusion du fichier JavaScript du calendrier -->
<script src="js/salary-calendar.js"></script>

<!-- Script pour le chargement du solde collaborateur -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour charger le solde
    function loadCollaboratorBalance(collaboratorId) {
        const soldeDisplay = document.getElementById('soldeDisplay');

        if (!collaboratorId) {
            soldeDisplay.innerHTML = 'Sélectionnez un collaborateur pour voir son solde';
            return;
        }

        soldeDisplay.innerHTML = '<em>Chargement du solde...</em>';

        fetch(window.location.pathname + '?action=get_collaborator_solde&collaborator_id=' + collaboratorId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const solde = parseFloat(data.solde) || 0;
                    const color = solde >= 0 ? '#28a745' : '#dc3545';
                    soldeDisplay.innerHTML = `<strong style="color: ${color};">${data.solde_formatted}</strong>`;
                } else {
                    soldeDisplay.innerHTML = '<em style="color: #666;">Solde non disponible</em>';
                }
            })
            .catch(error => {
                console.error('Erreur chargement solde:', error);
                soldeDisplay.innerHTML = '<em style="color: #dc3545;">Erreur de chargement</em>';
            });
    }

    // Gestion du chargement du solde
    const collaboratorSelect = document.getElementById('collaborator_id');
    if (collaboratorSelect) {
        // Listener pour les changements
        collaboratorSelect.addEventListener('change', function() {
            loadCollaboratorBalance(this.value);
        });

        // Chargement initial si collaborateur déjà sélectionné (mode édition)
        if (collaboratorSelect.value) {
            console.log('Mode édition détecté - Chargement du solde pour collaborateur:', collaboratorSelect.value);
            loadCollaboratorBalance(collaboratorSelect.value);
        }
    }
});
</script>
<?php
llxFooter();
$db->close();
?>

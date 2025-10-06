<?php
// Fichier: admin/edit_extrafields.php
// Edition en masse des extrafields des factures clients

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$filter_year = GETPOST('filter_year', 'int') ? GETPOST('filter_year', 'int') : date('Y');
$filter_ref_client = GETPOST('filter_ref_client', 'alpha');
$page = GETPOST('page', 'int') ? GETPOST('page', 'int') : 0;
$limit = 50; // Nombre de factures par page

llxHeader('', 'Edition Extrafields Factures', '');

print load_fiche_titre('Edition des Extrafields - Factures Clients', '', 'generic');

// Traitement de la sauvegarde - uniquement factures modifiées
if ($action == 'save' && $confirm == 'yes') {
    $updates = GETPOST('extrafields', 'array');
    $updated_count = 0;
    $errors = 0;

    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<h3>Sauvegarde des modifications...</h3>';

    if (!empty($updates)) {
        // D'abord, récupérer les valeurs actuelles pour comparaison
        $facture_ids = array_keys($updates);
        $current_values = array();

        if (!empty($facture_ids)) {
            // Récupérer les extrafields ET ref_client depuis la table facture
            $sql_current = "SELECT f.rowid, f.ref_client, fe.intervenant, fe.analytique";
            $sql_current .= " FROM ".MAIN_DB_PREFIX."facture f";
            $sql_current .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid";
            $sql_current .= " WHERE f.rowid IN (".implode(',', array_map('intval', $facture_ids)).")";

            $resql_current = $db->query($sql_current);
            if ($resql_current) {
                while ($obj_current = $db->fetch_object($resql_current)) {
                    $current_values[$obj_current->rowid] = array(
                        'ref_client' => $obj_current->ref_client,
                        'intervenant' => $obj_current->intervenant,
                        'analytique' => $obj_current->analytique
                    );
                }
            }
        }

        foreach ($updates as $facture_id => $fields) {
            $facture_id = (int) $facture_id;

            if ($facture_id > 0) {
                // Comparer avec les valeurs actuelles
                $current_ref_client = isset($current_values[$facture_id]) ? $current_values[$facture_id]['ref_client'] : '';
                $current_intervenant = isset($current_values[$facture_id]) ? $current_values[$facture_id]['intervenant'] : '';
                $current_analytique = isset($current_values[$facture_id]) ? $current_values[$facture_id]['analytique'] : '';

                $new_ref_client = isset($fields['ref_client']) ? trim($fields['ref_client']) : '';
                $new_intervenant = isset($fields['intervenant']) ? trim($fields['intervenant']) : '';
                $new_analytique = isset($fields['analytique']) ? trim($fields['analytique']) : '';

                // Vérifier si il y a vraiment une modification
                $has_changes = false;
                $updates_fields = array();
                $facture_updates = array(); // Pour les champs de la table facture

                if ($new_ref_client !== $current_ref_client) {
                    $facture_updates[] = "ref_client = '".$db->escape($new_ref_client)."'";
                    $has_changes = true;
                }
                if ($new_intervenant !== $current_intervenant) {
                    $updates_fields[] = "intervenant = '".$db->escape($new_intervenant)."'";
                    $has_changes = true;
                }
                if ($new_analytique !== $current_analytique) {
                    $updates_fields[] = "analytique = '".$db->escape($new_analytique)."'";
                    $has_changes = true;
                }

                // Ne traiter que si il y a des changements
                if (!$has_changes) {
                    print '<div style="color: gray; margin: 3px 0; font-size: 0.8em;">⏭️ Facture ID '.$facture_id.' - Aucune modification</div>';
                    continue;
                }

                // Construire la requête UPDATE complète
                $sql_update = "UPDATE ".MAIN_DB_PREFIX."facture_extrafields SET ".implode(', ', $updates_fields)." WHERE fk_object = ".$facture_id;

                print '<div style="color: blue; margin: 3px 0; font-size: 0.8em;">Modification facture ID '.$facture_id.'</div>';

                $success = true;

                // 1. Mettre à jour la table facture si nécessaire
                if (!empty($facture_updates)) {
                    $sql_facture = "UPDATE ".MAIN_DB_PREFIX."facture SET ".implode(', ', $facture_updates)." WHERE rowid = ".$facture_id;
                    $resql_facture = $db->query($sql_facture);
                    if (!$resql_facture) {
                        print '<div style="color: red; margin: 3px 0;">Erreur table facture ID '.$facture_id.' : '.$db->lasterror().'</div>';
                        $success = false;
                    }
                }

                // 2. Mettre à jour les extrafields si nécessaire
                if (!empty($updates_fields) && $success) {
                    // Vérifier si l'enregistrement existe avant UPDATE
                    $sql_check = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."facture_extrafields WHERE fk_object = ".$facture_id;
                    $resql_check = $db->query($sql_check);
                    $obj_check = $db->fetch_object($resql_check);

                    if ($obj_check->nb == 0) {
                        // Créer l'enregistrement s'il n'existe pas
                        $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."facture_extrafields (fk_object, intervenant, analytique) VALUES (".$facture_id.", '".$db->escape($new_intervenant)."', '".$db->escape($new_analytique)."')";
                        $resql_extrafields = $db->query($sql_insert);
                    } else {
                        // Faire l'UPDATE normal
                        $resql_extrafields = $db->query($sql_update);
                    }

                    if (!$resql_extrafields) {
                        print '<div style="color: red; margin: 3px 0;">Erreur extrafields ID '.$facture_id.' : '.$db->lasterror().'</div>';
                        $success = false;
                    }
                }

                if ($success) {
                    print '<div style="color: green; margin: 3px 0;">Facture ID '.$facture_id.' - Modifications sauvegardées</div>';
                    $updated_count++;
                } else {
                    $errors++;
                }
            }
        }
    }

    print '<hr style="margin: 15px 0;">';
    print '<div style="background: #d4edda; padding: 10px; border-radius: 3px; color: #155724;">';
    print '<strong>Résultat :</strong> '.$updated_count.' facture(s) mise(s) à jour, '.$errors.' erreur(s)';
    print '</div>';
    print '</div>';
}

// Affichage du formulaire de filtres
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4>Filtres et Navigation</h4>';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">';

// Filtre par année
print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Année :</label>';
print '<select name="filter_year" style="padding: 5px;">';
for ($y = date('Y'); $y >= date('Y') - 10; $y--) {
    $selected = ($y == $filter_year) ? ' selected' : '';
    print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
}
print '</select>';
print '</div>';

// Filtre par référence client
print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Réf. Client :</label>';
print '<input type="text" name="filter_ref_client" value="'.dol_escape_htmltag($filter_ref_client).'" placeholder="Rechercher réf..." style="padding: 5px; width: 150px;">';
print '</div>';

// Page
print '<input type="hidden" name="page" value="0">';

// Boutons
print '<div style="align-self: end;">';
print '<input type="submit" value="Filtrer" class="button" style="margin-right: 5px;">';
print '<a href="'.$_SERVER["PHP_SELF"].'" class="button">Reset</a>';
print '</div>';

print '</form>';
print '</div>';

// Informations sur les filtres actifs
print '<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">';
print '<strong>Filtres actifs :</strong> Année '.$filter_year;
if ($filter_ref_client) {
    print ' | Réf. client: "'.dol_escape_htmltag($filter_ref_client).'"';
}
print ' | Page '.($page + 1);
print '</div>';

// Récupération des factures avec extrafields
$offset = $page * $limit;
$sql_count = "SELECT COUNT(f.rowid) as total";
$sql_count .= " FROM ".MAIN_DB_PREFIX."facture f";
$sql_count .= " WHERE YEAR(f.datef) = ".((int) $filter_year);
if ($filter_ref_client) {
    $sql_count .= " AND f.ref_client LIKE '%".$db->escape($filter_ref_client)."%'";
}

$sql_factures = "SELECT f.rowid, f.ref, f.ref_client, f.datef, f.total_ht, f.total_ttc, f.fk_statut,";
$sql_factures .= " fe.intervenant, fe.analytique";
$sql_factures .= " FROM ".MAIN_DB_PREFIX."facture f";
$sql_factures .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid";
$sql_factures .= " WHERE YEAR(f.datef) = ".((int) $filter_year);
if ($filter_ref_client) {
    $sql_factures .= " AND f.ref_client LIKE '%".$db->escape($filter_ref_client)."%'";
}
$sql_factures .= " ORDER BY f.datef DESC";
$sql_factures .= " LIMIT ".$limit." OFFSET ".$offset;

// Compter le total
$resql_count = $db->query($sql_count);
$total_records = 0;
if ($resql_count) {
    $obj_count = $db->fetch_object($resql_count);
    $total_records = $obj_count->total;
    $db->free($resql_count);
}

// Récupérer la liste des collaborateurs actifs
$sql_collaborators = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
$resql_collaborators = $db->query($sql_collaborators);
$collaborators = array();
if ($resql_collaborators) {
    while ($obj_collab = $db->fetch_object($resql_collaborators)) {
        $collaborators[$obj_collab->label] = $obj_collab->label;
    }
    $db->free($resql_collaborators);
}

$resql_factures = $db->query($sql_factures);

if ($resql_factures) {
    $nb_records = $db->num_rows($resql_factures);

    if ($nb_records > 0) {
        print '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        print '<h4>'.number_format($total_records, 0, ',', ' ').' facture(s) trouvée(s) pour '.$filter_year.' - Affichage '.($offset + 1).' à '.min($offset + $limit, $total_records).'</h4>';

        // Test diagnostic de la table extrafields
        $sql_test_extrafields = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."facture_extrafields LIMIT 1";
        $resql_test = $db->query($sql_test_extrafields);
        if ($resql_test) {
            $obj_test = $db->fetch_object($resql_test);
            print '<div style="color: blue; font-size: 0.8em;">Diagnostic: Table facture_extrafields contient '.$obj_test->nb.' enregistrement(s)</div>';
            $db->free($resql_test);
        } else {
            print '<div style="color: red; font-size: 0.8em;">Erreur accès table facture_extrafields: '.$db->lasterror().'</div>';
        }

        print '</div>';

        // Navigation pagination
        if ($total_records > $limit) {
            $total_pages = ceil($total_records / $limit);

            print '<div class="center" style="margin: 15px 0;">';
            print '<div style="display: inline-flex; gap: 5px; align-items: center;">';

            // Page précédente
            if ($page > 0) {
                $prev_url = $_SERVER["PHP_SELF"].'?filter_year='.$filter_year.'&page='.($page - 1);
                if ($filter_ref_client) $prev_url .= '&filter_ref_client='.urlencode($filter_ref_client);
                print '<a href="'.$prev_url.'" class="button">« Précédent</a>';
            }

            // Numéros de pages
            $start_page = max(0, $page - 2);
            $end_page = min($total_pages - 1, $page + 2);

            for ($p = $start_page; $p <= $end_page; $p++) {
                if ($p == $page) {
                    print '<span style="background: #007cba; color: white; padding: 5px 10px; border-radius: 3px;">'.($p + 1).'</span>';
                } else {
                    $page_url = $_SERVER["PHP_SELF"].'?filter_year='.$filter_year.'&page='.$p;
                    if ($filter_ref_client) $page_url .= '&filter_ref_client='.urlencode($filter_ref_client);
                    print '<a href="'.$page_url.'" style="padding: 5px 10px; text-decoration: none; border: 1px solid #ddd; border-radius: 3px;">'.($p + 1).'</a>';
                }
            }

            // Page suivante
            if ($page < $total_pages - 1) {
                $next_url = $_SERVER["PHP_SELF"].'?filter_year='.$filter_year.'&page='.($page + 1);
                if ($filter_ref_client) $next_url .= '&filter_ref_client='.urlencode($filter_ref_client);
                print '<a href="'.$next_url.'" class="button">Suivant »</a>';
            }

            print '</div>';
            print '</div>';
        }

        // Formulaire d'édition
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" onsubmit="return confirm(\'Confirmer la mise à jour des extrafields ?\')">';
        print '<input type="hidden" name="action" value="save">';
        print '<input type="hidden" name="confirm" value="yes">';
        print '<input type="hidden" name="filter_year" value="'.$filter_year.'">';
        print '<input type="hidden" name="page" value="'.$page.'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';

        // CSS pour la surbrillance des lignes modifiées
        print '<style>
        .row-modified {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }
        .row-modified td {
            background-color: #fff3cd !important;
        }
        .field-changed {
            background-color: #fffbf0 !important;
            border: 2px solid #ffc107 !important;
        }
        </style>';

        // JavaScript pour détecter les modifications
        print '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Stocker les valeurs initiales
            const initialValues = {};

            // Capturer les valeurs initiales de tous les champs (incluant ref_client)
            document.querySelectorAll("input[name*=extrafields], select[name*=extrafields]").forEach(function(field) {
                const rowId = field.name.match(/extrafields\[(\d+)\]/)[1];
                if (!initialValues[rowId]) initialValues[rowId] = {};

                const fieldName = field.name.match(/\[([^\]]+)\]$/)[1];
                initialValues[rowId][fieldName] = field.value;
            });

            // Fonction pour vérifier si une ligne a été modifiée
            function checkRowModified(rowId) {
                let isModified = false;
                const row = document.querySelector("tr[data-facture-id=\"" + rowId + "\"]");

                if (initialValues[rowId]) {
                    Object.keys(initialValues[rowId]).forEach(function(fieldName) {
                        const field = document.querySelector("input[name=\"extrafields[" + rowId + "][" + fieldName + "]\"], select[name=\"extrafields[" + rowId + "][" + fieldName + "]\"]");
                        if (field && field.value !== initialValues[rowId][fieldName]) {
                            isModified = true;
                            field.classList.add("field-changed");
                        } else if (field) {
                            field.classList.remove("field-changed");
                        }
                    });
                }

                if (row) {
                    if (isModified) {
                        row.classList.add("row-modified");
                    } else {
                        row.classList.remove("row-modified");
                    }
                }
            }

            // Ajouter des écouteurs sur tous les champs
            document.querySelectorAll("input[name*=extrafields], select[name*=extrafields]").forEach(function(field) {
                field.addEventListener("input", function() {
                    const rowId = this.name.match(/extrafields\[(\d+)\]/)[1];
                    checkRowModified(rowId);
                });

                field.addEventListener("change", function() {
                    const rowId = this.name.match(/extrafields\[(\d+)\]/)[1];
                    checkRowModified(rowId);
                });
            });
        });
        </script>';

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Facture</th>';
        print '<th>Date</th>';
        print '<th>Réf. Client</th>';
        print '<th>Total HT</th>';
        print '<th>Statut</th>';
        print '<th>Intervenant</th>';
        print '<th>Analytique</th>';
        print '</tr>';

        // Récupérer la liste des collaborateurs une seule fois
        $collaborators_list = array();
        $sql_collaborators = "SELECT c.rowid, c.label, u.firstname, u.lastname FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
        $sql_collaborators .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
        $sql_collaborators .= " WHERE c.active = 1";
        $sql_collaborators .= " ORDER BY c.label ASC, u.lastname ASC, u.firstname ASC";

        $resql_collaborators = $db->query($sql_collaborators);
        if ($resql_collaborators) {
            while ($obj_collab = $db->fetch_object($resql_collaborators)) {
                $collaborator_name = $obj_collab->label ? $obj_collab->label : trim($obj_collab->firstname.' '.$obj_collab->lastname);
                $collaborators_list[] = $collaborator_name;
            }
        }

        while ($obj_facture = $db->fetch_object($resql_factures)) {
            $statut_libelle = '';
            $statut_class = '';
            switch ($obj_facture->fk_statut) {
                case 0:
                    $statut_libelle = 'Brouillon';
                    $statut_class = 'background: #f8f9fa; color: #6c757d;';
                    break;
                case 1:
                    $statut_libelle = 'Validée';
                    $statut_class = 'background: #d4edda; color: #155724;';
                    break;
                case 2:
                    $statut_libelle = 'Payée';
                    $statut_class = 'background: #d1ecf1; color: #0c5460;';
                    break;
                default:
                    $statut_libelle = '❓ Statut '.$obj_facture->fk_statut;
                    $statut_class = 'background: #fff3cd; color: #856404;';
            }

            print '<tr class="oddeven" data-facture-id="'.$obj_facture->rowid.'">';
            print '<td><strong>'.$obj_facture->ref.'</strong></td>';
            print '<td>'.dol_print_date($db->jdate($obj_facture->datef), 'day').'</td>';
            print '<td>';
            print '<input type="text" name="extrafields['.$obj_facture->rowid.'][ref_client]" value="'.dol_escape_htmltag($obj_facture->ref_client).'" style="width: 100%; padding: 3px;" placeholder="Réf. client...">';
            print '</td>';
            print '<td class="center">'.price($obj_facture->total_ht).'</td>';
            print '<td><span style="padding: 2px 6px; border-radius: 3px; font-size: 0.9em; '.$statut_class.'">'.$statut_libelle.'</span></td>';


            // Champ éditable Intervenant (menu déroulant)
            print '<td>';
            print '<select name="extrafields['.$obj_facture->rowid.'][intervenant]" style="width: 100%; padding: 3px;">';
            print '<option value="">-- Choisir intervenant --</option>';

            // Utiliser la liste des collaborateurs pré-chargée
            foreach ($collaborators_list as $collaborator_name) {
                $selected = ($obj_facture->intervenant == $collaborator_name) ? ' selected' : '';
                print '<option value="'.dol_escape_htmltag($collaborator_name).'"'.$selected.'>'.dol_escape_htmltag($collaborator_name).'</option>';
            }

            print '</select>';
            print '</td>';

            // Champ éditable Analytique
            print '<td>';
            print '<select name="extrafields['.$obj_facture->rowid.'][analytique]" style="width: 100%; padding: 3px;">';
            print '<option value="">-- Choisir --</option>';
            $analytique_options = array(
                'STU' => 'STU (Studio)',
                'FORM' => 'FORM (Formation)',
                'CONS' => 'CONS (Conseil)',
                'LOC' => 'LOC (Location matériel)',
                'VENTE' => 'VENTE (Vente matériel)',
                'Vente Focal' => 'Vente Focal',
                'IMMO' => ' IMMO (Vente immobilisation)',
                'REP' => ' REP (Répétition)',
                'LOC_IMMO' => 'LOC IMMO (Location espaces)',
                'AUTRE' => 'AUTRE (Autre)'
            );

            foreach ($analytique_options as $value => $label) {
                $selected = ($obj_facture->analytique == $value) ? ' selected' : '';
                print '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
            }
            print '</select>';
            print '</td>';

            print '</tr>';
        }

        print '</table>';

        print '<div class="center" style="margin: 20px 0;">';
        print '<input type="submit" class="button" value="Sauvegarder les modifications" style="background: #28a745; color: white; padding: 10px 20px; font-size: 1.1em;">';
        print '</div>';

        print '</form>';

        // Navigation pagination (répétée en bas)
        if ($total_records > $limit) {
            print '<div class="center" style="margin: 15px 0;">';
            print '<div style="display: inline-flex; gap: 5px; align-items: center;">';

            // Page précédente
            if ($page > 0) {
                $prev_url = $_SERVER["PHP_SELF"].'?filter_year='.$filter_year.'&page='.($page - 1);
                if ($filter_ref_client) $prev_url .= '&filter_ref_client='.urlencode($filter_ref_client);
                print '<a href="'.$prev_url.'" class="button">« Précédent</a>';
            }

            // Numéros de pages
            for ($p = $start_page; $p <= $end_page; $p++) {
                if ($p == $page) {
                    print '<span style="background: #007cba; color: white; padding: 5px 10px; border-radius: 3px;">'.($p + 1).'</span>';
                } else {
                    $page_url = $_SERVER["PHP_SELF"].'?filter_year='.$filter_year.'&page='.$p;
                    if ($filter_ref_client) $page_url .= '&filter_ref_client='.urlencode($filter_ref_client);
                    print '<a href="'.$page_url.'" style="padding: 5px 10px; text-decoration: none; border: 1px solid #ddd; border-radius: 3px;">'.($p + 1).'</a>';
                }
            }

            // Page suivante
            if ($page < $total_pages - 1) {
                $next_url = $_SERVER["PHP_SELF"].'?filter_year='.$filter_year.'&page='.($page + 1);
                if ($filter_ref_client) $next_url .= '&filter_ref_client='.urlencode($filter_ref_client);
                print '<a href="'.$next_url.'" class="button">Suivant »</a>';
            }

            print '</div>';
            print '</div>';
        }

    } else {
        print '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;">';
        print '<h4>Aucune facture trouvée</h4>';
        print '<p>Aucune facture trouvée pour l\'année '.$filter_year.'.</p>';
        print '</div>';
    }

    $db->free($resql_factures);
} else {
    print '<div style="color: red;">Erreur SQL : '.$db->lasterror().'</div>';
}

// Modal supprimée - utiliser la page collaborateur standard

// Aide et informations
print '<div style="background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h4>Aide</h4>';
print '<ul>';
print '<li><strong>Intervenant :</strong> Sélectionnez un collaborateur existant ou créez-en un nouveau</li>';
print '<li><strong>Analytique :</strong> Code analytique de la facture :';
print '<ul style="margin-top: 5px;">';
print '<li> <strong>STU</strong> : Studio d\'enregistrement, prise de son, mixage, mastering</li>';
print '<li> <strong>FORM</strong> : Formation</li>';
print '<li><strong>CONS</strong> : Conseil</li>';
print '<li> <strong>LOC</strong> : Location de matériel</li>';
print '<li><strong>VENTE</strong> : Vente de matériel</li>';
print '<li> <strong>Vente Focal</strong> : Vente Focal</li>';
print '<li> <strong>IMMO</strong> : Vente immobilisation</li>';
print '<li> <strong>REP</strong> : Répétition</li>';
print '<li><strong>LOC IMMO</strong> : Location d\'espaces</li>';
print '<li><strong>AUTRE</strong> : Autres prestations</li>';
print '</ul>';
print '</li>';
print '<li><strong>Navigation :</strong> '.$limit.' factures par page, naviguez avec les boutons de pagination</li>';
print '<li><strong>Sauvegarde :</strong> Toutes les modifications sont sauvegardées en une seule fois</li>';
print '</ul>';
print '</div>';

// Boutons d'actions
print '<div class="tabsAction">';
print '<a href="setup.php" class="butAction">Configuration</a>';
print '<a href="../auto_create_contracts.php" class="butAction"> Auto-création Contrats</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

// JavaScript simplifié
print '<script>';
print 'console.log("Page extrafields simplifiée chargée");';
print '</script>';

llxFooter();
$db->close();
?>

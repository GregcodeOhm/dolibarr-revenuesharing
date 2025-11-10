<?php
// Fichier: account_detail.php
// Détail du compte d'un collaborateur avec historique des transactions

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';
require_once __DIR__.'/lib/revenuesharing.lib.php';

// Repositories
require_once __DIR__.'/class/repositories/TransactionRepository.php';
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/repositories/ContractRepository.php';
require_once __DIR__.'/class/repositories/BalanceRepository.php';
require_once __DIR__.'/class/repositories/SalaryDeclarationRepository.php';
require_once __DIR__.'/class/CacheManager.php';
require_once __DIR__.'/lib/pagination.lib.php';

// Initialiser le gestionnaire de cache
$cache = new CacheManager(null, 300, true); // 5 minutes de cache

// Initialiser les repositories avec cache
$transactionRepo = new TransactionRepository($db);
$collaboratorRepo = new CollaboratorRepository($db);
$contractRepo = new ContractRepository($db);
$balanceRepo = new BalanceRepository($db, $cache);
$salaryDeclRepo = new SalaryDeclarationRepository($db);

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$filter_type = GETPOST('filter_type', 'alpha');
$filter_year = GETPOST('filter_year', 'int');
$collaborator_filter = GETPOST('collaborator_filter', 'int') ? GETPOST('collaborator_filter', 'int') : $id;
$show_previsionnel = GETPOST('show_previsionnel', 'alpha') === 'yes';
$page = GETPOST('page', 'int') ? GETPOST('page', 'int') : 1;
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : 50;

if ($id <= 0) {
    print '<div style="color: red;">ID collaborateur manquant</div>';
    llxFooter();
    $db->close();
    exit;
}

llxHeader('', 'Compte Collaborateur', '');

// Récupérer les infos du collaborateur affiché (peut être différent de l'ID d'origine)
$displayed_collaborator_id = $collaborator_filter > 0 ? $collaborator_filter : $id;

try {
    $collaborator = $collaboratorRepo->findById($displayed_collaborator_id);

    if (!$collaborator) {
        throw new Exception('Collaborateur non trouvé (ID: '.$displayed_collaborator_id.')');
    }

    print load_fiche_titre('Compte de '.($collaborator->label), '', 'accounting');

    // Définir les types de transactions pour les filtres et l'affichage
    $type_labels = array(
        'contract' => 'Contrats',
        'commission' => 'Commissions',
        'bonus' => 'Bonus',
        'interest' => 'Intéressements',
        'advance' => 'Avances',
        'fee' => 'Frais',
        'refund' => 'Remboursements',
        'adjustment' => 'Ajustements',
        'salary' => 'Salaires',
        'other_credit' => 'Autres crédits',
        'other_debit' => 'Autres débits'
    );

    // Section de filtres - Utiliser le template
    include __DIR__.'/templates/filters_section.php';

    // Récupérer les soldes via le repository
    $balance_info = $balanceRepo->getBalance($displayed_collaborator_id, [
        'year' => $filter_year,
        'show_previsionnel' => $show_previsionnel
    ]);

    if (!$balance_info) {
        throw new Exception('Erreur lors du calcul du solde');
    }

    // Pour compatibilité avec le code existant
    $previous_balance = $balance_info->previous_balance;

    // Récupérer le chiffre d'affaires via le repository
    $ca_info = $balanceRepo->getTurnover($displayed_collaborator_id, [
        'year' => $filter_year,
        'show_previsionnel' => $show_previsionnel
    ]);

    if (!$ca_info) {
        throw new Exception('Erreur lors du calcul du chiffre d\'affaires');
    }

    // Récupérer les déclarations de salaires via le repository
    $salaires_info = $salaryDeclRepo->getSalaryStatistics($displayed_collaborator_id, [
        'year' => $filter_year
    ]);

    if (!$salaires_info) {
        throw new Exception('Erreur lors de la récupération des déclarations de salaires');
    }
} catch (Exception $e) {
    print '<div style="background: var(--colorbacktabcard1); border: 1px solid #dc3545; color: var(--colortext); padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    llxFooter();
    $db->close();
    exit;
}

// En-tête du compte
print '<div class="fichecenter">';
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';

print '<div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap;">';

// Infos collaborateur
print '<div>';
print '<h3 style="margin: 0 0 10px 0; color: #007cba;"> '.dol_escape_htmltag($collaborator->label).'</h3>';
if ($collaborator->firstname && $collaborator->lastname) {
    print '<p style="margin: 5px 0; color: var(--colortextbackhmenu);"><strong>Nom complet :</strong> '.$collaborator->firstname.' '.$collaborator->lastname.'</p>';
}
if ($collaborator->email) {
    print '<p style="margin: 5px 0; color: var(--colortextbackhmenu);"><strong>Email :</strong> '.$collaborator->email.'</p>';
}
if ($collaborator->default_percentage) {
    print '<p style="margin: 5px 0; color: var(--colortextbackhmenu);"><strong>% défaut :</strong> '.$collaborator->default_percentage.'%</p>';
}
print '<p style="margin: 5px 0; color: var(--colortextbackhmenu);"><strong>Statut :</strong> '.($collaborator->active ? 'Actif' : 'Inactif').'</p>';
print '</div>';

// Afficher le chiffre d'affaires et la répartition du collaborateur
print '<div style="margin-top: 15px; padding: 15px; background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px;">';
print '<h4 style="margin: 0 0 15px 0; color: #2d7d2d;">Chiffre d\'Affaires & Répartition</h4>';

// Indicateur de filtrage prévisionnels
if (!$show_previsionnel) {
    print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 8px; margin-bottom: 10px; text-align: center;">';
    print '<small style="color: #856404;">Contrats prévisionnels masqués</small>';
    print '</div>';
} else {
    print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 4px; padding: 8px; margin-bottom: 10px; text-align: center;">';
    print '<small style="color: #0d47a1;"> Contrats prévisionnels inclus</small>';
    print '</div>';
}

if ($ca_info->ca_total_ht > 0 || $ca_info->ca_previsionnel_ht > 0) {
    
    // Section Chiffre d'Affaires détaillé
    print '<div style="background: white; border-radius: 6px; padding: 12px; margin-bottom: 15px;">';
    print '<h5 style="margin: 0 0 15px 0; color: #2d7d2d;">Chiffre d\'Affaires Détaillé</h5>';
    
    // Ligne 1 : CA Réel et Prévisionnel
    print '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; text-align: center; margin-bottom: 15px;">';
    
    print '<div style="background: #e8f5e8; padding: 12px; border-radius: 4px; border-left: 4px solid #28a745;">';
    print '<div style="font-size: 1.2em; font-weight: bold; color: #155724;">'.price($ca_info->ca_reel_ht).'</div>';
    print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu); margin-bottom: 3px;">CA Réel HT</div>';
    print '<div style="font-size: 0.8em; color: #155724;">'.$ca_info->nb_contrats_reels.' contrat(s) • '.$ca_info->nb_factures_clients.' facture(s)</div>';
    print '</div>';
    
    if ($show_previsionnel && $ca_info->ca_previsionnel_ht > 0) {
        print '<div style="background: #e3f2fd; padding: 12px; border-radius: 4px; border-left: 4px solid #007cba;">';
        print '<div style="font-size: 1.2em; font-weight: bold; color: #007cba;">'.price($ca_info->ca_previsionnel_ht).'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu); margin-bottom: 3px;">CA Prévisionnel HT</div>';
        print '<div style="font-size: 0.8em; color: #007cba;">'.$ca_info->nb_contrats_previsionnel.' contrat(s) • estimations</div>';
        print '</div>';
    } else {
        print '<div style="background: #f8f9fa; padding: 12px; border-radius: 4px; text-align: center; color: #6c757d;">';
        if (!$show_previsionnel) {
            print '<div style="font-size: 0.9em;">Prévisionnels masqués</div>';
        } else {
            print '<div style="font-size: 0.9em;">Aucun prévisionnel</div>';
        }
        print '</div>';
    }
    
    print '<div style="background: #fff3e0; padding: 12px; border-radius: 4px; border-left: 4px solid #f57c00;">';
    print '<div style="font-size: 1.3em; font-weight: bold; color: #f57c00;">'.price($ca_info->ca_total_ht).'</div>';
    print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu); margin-bottom: 3px;">CA Total HT</div>';
    print '<div style="font-size: 0.8em; color: #f57c00;">';
    if ($show_previsionnel && $ca_info->ca_previsionnel_ht > 0) {
        print 'Réel + Prévisionnel';
    } else {
        print 'Réel uniquement';  
    }
    print '</div>';
    print '</div>';
    
    print '</div>';
    print '</div>';
    
    // Section Répartition détaillée
    if ($ca_info->collaborator_total_ht > 0 || $ca_info->studio_total_ht > 0) {
        print '<div style="background: white; border-radius: 6px; padding: 12px;">';
        print '<h5 style="margin: 0 0 15px 0; color: #007cba;">Répartition des Montants</h5>';
        
        // Ligne 1 : Parts collaborateur détaillées
        print '<div style="margin-bottom: 15px;">';
        print '<h6 style="margin: 0 0 8px 0; color: var(--colortextbackhmenu); font-size: 0.9em;"> PARTS COLLABORATEUR</h6>';
        print '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; text-align: center;">';
        
        // Part réelle
        print '<div style="background: #e8f5e8; padding: 10px; border-radius: 4px; border-left: 3px solid #28a745;">';
        print '<div style="font-size: 1.1em; font-weight: bold; color: #155724;">'.price($ca_info->collaborator_reel_ht).'</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu);">Réel</div>';
        print '</div>';
        
        // Part prévisionnelle
        if ($show_previsionnel && $ca_info->collaborator_previsionnel_ht > 0) {
            print '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #007cba;">';
            print '<div style="font-size: 1.1em; font-weight: bold; color: #007cba;">'.price($ca_info->collaborator_previsionnel_ht).'</div>';
            print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu);">Prévisionnel</div>';
            print '</div>';
        } else {
            print '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px; color: #6c757d;">';
            print '<div style="font-size: 0.9em;">-</div>';
            print '<div style="font-size: 0.8em;">Prévisionnel</div>';
            print '</div>';
        }
        
        // Total collaborateur
        $total_repartition = $ca_info->collaborator_total_ht + $ca_info->studio_total_ht;
        $collab_percent = $total_repartition > 0 ? ($ca_info->collaborator_total_ht / $total_repartition * 100) : 0;
        print '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border: 2px solid #007cba;">';
        print '<div style="font-size: 1.2em; font-weight: bold; color: #007cba;">'.price($ca_info->collaborator_total_ht).'</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu);">Total ('.number_format($collab_percent, 1).'%)</div>';
        print '</div>';
        
        print '</div>';
        print '</div>';
        
        // Ligne 2 : Parts structure détaillées
        print '<div style="margin-bottom: 15px;">';
        print '<h6 style="margin: 0 0 8px 0; color: var(--colortextbackhmenu); font-size: 0.9em;"> PARTS STRUCTURE</h6>';
        print '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; text-align: center;">';
        
        // Part structure réelle
        print '<div style="background: #fff3e0; padding: 10px; border-radius: 4px; border-left: 3px solid #f57c00;">';
        print '<div style="font-size: 1.1em; font-weight: bold; color: #f57c00;">'.price($ca_info->studio_reel_ht).'</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu);">Réel</div>';
        print '</div>';
        
        // Part structure prévisionnelle
        if ($show_previsionnel && $ca_info->studio_previsionnel_ht > 0) {
            print '<div style="background: #e0f2f1; padding: 10px; border-radius: 4px; border-left: 3px solid #00695c;">';
            print '<div style="font-size: 1.1em; font-weight: bold; color: #00695c;">'.price($ca_info->studio_previsionnel_ht).'</div>';
            print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu);">Prévisionnel</div>';
            print '</div>';
        } else {
            print '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px; color: #6c757d;">';
            print '<div style="font-size: 0.9em;">-</div>';
            print '<div style="font-size: 0.8em;">Prévisionnel</div>';
            print '</div>';
        }
        
        // Total structure
        $studio_percent = $total_repartition > 0 ? ($ca_info->studio_total_ht / $total_repartition * 100) : 0;
        print '<div style="background: #fff3e0; padding: 10px; border-radius: 4px; border: 2px solid #f57c00;">';
        print '<div style="font-size: 1.2em; font-weight: bold; color: #f57c00;">'.price($ca_info->studio_total_ht).'</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu);">Total ('.number_format($studio_percent, 1).'%)</div>';
        print '</div>';
        
        print '</div>';
        print '</div>';
        
        // Ligne 3 : Statistiques
        if ($ca_info->avg_percentage > 0) {
            print '<div style="text-align: center; background: #f3e5f5; padding: 10px; border-radius: 4px;">';
            print '<span style="color: #7b1fa2; font-weight: bold;">% Moyen collaborateur : '.number_format($ca_info->avg_percentage, 1).'%</span>';
            print '</div>';
        }
        
        print '</div>';
    }
    
    // Info complémentaire
    print '<div style="text-align: center; margin-top: 15px; font-size: 0.9em; color: var(--colortextbackhmenu); background: #f8f9fa; padding: 10px; border-radius: 4px;">';
    print '<span style="margin-right: 15px;">'.$ca_info->nb_contrats_total.' contrat(s) total</span>';
    if ($ca_info->nb_contrats_reels > 0) {
        print '<span style="margin-right: 15px;">'.$ca_info->nb_contrats_reels.' réel(s)</span>';
    }
    if ($show_previsionnel && $ca_info->nb_contrats_previsionnel > 0) {
        print '<span style="margin-right: 15px;"> '.$ca_info->nb_contrats_previsionnel.' prév.</span>';
    }
    if ($filter_year > 0) {
        print '<span>Année '.$filter_year.'</span>';
    } else {
        print '<span>Toutes années</span>';
    }
    print '</div>';
    
} else {
    print '<div style="text-align: center; padding: 20px; color: var(--colortextbackhmenu); font-style: italic;">';
    print '<div style="font-size: 2em; margin-bottom: 10px;"></div>';
    print '<div>Aucune facture client associée';
    if ($filter_year > 0) {
        print ' pour l\'année '.$filter_year;
    }
    print '</div>';
    if ($ca_info->nb_contrats > 0) {
        print '<div style="margin-top: 5px; font-size: 0.9em;">('.$ca_info->nb_contrats.' contrat(s) sans facture)</div>';
    }
    print '</div>';
}
print '</div>';

// Calculer le solde cumulé (nécessaire pour les prévisionnels)
if ($filter_year > 0) {
    $cumulative_balance = $previous_balance + $balance_info->year_balance;
} else {
    $cumulative_balance = $balance_info->year_balance;
}

// Section Déclarations de Salaires
print '<div style="background: white; border-radius: 8px; padding: 20px; margin: 20px 0; border: 1px solid #dee2e6;">';
print '<h3 style="margin: 0 0 15px 0; color: #007cba; display: flex; align-items: center;">';
print '<span style="margin-right: 10px;"></span> Déclarations de Salaires';
if ($filter_year > 0) {
    print ' - '.$filter_year;
}
print '</h3>';

if ($salaires_info->nb_brouillons > 0 || $salaires_info->nb_valides > 0 || $salaires_info->nb_payes > 0) {
    // Statistiques générales
    print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">';
    
    // Brouillons
    if ($salaires_info->nb_brouillons > 0) {
        print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; text-align: center;">';
        print '<div style="font-size: 1.5em; margin-bottom: 5px;"></div>';
        print '<div style="font-size: 1.2em; font-weight: bold; color: #856404;">'.$salaires_info->nb_brouillons.'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">Brouillon(s)</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu); margin-top: 5px;">'.$salaires_info->jours_brouillons.' jour(s)</div>';
        print '</div>';
    }
    
    // Validées
    if ($salaires_info->nb_valides > 0) {
        print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px; text-align: center;">';
        print '<div style="font-size: 1.5em; margin-bottom: 5px;"></div>';
        print '<div style="font-size: 1.2em; font-weight: bold; color: #155724;">'.$salaires_info->nb_valides.'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">Validée(s)</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu); margin-top: 5px;">'.$salaires_info->jours_valides.' jour(s)</div>';
        print '</div>';
    }
    
    // Payées
    if ($salaires_info->nb_payes > 0) {
        print '<div style="background: #cce5f0; border: 1px solid #9fc5e8; border-radius: 8px; padding: 15px; text-align: center;">';
        print '<div style="font-size: 1.5em; margin-bottom: 5px;"></div>';
        print '<div style="font-size: 1.2em; font-weight: bold; color: #007cba;">'.$salaires_info->nb_payes.'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">Payée(s)</div>';
        print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu); margin-top: 5px;">'.$salaires_info->jours_payes.' jour(s)</div>';
        print '</div>';
    }
    
    print '</div>';
    
    // Section prévisionnel si il y a des brouillons ou validées
    if ($salaires_info->montant_previsionnel > 0) {
        print '<div style="background: var(--colorbacktabcard1); border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 15px 0;">';
        print '<h4 style="margin: 0 0 10px 0; color: var(--colortext);">Impact Prévisionnel</h4>';
        print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
        
        print '<div style="text-align: center;">';
        print '<div style="font-size: 1.1em; color: #dc3545;">- '.price($salaires_info->montant_previsionnel).'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">Solde à déduire</div>';
        print '</div>';
        
        $solde_previsionnel = $cumulative_balance - $salaires_info->montant_previsionnel;
        $color_previsionnel = $solde_previsionnel >= 0 ? '#28a745' : '#dc3545';
        print '<div style="text-align: center;">';
        print '<div style="font-size: 1.3em; font-weight: bold; color: '.$color_previsionnel.';">'.price($solde_previsionnel).'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">Solde prévisionnel</div>';
        print '</div>';
        
        print '</div>';
        
        if ($salaires_info->nb_brouillons > 0) {
            print '<div style="font-size: 0.8em; color: var(--colortextbackhmenu); margin-top: 10px; text-align: center;">';
            print 'Inclut '.$salaires_info->nb_brouillons.' brouillon(s) non validé(s)';
            print '</div>';
        }
        print '</div>';
    }
    
    // Lien vers les déclarations
    print '<div style="text-align: center; margin-top: 15px;">';
    print '<a href="salary_declarations_list.php?collaborator_filter='.$displayed_collaborator_id.'" class="button">';
    print img_picto('', 'eye', 'class="pictofixedwidth"').' Voir toutes les déclarations</a>';
    print '</div>';
    
} else {
    print '<div style="text-align: center; padding: 20px; color: var(--colortextbackhmenu);">';
    print '<div style="font-size: 2em; margin-bottom: 10px;"></div>';
    print '<div>Aucune déclaration de salaire';
    if ($filter_year > 0) {
        print ' pour '.$filter_year;
    }
    print '</div>';
    print '<div style="margin-top: 10px;">';
    print '<a href="salary_declaration_form.php?collaborator_id='.$displayed_collaborator_id.'" class="button">';
    print img_picto('', 'add', 'class="pictofixedwidth"').' Créer une déclaration</a>';
    print '</div>';
    print '</div>';
}

print '</div>';

// Résumé financier avec solde cumulé
if ($filter_year > 0) {
    $cumulative_balance = $previous_balance + $balance_info->year_balance;
} else {
    $cumulative_balance = $balance_info->year_balance;
}

$balance_color = ($cumulative_balance >= 0) ? '#28a745' : '#dc3545';
print '<div style="text-align: right;">';
print '<div style="margin-top: 10px; font-size: 0.9em;">';

if ($filter_year > 0) {
    // Affichage détaillé pour une année filtrée - solde reporté d'abord
    print '<span class="opacitymedium">Solde reporté: '.price($previous_balance).'</span><br>';
    print '<span style="color: green;">Crédits '.$filter_year.': '.price($balance_info->year_credits).'</span><br>';
    print '<span style="color: red;">Débits '.$filter_year.': '.price($balance_info->year_debits).'</span><br>';
    print '<span class="opacitymedium">'.$balance_info->nb_transactions.' transaction(s) en '.$filter_year.'</span><br>';
} else {
    // Affichage global
    print '<span style="color: green;">Crédits: '.price($balance_info->year_credits).'</span><br>';
    print '<span style="color: red;">Débits: '.price($balance_info->year_debits).'</span><br>';
    print '<span class="opacitymedium">'.$balance_info->nb_transactions.' transaction(s)</span><br>';
}

// Solde cumulé en dernier, mis en évidence
print '<div style="font-size: 2em; font-weight: bold; color: '.$balance_color.'; margin-top: 10px;">'.price($cumulative_balance).'</div>';
if ($filter_year > 0) {
    print '<div style="color: var(--colortextbackhmenu); font-size: 0.9em;">Solde cumulé au '.$filter_year.'</div>';
} else {
    print '<div style="color: var(--colortextbackhmenu); font-size: 0.9em;">Solde actuel</div>';
}

// Indication sur l'inclusion/exclusion des prévisionnels dans le solde
if ($show_previsionnel) {
    print '<div style="color: #007cba; font-size: 0.8em; margin-top: 5px; font-style: italic;"> Inclut les contrats prévisionnels</div>';
} else {
    print '<div style="color: var(--colortextbackhmenu); font-size: 0.8em; margin-top: 5px; font-style: italic;">Contrats réels uniquement</div>';
}
if ($balance_info->last_transaction_date) {
    print '<br><span class="opacitymedium">'.dol_print_date($db->jdate($balance_info->last_transaction_date), 'day').'</span>';
}
print '</div>';
print '</div>';

print '</div>';
print '</div>';

// Statistiques par type d'opération via le repository
$statistics = $balanceRepo->getStatisticsByType($displayed_collaborator_id, [
    'year' => $filter_year,
    'show_previsionnel' => $show_previsionnel
]);

if (count($statistics) > 0) {
    if ($filter_year > 0) {
        print '<h4>Répartition par type d\'opération ('.$filter_year.')</h4>';
    } else {
        print '<h4>Répartition par type d\'opération</h4>';
    }
    print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">';

    foreach ($statistics as $stat) {
        $color = ($stat->total_amount >= 0) ? '#d4edda' : '#f8d7da';
        $text_color = ($stat->total_amount >= 0) ? '#155724' : '#721c24';

        print '<div style="background: '.$color.'; padding: 15px; border-radius: 8px; text-align: center;">';
        print '<div style="font-weight: bold; color: '.$text_color.';">'.$type_labels[$stat->transaction_type].'</div>';
        print '<div style="font-size: 1.2em; font-weight: bold; color: '.$text_color.';">'.price($stat->total_amount).'</div>';
        print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">'.$stat->nb_operations.' opération(s)</div>';
        print '</div>';
    }

    print '</div>';
}

// Récupérer les transactions avec pagination via le repository
$result = $transactionRepo->findByCollaborator($displayed_collaborator_id, [
    'year' => $filter_year,
    'type' => $filter_type,
    'show_previsionnel' => $show_previsionnel,
    'page' => $page,
    'limit' => $limit
]);

$transactions = $result['transactions'];
$total = $result['total'];
$totalPages = $result['pages'];

// Afficher l'erreur SQL si présente (debug)
if (isset($result['error']) && $result['error']) {
    print '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 4px;">';
    print '<strong>Erreur SQL:</strong> '.htmlspecialchars($result['error']);
    print '<br><pre style="font-size: 0.85em; margin-top: 10px; overflow-x: auto;">'.htmlspecialchars($result['sql']).'</pre>';
    print '</div>';
}

// Tableau des transactions - Utiliser le template
include __DIR__.'/templates/transaction_table.php';

print '</div>';

// Section Export
print '<div style="background: #f0f8ff; border: 1px solid #b8d4f0; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #1e6ba8;">Export du relevé de compte</h4>';
print '<p style="margin: 5px 0; color: var(--colortextbackhmenu);">Exportez le relevé de compte avec les filtres actuellement appliqués</p>';

// Formulaire d'export
print '<form method="GET" action="export_account.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 10px;">';
print '<input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="action" value="export">';
print '<input type="hidden" name="token" value="'.newToken().'">';

// Reprendre les filtres actuels
if ($filter_type) {
    print '<input type="hidden" name="filter_type" value="'.$filter_type.'">';
}
if ($filter_year) {
    print '<input type="hidden" name="filter_year" value="'.$filter_year.'">';
}
// Toujours transmettre le paramètre show_previsionnel
print '<input type="hidden" name="show_previsionnel" value="'.($show_previsionnel ? '1' : '0').'">';

print '<button type="submit" name="format" value="pdf" class="butAction" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">'.img_picto('', 'pdf', 'class="pictofixedwidth"').' Export PDF</button>';
print '<button type="submit" name="format" value="excel" class="butAction" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">'.img_picto('', 'object_xls', 'class="pictofixedwidth"').' Export Excel</button>';
print '<button type="button" onclick="showEmailModal()" class="butAction" style="background: #007cba; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">'.img_picto('', 'email', 'class="pictofixedwidth"').' Envoyer par email</button>';

if ($filter_type || $filter_year) {
    print '<small style="color: var(--colortextbackhmenu); font-style: italic;">Avec filtres: ';
    if ($filter_type) print 'Type='.$filter_type.' ';
    if ($filter_year) print 'Année='.$filter_year;
    print '</small>';
}

print '</form>';
print '</div>';

print '<div class="tabsAction">';
print '<a href="account_transaction.php?collaborator_id='.$displayed_collaborator_id.'" class="butAction" style="background: #28a745; color: white;">'.img_picto('', 'add', 'class="pictofixedwidth"').' Nouvelle Opération</a>';
print '<a href="account_list.php" class="butAction">'.img_picto('', 'bank', 'class="pictofixedwidth"').' Tous les Comptes</a>';
print '<a href="collaborator_card.php?id='.$displayed_collaborator_id.'" class="butAction">'.img_picto('', 'user', 'class="pictofixedwidth"').' Fiche Collaborateur</a>';
print '<a href="index.php" class="butAction">'.img_picto('', 'back', 'class="pictofixedwidth"').' Dashboard</a>';
print '</div>';

// Vérifier comment obtenir les labels de types pour JavaScript
$type_labels_js = array(
    'contract' => 'Contrat',
    'commission' => 'Commission',
    'bonus' => 'Bonus', 
    'interest' => 'Intérêt',
    'advance' => 'Avance',
    'fee' => 'Frais',
    'refund' => 'Remboursement',
    'adjustment' => 'Ajustement',
    'salary' => 'Salaire',
    'other_credit' => 'Autre crédit',
    'other_debit' => 'Autre débit'
);
?>

<!-- Modal d'édition de transaction -->
<div id="editTransactionModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 2% auto; border-radius: 8px; width: 90%; max-width: 700px; height: 90%; max-height: 800px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
        
        <!-- En-tête fixe -->
        <div style="padding: 20px 20px 15px 20px; border-bottom: 1px solid #ddd; flex-shrink: 0;">
            <h3 style="margin: 0; color: #007cba;"> Éditer la transaction</h3>
        </div>
        
        <!-- Zone de contenu avec scrollbar -->
        <div style="flex: 1; overflow-y: auto; padding: 20px;">
            <form id="editTransactionForm" method="POST" action="edit_transaction.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="transaction_id" id="edit_transaction_id">
                <input type="hidden" name="collaborator_id" value="<?php echo $id; ?>">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                
                <div style="margin-bottom: 15px;">
                    <label for="edit_transaction_type" style="display: block; font-weight: bold; margin-bottom: 5px;">Type de transaction:</label>
                    <select name="transaction_type" id="edit_transaction_type" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php foreach ($type_labels_js as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit_amount" style="display: block; font-weight: bold; margin-bottom: 5px;">Montant (€):</label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                    <small style="color: var(--colortextbackhmenu); display: block; margin-top: 5px;" id="amount_help">Si vous venez de supprimer une liaison facture, vous pouvez fermer cette fenêtre sans modifier le montant.</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit_description" style="display: block; font-weight: bold; margin-bottom: 5px;">Description:</label>
                    <input type="text" name="description" id="edit_description" autocomplete="off" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                </div>
                
                <!-- Section Libellé (contrat/facture liée) -->
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Réf. client (contrat/facture liée):</label>
                    <div id="edit_label_display" style="background: #f8f9fa; padding: 8px; border: 1px solid #e9ecef; border-radius: 4px; min-height: 20px; color: var(--colortextbackhmenu); font-style: italic; position: relative;">
                        Aucune réf. client (transaction non liée)
                    </div>
                    <div id="edit_label_actions" style="margin-top: 5px; display: none;">
                        <small style="color: #007cba;">
                            <em>La réf. client peut être modifiée via la page de gestion des contrats</em>
                        </small>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="edit_note_private" style="display: block; font-weight: bold; margin-bottom: 5px;">Note privée:</label>
                    <textarea name="note_private" id="edit_note_private" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                </div>
            
            <!-- Section Liaison Contrat/Facture Client (pour les crédits) -->
            <div id="contract_section_modal" style="margin-bottom: 20px; border-top: 1px solid #ddd; padding-top: 15px; display: none;">
                <h4 style="margin: 0 0 10px 0; color: #007cba;">Liaison avec contrat/facture client</h4>
                
                <!-- Contrat actuellement lié -->
                <div id="current_contract" style="margin-bottom: 15px;">
                    <div id="current_contract_info"></div>
                </div>
                
                <!-- Actions sur le contrat -->
                <div style="margin-bottom: 10px;">
                    <button type="button" onclick="editContractWithFallback()" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-right: 10px;"><?php print img_picto('', 'edit'); ?> Éditer le contrat</button>
                    <button type="button" onclick="unlinkContract()" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'unlink'); ?> Délier le contrat</button>
                </div>
                
                <!-- Sélection d'un nouveau contrat -->
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Lier à un contrat:</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="contract_select" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Sélectionner un contrat...</option>
                        </select>
                        <button type="button" onclick="linkContract()" style="background: #007cba; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'link'); ?> Lier</button>
                    </div>
                </div>
                
                <!-- Messages pour contrat -->
                <div id="contract_transaction_messages" style="margin-top: 10px;"></div>
            </div>
            
            <!-- Section Liaison Facture Fournisseur (seulement pour les débits) -->
            <div id="supplier_invoice_section_modal" style="margin-bottom: 20px; border-top: 1px solid #ddd; padding-top: 15px; display: none;">
                <h4 style="margin: 0 0 10px 0; color: #007cba;">Liaison avec facture fournisseur</h4>
                
                <!-- Facture actuellement liée -->
                <div id="current_supplier_invoice" style="margin-bottom: 15px;">
                    <div id="current_invoice_info"></div>
                </div>
                
                <!-- Sélection d'une nouvelle facture -->
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Lier à une facture fournisseur:</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="supplier_invoice_select" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Sélectionner une facture...</option>
                        </select>
                        <button type="button" onclick="linkSupplierInvoice()" style="background: #007cba; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'link'); ?> Lier</button>
                    </div>
                </div>
                
                <!-- Documents de la facture liée -->
                <div id="supplier_invoice_documents" style="margin-top: 15px;">
                    <div id="invoice_documents_list"></div>
                </div>
                
                <!-- Messages -->
                <div id="invoice_messages" style="margin-top: 10px;"></div>
            </div>
            
            </form>
        </div>
        
        <!-- Pied de page fixe avec boutons -->
        <div style="padding: 15px 20px; border-top: 1px solid #ddd; background: #f8f9fa; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" onclick="deleteTransactionFromModal()" id="modal_delete_btn" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">🗑️ Supprimer</button>
            <div>
                <button type="button" onclick="closeEditModal()" id="modal_close_btn" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px; cursor: pointer;">Fermer</button>
                <button type="submit" form="editTransactionForm" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Sauvegarder</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'édition de contrat -->
<div id="contractEditModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 2% auto; border-radius: 8px; width: 90%; max-width: 800px; height: 90%; max-height: 700px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
        
        <!-- En-tête fixe -->
        <div style="padding: 20px 20px 15px 20px; border-bottom: 1px solid #ddd; flex-shrink: 0;">
            <h3 style="margin: 0; color: #007cba;">Éditer le contrat</h3>
        </div>
        
        <!-- Zone de contenu avec scrollbar -->
        <div style="flex: 1; overflow-y: auto; padding: 20px;">
            <form id="contractEditForm" method="POST" action="contract_card_complete.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="contract_edit_id">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                
                <!-- Zone de chargement -->
                <div id="contract_loading" style="text-align: center; padding: 40px; display: none;">
                    <div style="font-size: 2em; margin-bottom: 10px;"></div>
                    <div>Chargement des données du contrat...</div>
                </div>
                
                <!-- Formulaire d'édition -->
                <div id="contract_form_content" style="display: none;">
                    
                    <!-- Informations générales -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0;"> Informations Générales</h4>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Collaborateur:</label>
                            <select name="fk_collaborator" id="contract_collaborator" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                <option value="">-- Sélectionner un collaborateur --</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Réf. client:</label>
                            <input type="text" name="label" id="contract_label" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Date de création:</label>
                            <input type="date" name="date_creation" id="contract_date_creation" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <!-- Montants -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0;">Montants</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Montant HT (€):</label>
                                <input type="number" name="amount_ht" id="contract_amount_ht" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Montant TTC (€):</label>
                                <input type="number" name="amount_ttc" id="contract_amount_ttc" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Pourcentage (%):</label>
                                    <input type="number" name="percentage" id="contract_percentage" step="0.01" min="0" max="100" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                </div>
                                
                                <div>
                                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Montant commission (€):</label>
                                    <input type="number" name="commission_amount" id="contract_commission_amount" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liaison documents -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0;">Liaison Documents</h4>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Facture liée:</label>
                            <div id="contract_facture_info" style="padding: 8px; border: 1px solid #e9ecef; border-radius: 4px; background: white; min-height: 20px;">
                                <span style="color: var(--colortextbackhmenu); font-style: italic;">Aucune facture liée</span>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Devis lié:</label>
                            <div id="contract_propal_info" style="padding: 8px; border: 1px solid #e9ecef; border-radius: 4px; background: white; min-height: 20px;">
                                <span style="color: var(--colortextbackhmenu); font-style: italic;">Aucun devis lié</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="contract_messages" style="margin-top: 15px;"></div>
                    
                </div>
                
            </form>
        </div>
        
        <!-- Pied de page fixe avec boutons -->
        <div style="padding: 15px 20px; border-top: 1px solid #ddd; background: #f8f9fa; flex-shrink: 0; text-align: right;">
            <button type="button" onclick="closeContractEditModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px; cursor: pointer;">Fermer</button>
            <button type="submit" form="contractEditForm" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Sauvegarder</button>
        </div>
    </div>
</div>

<!-- Styles CSS externes -->
<link rel="stylesheet" href="css/account_detail.css">

<!-- Variables JavaScript injectées depuis PHP -->
<script>
// Initialiser les variables globales
document.addEventListener('DOMContentLoaded', function() {
    initAccountDetail({
        typeLabels: <?php echo json_encode($type_labels_js); ?>,
        collaboratorId: <?php echo $displayed_collaborator_id; ?>,
        csrfToken: '<?php echo newToken(); ?>'
    });
});
</script>

<!-- Modal d'envoi par email -->
<div id="emailModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 5% auto; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
        <div style="padding: 20px; border-bottom: 1px solid #ddd;">
            <h3 style="margin: 0; color: #007cba;">Envoyer le relevé de compte par email</h3>
        </div>

        <form method="POST" action="export_account.php" style="padding: 20px;">
            <input type="hidden" name="action" value="send_email">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="format" value="pdf">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <?php if ($filter_type): ?>
            <input type="hidden" name="filter_type" value="<?php echo $filter_type; ?>">
            <?php endif; ?>
            <?php if ($filter_year): ?>
            <input type="hidden" name="filter_year" value="<?php echo $filter_year; ?>">
            <?php endif; ?>
            <input type="hidden" name="show_previsionnel" value="<?php echo $show_previsionnel ? '1' : '0'; ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Format du relevé:</label>
                <div style="display: flex; gap: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="radio" name="email_format" value="pdf" checked style="margin-right: 5px;">
                        <span>PDF (pièce jointe)</span>
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="radio" name="email_format" value="html" style="margin-right: 5px;">
                        <span>HTML (dans le corps du message)</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Adresse email du destinataire:</label>
                <input type="email" name="email_to" id="email_to" value="<?php echo dol_escape_htmltag($collaborator->email); ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Objet de l'email:</label>
                <input type="text" name="email_subject" id="email_subject" value="Votre relevé de compte<?php echo $filter_year ? ' - '.$filter_year : ''; ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Message:</label>
                <textarea name="email_message" id="email_message" rows="6" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">Bonjour <?php echo dol_escape_htmltag($collaborator->label); ?>,

Veuillez trouver ci-joint votre relevé de compte<?php echo $filter_year ? ' pour l\'année '.$filter_year : ''; ?>.

Cordialement,
<?php echo $conf->global->MAIN_INFO_SOCIETE_NOM; ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between;">
                <button type="button" onclick="closeEmailModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Annuler</button>
                <button type="submit" style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'email'); ?> Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
function showEmailModal() {
    document.getElementById('emailModal').style.display = 'block';
}

function closeEmailModal() {
    document.getElementById('emailModal').style.display = 'none';
}

// Fermer le modal si on clique en dehors
window.onclick = function(event) {
    var modal = document.getElementById('emailModal');
    if (event.target == modal) {
        closeEmailModal();
    }
}
</script>

<!-- JavaScript externe -->
<script src="js/account_detail.js"></script>

<?php
llxFooter();
$db->close();
?>
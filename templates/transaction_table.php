<?php
/**
 * Template: Tableau des transactions avec pagination
 * Variables requises:
 * - $transactions: Array des transactions
 * - $total: Nombre total de transactions
 * - $page: Page actuelle
 * - $totalPages: Nombre total de pages
 * - $limit: Nombre d'éléments par page
 * - $type_labels: Array des labels de types
 * - $id: ID du collaborateur
 * - $db: Objet database Dolibarr
 * - $filter_type: Type filtré
 * - $filter_year: Année filtrée
 */

if (!defined('MAIN_DB_PREFIX')) {
    die('Accès interdit');
}

$num_trans = count($transactions);
?>

<h4>Historique des transactions</h4>

<?php if ($num_trans > 0): ?>
    <div class="div-table-responsive-no-min">
        <table class="noborder centpercent">
            <tr class="liste_titre">
                <th>Date</th>
                <th>Type</th>
                <th>Réf. client</th>
                <th>Description</th>
                <th class="center">Montant</th>
                <th class="center">Lié à</th>
                <th class="center">Par</th>
                <th class="center">Actions</th>
            </tr>

            <?php
            $running_balance = 0;
            foreach ($transactions as $trans):
                $running_balance += $trans->amount;

                // Type avec couleur selon crédit/débit
                $credit_types = array('contract', 'commission', 'bonus', 'interest', 'other_credit');
                $is_credit = in_array($trans->transaction_type, $credit_types);
                $type_color = $is_credit ? '#28a745' : '#dc3545';
                $type_bg = $is_credit ? '#d4edda' : '#f8d7da';

                // Montant avec couleur
                if ($trans->amount >= 0) {
                    $amount_color = '#28a745';
                    $amount_bg = '#d4edda';
                } else {
                    $amount_color = '#dc3545';
                    $amount_bg = '#f8d7da';
                }
            ?>
                <tr class="oddeven">
                    <!-- Date -->
                    <td><?php echo dol_print_date($db->jdate($trans->display_date), 'day'); ?></td>

                    <!-- Type -->
                    <td>
                        <span style="color: <?php echo $type_color; ?>; background: <?php echo $type_bg; ?>; padding: 2px 6px; border-radius: 12px; font-size: 0.9em; font-weight: 500;">
                            <?php echo $type_labels[$trans->transaction_type]; ?>
                        </span>
                    </td>

                    <!-- Réf. client -->
                    <td>
                        <?php if ($trans->fk_contract && $trans->contract_label): ?>
                            <?php
                            $label = dol_escape_htmltag($trans->contract_label);
                            if (strlen($label) > 60) $label = substr($label, 0, 60) . '...';
                            ?>
                            <span style="color: #007cba; font-weight: 500;"><?php echo $label; ?></span>
                        <?php elseif ($trans->fk_facture_fourn && $trans->facture_fourn_label): ?>
                            <?php
                            $label = dol_escape_htmltag($trans->facture_fourn_label);
                            if (strlen($label) > 60) $label = substr($label, 0, 60) . '...';
                            ?>
                            <span style="color: #fd7e14; font-weight: 500;"><?php echo $label; ?></span>
                        <?php else: ?>
                            <span style="color: #666; font-style: italic;">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Description -->
                    <td>
                        <?php
                        $description = dol_escape_htmltag($trans->description);
                        if (strlen($description) > 50) $description = substr($description, 0, 50) . '...';
                        ?>
                        <span style="font-weight: 500;"><?php echo $description; ?></span>
                        <?php if ($trans->note_private): ?>
                            <?php
                            $note = dol_escape_htmltag($trans->note_private);
                            if (strlen($note) > 30) $note = substr($note, 0, 30) . '...';
                            ?>
                            <br><small style="color: #888; font-size: 0.8em;"><?php echo $note; ?></small>
                        <?php endif; ?>
                    </td>

                    <!-- Montant -->
                    <td class="center">
                        <span style="color: <?php echo $amount_color; ?>; background: <?php echo $amount_bg; ?>; padding: 4px 8px; border-radius: 8px; font-weight: bold; font-size: 0.95em;">
                            <?php echo price($trans->amount); ?>
                        </span>
                    </td>

                    <!-- Lié à -->
                    <td class="center">
                        <?php if ($trans->fk_contract && $trans->contract_ref): ?>
                            <a href="contract_card_complete.php?id=<?php echo $trans->fk_contract; ?>"><?php echo $trans->contract_ref; ?></a>
                        <?php elseif ($trans->fk_facture && $trans->facture_ref): ?>
                            <?php echo $trans->facture_ref; ?>
                        <?php elseif ($trans->fk_facture_fourn && $trans->facture_fourn_ref): ?>
                            <?php echo $trans->facture_fourn_ref; ?> <small style="color: #666;">(Fournisseur)</small>
                        <?php else: ?>
                            <span style="color: #ccc;">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Créé par -->
                    <td class="center">
                        <small><?php echo $trans->user_login; ?></small>
                    </td>

                    <!-- Actions -->
                    <td class="center">
                        <div class="transaction-actions" data-transaction-id="<?php echo $trans->rowid; ?>">
                            <!-- Bouton Éditer -->
                            <button class="btn-edit-transaction" title="Éditer cette transaction"
                                data-transaction-id="<?php echo $trans->rowid; ?>"
                                data-amount="<?php echo $trans->amount; ?>"
                                data-description="<?php echo dol_escape_htmltag($trans->description); ?>"
                                data-note="<?php echo dol_escape_htmltag($trans->note_private); ?>"
                                data-type="<?php echo $trans->transaction_type; ?>"
                                style="background: #007cba; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer; margin-right: 5px;">
                                <?php echo img_picto('', 'edit'); ?>
                            </button>

                            <!-- Bouton Supprimer (seulement pour transactions non liées) -->
                            <?php if (!$trans->fk_contract && !$trans->fk_facture && !$trans->fk_facture_fourn): ?>
                                <button class="btn-delete-transaction" title="Supprimer cette transaction"
                                    data-transaction-id="<?php echo $trans->rowid; ?>"
                                    onclick="confirmDeleteTransaction(<?php echo $trans->rowid; ?>, '<?php echo $id; ?>')"
                                    style="background: #dc3545; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;">
                                    <?php echo img_picto('', 'delete'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    $params = $_GET;
    unset($params['page']);
    echo generatePagination($page, $totalPages, $total, $limit, $_SERVER['PHP_SELF'], $params);
    ?>

<?php else: ?>
    <!-- Aucune transaction -->
    <div style="text-align: center; padding: 40px; color: #666;">
        <div style="font-size: 3em;"></div>
        <h3>Aucune transaction trouvée</h3>
        <?php if ($filter_type || $filter_year): ?>
            <p>Essayez de modifier les filtres ci-dessus</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

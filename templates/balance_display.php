<?php
/**
 * Template: Affichage du solde et des informations du collaborateur
 * Variables requises:
 * - $collaborator: Objet collaborateur
 * - $show_previsionnel: Booléen pour afficher les prévisionnels
 * - $ca_info: Objet avec les infos de CA
 * - $filter_year: Année filtrée (0 = toutes)
 */

if (!defined('MAIN_DB_PREFIX')) {
    die('Accès interdit');
}
?>

<div class="fichecenter">
    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">

        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap;">

            <!-- Infos collaborateur -->
            <div>
                <h3 style="margin: 0 0 10px 0; color: #007cba;"><?php echo dol_escape_htmltag($collaborator->label); ?></h3>
                <?php if ($collaborator->firstname && $collaborator->lastname): ?>
                    <p style="margin: 5px 0; color: #666;"><strong>Nom complet :</strong> <?php echo $collaborator->firstname.' '.$collaborator->lastname; ?></p>
                <?php endif; ?>
                <?php if ($collaborator->email): ?>
                    <p style="margin: 5px 0; color: #666;"><strong>Email :</strong> <?php echo $collaborator->email; ?></p>
                <?php endif; ?>
                <?php if ($collaborator->default_percentage): ?>
                    <p style="margin: 5px 0; color: #666;"><strong>% défaut :</strong> <?php echo $collaborator->default_percentage; ?>%</p>
                <?php endif; ?>
                <p style="margin: 5px 0; color: #666;"><strong>Statut :</strong> <?php echo ($collaborator->active ? 'Actif' : 'Inactif'); ?></p>
            </div>

            <!-- Chiffre d'Affaires & Répartition -->
            <div style="margin-top: 15px; padding: 15px; background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px;">
                <h4 style="margin: 0 0 15px 0; color: #2d7d2d;">Chiffre d'Affaires & Répartition</h4>

                <!-- Indicateur de filtrage prévisionnels -->
                <?php if (!$show_previsionnel): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 8px; margin-bottom: 10px; text-align: center;">
                        <small style="color: #856404;">Contrats prévisionnels masqués</small>
                    </div>
                <?php else: ?>
                    <div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 4px; padding: 8px; margin-bottom: 10px; text-align: center;">
                        <small style="color: #0d47a1;">Contrats prévisionnels inclus</small>
                    </div>
                <?php endif; ?>

                <?php if ($ca_info->ca_total_ht > 0 || $ca_info->ca_previsionnel_ht > 0): ?>

                    <!-- Section Chiffre d'Affaires détaillé -->
                    <div style="background: white; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
                        <h5 style="margin: 0 0 15px 0; color: #2d7d2d;">Chiffre d'Affaires Détaillé</h5>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; text-align: center; margin-bottom: 15px;">
                            <!-- CA Réel -->
                            <div style="background: #e8f5e8; padding: 12px; border-radius: 4px; border-left: 4px solid #28a745;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #155724;"><?php echo price($ca_info->ca_reel_ht); ?></div>
                                <div style="font-size: 0.9em; color: #666; margin-bottom: 3px;">CA Réel HT</div>
                                <div style="font-size: 0.8em; color: #155724;"><?php echo $ca_info->nb_contrats_reels; ?> contrat(s) • <?php echo $ca_info->nb_factures_clients; ?> facture(s)</div>
                            </div>

                            <!-- CA Prévisionnel -->
                            <?php if ($show_previsionnel && $ca_info->ca_previsionnel_ht > 0): ?>
                                <div style="background: #e3f2fd; padding: 12px; border-radius: 4px; border-left: 4px solid #007cba;">
                                    <div style="font-size: 1.2em; font-weight: bold; color: #007cba;"><?php echo price($ca_info->ca_previsionnel_ht); ?></div>
                                    <div style="font-size: 0.9em; color: #666; margin-bottom: 3px;">CA Prévisionnel HT</div>
                                    <div style="font-size: 0.8em; color: #007cba;"><?php echo $ca_info->nb_contrats_previsionnel; ?> contrat(s) • estimations</div>
                                </div>
                            <?php else: ?>
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 4px; text-align: center; color: #6c757d;">
                                    <div style="font-size: 0.9em;"><?php echo (!$show_previsionnel ? 'Prévisionnels masqués' : 'Aucun prévisionnel'); ?></div>
                                </div>
                            <?php endif; ?>

                            <!-- CA Total -->
                            <div style="background: #fff3e0; padding: 12px; border-radius: 4px; border-left: 4px solid #f57c00;">
                                <div style="font-size: 1.3em; font-weight: bold; color: #f57c00;"><?php echo price($ca_info->ca_total_ht); ?></div>
                                <div style="font-size: 0.9em; color: #666; margin-bottom: 3px;">CA Total HT</div>
                                <div style="font-size: 0.8em; color: #f57c00;">
                                    <?php echo ($show_previsionnel && $ca_info->ca_previsionnel_ht > 0) ? 'Réel + Prévisionnel' : 'Réel uniquement'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Répartition détaillée -->
                    <?php if ($ca_info->collaborator_total_ht > 0 || $ca_info->studio_total_ht > 0): ?>
                        <div style="background: white; border-radius: 6px; padding: 12px;">
                            <h5 style="margin: 0 0 15px 0; color: #007cba;">Répartition des Montants</h5>

                            <!-- Parts collaborateur -->
                            <div style="margin-bottom: 15px;">
                                <h6 style="margin: 0 0 8px 0; color: #666; font-size: 0.9em;">PARTS COLLABORATEUR</h6>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; text-align: center;">
                                    <div style="background: #e8f5e8; padding: 10px; border-radius: 4px; border-left: 3px solid #28a745;">
                                        <div style="font-size: 1.1em; font-weight: bold; color: #155724;"><?php echo price($ca_info->collaborator_reel_ht); ?></div>
                                        <div style="font-size: 0.8em; color: #666;">Réel</div>
                                    </div>

                                    <?php if ($show_previsionnel && $ca_info->collaborator_previsionnel_ht > 0): ?>
                                        <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #007cba;">
                                            <div style="font-size: 1.1em; font-weight: bold; color: #007cba;"><?php echo price($ca_info->collaborator_previsionnel_ht); ?></div>
                                            <div style="font-size: 0.8em; color: #666;">Prévisionnel</div>
                                        </div>
                                    <?php else: ?>
                                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; color: #6c757d;">
                                            <div style="font-size: 0.9em;">-</div>
                                            <div style="font-size: 0.8em;">Prévisionnel</div>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    $total_repartition = $ca_info->collaborator_total_ht + $ca_info->studio_total_ht;
                                    $collab_percent = $total_repartition > 0 ? ($ca_info->collaborator_total_ht / $total_repartition * 100) : 0;
                                    ?>
                                    <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border: 2px solid #007cba;">
                                        <div style="font-size: 1.2em; font-weight: bold; color: #007cba;"><?php echo price($ca_info->collaborator_total_ht); ?></div>
                                        <div style="font-size: 0.8em; color: #666;">Total (<?php echo number_format($collab_percent, 1); ?>%)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Parts structure -->
                            <div style="margin-bottom: 15px;">
                                <h6 style="margin: 0 0 8px 0; color: #666; font-size: 0.9em;">PARTS STRUCTURE</h6>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; text-align: center;">
                                    <div style="background: #fff3e0; padding: 10px; border-radius: 4px; border-left: 3px solid #f57c00;">
                                        <div style="font-size: 1.1em; font-weight: bold; color: #f57c00;"><?php echo price($ca_info->studio_reel_ht); ?></div>
                                        <div style="font-size: 0.8em; color: #666;">Réel</div>
                                    </div>

                                    <?php if ($show_previsionnel && $ca_info->studio_previsionnel_ht > 0): ?>
                                        <div style="background: #e0f2f1; padding: 10px; border-radius: 4px; border-left: 3px solid #00695c;">
                                            <div style="font-size: 1.1em; font-weight: bold; color: #00695c;"><?php echo price($ca_info->studio_previsionnel_ht); ?></div>
                                            <div style="font-size: 0.8em; color: #666;">Prévisionnel</div>
                                        </div>
                                    <?php else: ?>
                                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; color: #6c757d;">
                                            <div style="font-size: 0.9em;">-</div>
                                            <div style="font-size: 0.8em;">Prévisionnel</div>
                                        </div>
                                    <?php endif; ?>

                                    <?php $studio_percent = $total_repartition > 0 ? ($ca_info->studio_total_ht / $total_repartition * 100) : 0; ?>
                                    <div style="background: #fff3e0; padding: 10px; border-radius: 4px; border: 2px solid #f57c00;">
                                        <div style="font-size: 1.2em; font-weight: bold; color: #f57c00;"><?php echo price($ca_info->studio_total_ht); ?></div>
                                        <div style="font-size: 0.8em; color: #666;">Total (<?php echo number_format($studio_percent, 1); ?>%)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistiques -->
                            <?php if ($ca_info->avg_percentage > 0): ?>
                                <div style="text-align: center; background: #f3e5f5; padding: 10px; border-radius: 4px;">
                                    <span style="color: #7b1fa2; font-weight: bold;">% Moyen collaborateur : <?php echo number_format($ca_info->avg_percentage, 1); ?>%</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Info complémentaire -->
                    <div style="text-align: center; margin-top: 15px; font-size: 0.9em; color: #666; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                        <span style="margin-right: 15px;"><?php echo $ca_info->nb_contrats_total; ?> contrat(s) total</span>
                        <?php if ($ca_info->nb_contrats_reels > 0): ?>
                            <span style="margin-right: 15px;"><?php echo $ca_info->nb_contrats_reels; ?> réel(s)</span>
                        <?php endif; ?>
                        <?php if ($show_previsionnel && $ca_info->nb_contrats_previsionnel > 0): ?>
                            <span style="margin-right: 15px;"><?php echo $ca_info->nb_contrats_previsionnel; ?> prév.</span>
                        <?php endif; ?>
                        <span><?php echo ($filter_year > 0 ? 'Année '.$filter_year : 'Toutes années'); ?></span>
                    </div>

                <?php else: ?>
                    <!-- Aucune facture -->
                    <div style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                        <div style="font-size: 2em; margin-bottom: 10px;"></div>
                        <div>Aucune facture client associée<?php echo ($filter_year > 0 ? ' pour l\'année '.$filter_year : ''); ?></div>
                        <?php if ($ca_info->nb_contrats > 0): ?>
                            <div style="margin-top: 5px; font-size: 0.9em;">(<?php echo $ca_info->nb_contrats; ?> contrat(s) sans facture)</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

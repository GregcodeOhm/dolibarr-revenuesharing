<?php
/**
 * Métiers du son selon la convention collective IDCC 2642
 * Fichier: /htdocs/custom/revenuesharing/lib/metiers_son.php
 */

/**
 * Retourne la liste des métiers du son selon la convention collective IDCC 2642
 * Production audiovisuelle
 */
function getMetiersSonIDCC2642() {
    return array(
        '' => '-- Sélectionner un métier --',
        'assistant_son' => 'Assistant son',
        'chef_operateur_son' => 'Chef opérateur du son',
        'ingenieur_son' => 'Ingénieur du son',
        'mixeur' => 'Mixeur',
        'operateur_prise_son' => 'Opérateur de prise de son',
        'perchman' => 'Perchman',
        'preneur_son' => 'Preneur de son',
        'regisseur_son' => 'Régisseur son',
        'sound_designer' => 'Sound designer',
        'technicien_antenne' => 'Technicien antenne',
        'technicien_audionumerique' => 'Technicien audionumérique',
        'technicien_son' => 'Technicien son',
        'technicien_sonorisation' => 'Technicien sonorisation'
    );
}

/**
 * Retourne le libellé d'un métier à partir de sa clé
 */
function getMetierSonLabel($metier_key) {
    $metiers = getMetiersSonIDCC2642();
    return isset($metiers[$metier_key]) ? $metiers[$metier_key] : $metier_key;
}

/**
 * Retourne les métiers organisés par catégorie
 */
function getMetiersSonParCategorie() {
    return array(
        'Encadrement' => array(
            'chef_operateur_son' => 'Chef opérateur du son',
            'ingenieur_son' => 'Ingénieur du son',
            'regisseur_son' => 'Régisseur son'
        ),
        'Prise de son' => array(
            'operateur_prise_son' => 'Opérateur de prise de son',
            'preneur_son' => 'Preneur de son',
            'perchman' => 'Perchman',
            'assistant_son' => 'Assistant son'
        ),
        'Post-production' => array(
            'mixeur' => 'Mixeur',
            'sound_designer' => 'Sound designer'
        ),
        'Technique' => array(
            'technicien_son' => 'Technicien son',
            'technicien_audionumerique' => 'Technicien audionumérique',
            'technicien_antenne' => 'Technicien antenne',
            'technicien_sonorisation' => 'Technicien sonorisation'
        )
    );
}

/**
 * Retourne la description d'un métier
 */
function getMetierSonDescription($metier_key) {
    $descriptions = array(
        'assistant_son' => 'Assiste l\'ingénieur du son dans ses tâches techniques',
        'chef_operateur_son' => 'Responsable de l\'équipe son et des choix artistiques',
        'ingenieur_son' => 'Responsable technique de la prise de son et du mixage',
        'mixeur' => 'Réalise le mixage final des bandes sonores',
        'operateur_prise_son' => 'Effectue les prises de son en direct',
        'perchman' => 'Manie la perche et les micros-cravates',
        'preneur_son' => 'Responsable de la capture sonore sur le plateau',
        'regisseur_son' => 'Organise et coordonne les équipes son',
        'sound_designer' => 'Crée et conçoit l\'univers sonore',
        'technicien_antenne' => 'Gère les équipements de diffusion',
        'technicien_audionumerique' => 'Spécialiste des systèmes numériques',
        'technicien_son' => 'Maintenance et installation des équipements',
        'technicien_sonorisation' => 'Installation et réglage des systèmes de sonorisation'
    );
    
    return isset($descriptions[$metier_key]) ? $descriptions[$metier_key] : '';
}
?>
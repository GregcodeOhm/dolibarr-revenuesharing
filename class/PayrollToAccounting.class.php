<?php
/**
 * PayrollToAccounting.class.php
 * Classe de conversion des exports de paie vers format comptable Dolibarr
 *
 * @package    RevenueSharing
 * @subpackage Classes
 * @author     Dolibarr Module
 * @license    GPL-3.0+
 */

// Utiliser le module d'export Excel de Dolibarr
require_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_excel2007.modules.php';

/**
 * Classe de conversion des exports de paie vers format comptable
 *
 * Convertit les fichiers texte d'export de paie (format tabulé) vers
 * le format CSV d'import comptable Dolibarr avec 13 colonnes.
 * Note: Le format CSV est préféré car Dolibarr le supporte nativement
 * sans dépendances externes.
 */
class PayrollToAccounting
{
    /** @var DoliDB Instance de connexion à la base de données */
    private $db;

    /** @var array Données parsées du fichier texte source */
    private $data = [];

    /** @var array Erreurs de parsing */
    private $errors = [];

    /** @var string Numéro d'écriture à utiliser */
    private $piece_num = '';

    /** @var string Code journal par défaut */
    private $code_journal = 'SAL';

    /** @var string Libellé du journal par défaut */
    private $journal_label = 'Journal de Paie';

    /**
     * Constructeur
     *
     * @param DoliDB $db Instance de la base de données
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Parse un fichier texte d'export de paie
     *
     * Format attendu (séparé par tabulations):
     * journal | date | ref | compte | vide | libellé | montant_debit | montant_credit
     *
     * @param string $filepath Chemin du fichier à parser
     *
     * @return bool True si succès
     */
    public function parsePayrollFile($filepath)
    {
        if (!file_exists($filepath)) {
            $this->errors[] = "Fichier non trouvé : ".$filepath;
            return false;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            $this->errors[] = "Impossible de lire le fichier";
            return false;
        }

        // Détecter et convertir l'encodage si nécessaire (ISO-8859-1 vers UTF-8)
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $lines = explode("\n", $content);
        $this->data = [];

        foreach ($lines as $line_num => $line) {
            $line = trim($line);

            // Ignorer les lignes vides
            if (empty($line)) {
                continue;
            }

            // Parser la ligne (séparateur: tabulation)
            $parts = explode("\t", $line);

            if (count($parts) < 8) {
                $this->errors[] = "Ligne ".($line_num + 1)." : format invalide (colonnes manquantes)";
                continue;
            }

            // Extraire les données
            $entry = [
                'journal_code' => trim($parts[0]),
                'date' => $this->parseDate(trim($parts[1])),
                'doc_ref' => trim($parts[2]),
                'numero_compte' => trim($parts[3]),
                'label_operation' => trim($parts[5]),
                'debit' => $this->parseAmount(trim($parts[6])),
                'credit' => $this->parseAmount(trim($parts[7])),
            ];

            // Extraire le nom du salarié si présent dans le libellé
            if (preg_match('/\*\*\*\s*\(salari[ée]\s*:\s*([^)]+)\)/i', $entry['label_operation'], $matches)) {
                $entry['employee_name'] = trim($matches[1]);
            }

            $this->data[] = $entry;
        }

        return !empty($this->data);
    }

    /**
     * Parse une date au format JJ/MM/AAAA vers AAAA-MM-JJ
     *
     * @param string $date_str Date au format JJ/MM/AAAA
     *
     * @return string Date au format AAAA-MM-JJ
     */
    private function parseDate($date_str)
    {
        $parts = explode('/', $date_str);
        if (count($parts) == 3) {
            return $parts[2].'-'.$parts[1].'-'.$parts[0];
        }
        return $date_str;
    }

    /**
     * Parse un montant (convertit virgule en point)
     *
     * @param string $amount_str Montant sous forme de chaîne
     *
     * @return float Montant converti
     */
    private function parseAmount($amount_str)
    {
        $amount_str = str_replace(',', '.', $amount_str);
        $amount_str = str_replace(' ', '', $amount_str);
        return (float)$amount_str;
    }

    /**
     * Génère un fichier XLSX au format d'import comptable Dolibarr
     *
     * Utilise le module d'export Excel natif de Dolibarr (ExportExcel2007)
     *
     * Format de sortie (13 colonnes):
     * 1. Num. écriture (b.piece_num)
     * 2. Date (b.doc_date)
     * 3. Pièce (b.doc_ref)
     * 4. Journal (b.code_journal)
     * 5. Libellé journal (b.journal_label)
     * 6. Compte comptable (b.numero_compte)
     * 7. Libellé du compte (b.label_compte)
     * 8. Compte auxiliaire (b.subledger_account)
     * 9. Libellé du compte auxiliaire (b.subledger_label)
     * 10. Libellé opération (b.label_operation)
     * 11. Débit (b.debit)
     * 12. Crédit (b.credit)
     * 13. Direction (b.sens)
     *
     * @param string $output_filepath Chemin du fichier de sortie
     * @param string $piece_num       Numéro d'écriture à utiliser
     *
     * @return bool True si succès
     */
    public function generateAccountingXLS($output_filepath, $piece_num = null)
    {
        if (empty($this->data)) {
            $this->errors[] = "Aucune donnée à exporter. Veuillez d'abord parser un fichier.";
            return false;
        }

        if ($piece_num) {
            $this->piece_num = $piece_num;
        }

        try {
            // Utiliser le module d'export Excel de Dolibarr
            $exporter = new ExportExcel2007($this->db);

            // Ouvrir le fichier
            $exporter->open_file($output_filepath, '');

            // En-tête du fichier
            $headers = [
                'Num. écriture (b.piece_num)',
                'Date (b.doc_date)',
                'Pièce (b.doc_ref)',
                'Journal (b.code_journal)',
                'Libellé journal (b.journal_label)',
                'Compte comptable (b.numero_compte)',
                'Libellé du compte (b.label_compte)',
                'Compte auxiliaire (b.subledger_account)',
                'Libellé du compte auxiliaire (b.subledger_label)',
                'Libellé opération (b.label_operation)',
                'Débit (b.debit)',
                'Crédit (b.credit)',
                'Direction (b.sens)'
            ];

            // Écrire l'en-tête
            $exporter->write_header('Import Comptable', $headers);

            // Écrire les données
            foreach ($this->data as $entry) {
                // Déterminer la direction (sens)
                $sens = '';
                if ($entry['debit'] > 0) {
                    $sens = 'D';
                } elseif ($entry['credit'] > 0) {
                    $sens = 'C';
                }

                $row = [
                    $this->piece_num,
                    $entry['date'],
                    $entry['doc_ref'],
                    $this->code_journal,
                    $this->journal_label,
                    $entry['numero_compte'],
                    '', // Libellé du compte (à remplir par Dolibarr)
                    '', // Compte auxiliaire
                    '', // Libellé compte auxiliaire
                    $entry['label_operation'],
                    $entry['debit'],
                    $entry['credit'],
                    $sens
                ];

                $exporter->write_record($row, $headers);
            }

            // Fermer le fichier
            $exporter->close_file();

            return true;

        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la génération du fichier XLSX : ".$e->getMessage();
            return false;
        }
    }

    /**
     * Alias pour compatibilité
     *
     * @param string $output_filepath Chemin du fichier de sortie
     * @param string $piece_num       Numéro d'écriture à utiliser
     *
     * @return bool True si succès
     */
    public function generateAccountingExcel($output_filepath, $piece_num = null)
    {
        return $this->generateAccountingXLS($output_filepath, $piece_num);
    }

    /**
     * Récupère les données parsées
     *
     * @return array Tableau des entrées parsées
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Récupère les erreurs
     *
     * @return array Tableau des erreurs
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Définit le numéro d'écriture
     *
     * @param string $piece_num Numéro d'écriture
     */
    public function setPieceNum($piece_num)
    {
        $this->piece_num = $piece_num;
    }

    /**
     * Définit le code journal
     *
     * @param string $code        Code du journal
     * @param string $label       Libellé du journal
     */
    public function setJournal($code, $label)
    {
        $this->code_journal = $code;
        $this->journal_label = $label;
    }

    /**
     * Récupère les statistiques de la conversion
     *
     * @return object Objet avec les statistiques
     */
    public function getStatistics()
    {
        $total_debit = 0;
        $total_credit = 0;
        $nb_entries = count($this->data);

        foreach ($this->data as $entry) {
            $total_debit += $entry['debit'];
            $total_credit += $entry['credit'];
        }

        return (object)[
            'nb_entries' => $nb_entries,
            'total_debit' => $total_debit,
            'total_credit' => $total_credit,
            'balance' => $total_debit - $total_credit,
            'is_balanced' => abs($total_debit - $total_credit) < 0.01
        ];
    }
}
?>

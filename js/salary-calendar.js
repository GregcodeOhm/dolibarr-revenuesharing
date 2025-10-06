/**
 * Calendrier de sélection des jours pour les déclarations de salaire
 * Module Revenue Sharing - Dolibarr
 */

// Variables globales du calendrier
let selectedDays = new Map(); // Changed to Map to store day -> {metier, heures}
let defaultMetier = 'technicien_son'; // Métier par défaut
let defaultHeures = 8.00; // Heures par défaut
const months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// Fonction principale de génération du calendrier
function generateCalendar() {
    const monthElement = document.getElementById('declaration_month');
    const yearElement = document.getElementById('declaration_year');
    const containerElement = document.getElementById('calendar-container');

    // Debug : vérifier les éléments
    if (!monthElement || !yearElement || !containerElement) {
        console.error('Éléments du calendrier non trouvés:', {
            month: !!monthElement,
            year: !!yearElement,
            container: !!containerElement
        });
        return;
    }

    const month = parseInt(monthElement.value);
    const year = parseInt(yearElement.value);

    if (!month || !year) {
        console.warn('Mois ou année invalide:', { month, year });
        containerElement.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Veuillez sélectionner un mois et une année</div>';
        return;
    }

    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDay = new Date(year, month - 1, 1).getDay();
    const adjustedFirstDay = firstDay === 0 ? 7 : firstDay; // Lundi = 1, Dimanche = 7

    let calendarHTML = '<div class="calendar-grid">';

    // En-têtes des jours
    const dayHeaders = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    dayHeaders.forEach(day => {
        calendarHTML += `<div class="calendar-header">${day}</div>`;
    });

    // Cases vides pour aligner le premier jour
    for (let i = 1; i < adjustedFirstDay; i++) {
        calendarHTML += '<div class="calendar-day disabled"></div>';
    }

    // Jours du mois
    for (let day = 1; day <= daysInMonth; day++) {
        const dayOfWeek = ((adjustedFirstDay + day - 2) % 7) + 1;
        const isWeekend = (dayOfWeek === 6 || dayOfWeek === 7); // Samedi ou Dimanche
        const isSelected = selectedDays.has(day);

        let classes = 'calendar-day';
        if (isWeekend) classes += ' weekend';
        if (isSelected) classes += ' selected';

        // Ajouter une petite indication du métier et heures si sélectionné
        let dayContent = day;
        if (isSelected) {
            const dayData = selectedDays.get(day);
            const metierShort = dayData.metier ? dayData.metier.substring(0, 4) + '...' : '';
            const heures = dayData.heures || 8;
            dayContent += `<br><small style="font-size: 0.7em;">${metierShort}<br>${heures}h</small>`;
        }

        calendarHTML += `<div class="${classes}" onclick="toggleDay(${day})" data-day="${day}">${dayContent}</div>`;
    }

    calendarHTML += '</div>';
    containerElement.innerHTML = calendarHTML;

    console.log('Calendrier généré pour:', { month, year, days: daysInMonth });
}

// Basculer la sélection d'un jour
function toggleDay(day) {
    if (selectedDays.has(day)) {
        // Désélectionner
        selectedDays.delete(day);
    } else {
        // Ajouter avec le métier et heures par défaut
        selectedDays.set(day, {
            metier: defaultMetier,
            heures: defaultHeures
        });
    }

    generateCalendar(); // Regénérer pour mettre à jour l'affichage
    updateCounters(); // Mettre à jour les compteurs
    updateMetiersDetails(); // Mettre à jour la section détails
}

// Sélectionner tous les jours du mois
function selectAllDays() {
    const month = parseInt(document.getElementById('declaration_month').value);
    const year = parseInt(document.getElementById('declaration_year').value);

    if (!month || !year) return;

    const daysInMonth = new Date(year, month, 0).getDate();

    for (let day = 1; day <= daysInMonth; day++) {
        selectedDays.set(day, {
            metier: defaultMetier,
            heures: defaultHeures
        });
    }

    generateCalendar();
    updateCounters();
    updateMetiersDetails();
}

// Désélectionner tous les jours
function clearAllDays() {
    selectedDays.clear();
    generateCalendar();
    updateCounters();
    updateMetiersDetails();
}

// Mettre à jour le métier par défaut
function updateDefaultMetier() {
    defaultMetier = document.getElementById('defaultMetier').value;
}

// Mettre à jour les heures par défaut
function updateDefaultHeures() {
    defaultHeures = parseFloat(document.getElementById('defaultHeures').value);
}

// Appliquer les valeurs par défaut à tous les jours sélectionnés
function applyDefaultsToAll() {
    if (selectedDays.size === 0) {
        alert('Veuillez d\'abord sélectionner des jours dans le calendrier');
        return;
    }

    // Appliquer les valeurs par défaut à tous les jours sélectionnés
    selectedDays.forEach((dayData, day) => {
        dayData.metier = defaultMetier;
        dayData.heures = defaultHeures;
        selectedDays.set(day, dayData);
    });

    generateCalendar(); // Regénérer le calendrier
    updateCounters(); // Recalculer les totaux
    updateMetiersDetails(); // Mettre à jour la section détails
}

// Debug des jours sélectionnés
function debugSelectedDays() {
    if (typeof console !== 'undefined' && console.log) {
        console.log('=== DEBUG HEURES (mode développement) ===');
        console.log('defaultMetier:', defaultMetier);
        console.log('defaultHeures:', defaultHeures);
        console.log('selectedDays.size:', selectedDays.size);

        selectedDays.forEach((dayData, day) => {
            console.log(`Jour ${day}:`, dayData);
        });
    }

    alert(`Debug affiché dans la console du navigateur.\nJours sélectionnés: ${selectedDays.size}\nDéfaut heures: ${defaultHeures}h`);
}

// Force la régénération du calendrier
function forceRegenerateCalendar() {
    console.log('🔄 Force régénération du calendrier...');

    const container = document.getElementById('calendar-container');
    if (!container) {
        alert('❌ Container calendar-container introuvable !');
        return;
    }

    const month = document.getElementById('declaration_month');
    const year = document.getElementById('declaration_year');

    if (!month || !year) {
        alert('❌ Sélecteurs mois/année introuvables !');
        return;
    }

    console.log('Éléments trouvés - Mois:', month.value, 'Année:', year.value);

    container.innerHTML = '<div style="background: orange; color: white; padding: 20px; text-align: center;">⚡ RÉGÉNÉRATION EN COURS...</div>';

    setTimeout(() => {
        generateCalendar();
        updateCounters();
        updateMetiersDetails();
        console.log('✅ Régénération terminée');
    }, 500);
}

// Mettre à jour la section des détails métiers
function updateMetiersDetails() {
    const container = document.getElementById('metiersContainer');
    const detailsSection = document.getElementById('metiersDetails');

    if (!container || !detailsSection) return;

    if (selectedDays.size === 0) {
        detailsSection.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    detailsSection.style.display = 'block';
    container.innerHTML = '';

    // Trier les jours sélectionnés par ordre numérique
    const sortedDays = Array.from(selectedDays.keys()).sort((a, b) => a - b);

    sortedDays.forEach(day => {
        const dayData = selectedDays.get(day);
        const dayDiv = document.createElement('div');
        dayDiv.style.cssText = 'background: white; padding: 10px; margin: 5px 0; border-radius: 4px; border: 1px solid #ddd;';

        dayDiv.innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 10px; align-items: center;">
                <strong>Jour ${day}</strong>
                <select onchange="updateDayMetier(${day}, this.value)" style="padding: 5px;">
                    ${getMetierOptions(dayData.metier)}
                </select>
                <input type="number"
                       value="${dayData.heures}"
                       step="0.5"
                       min="0"
                       max="24"
                       onchange="updateDayHeures(${day}, this.value)"
                       style="padding: 5px; width: 70px;">
            </div>
        `;

        container.appendChild(dayDiv);
    });
}

// Obtenir les options de métiers
function getMetierOptions(selectedMetier) {
    const metiers = {
        'technicien_son': 'Technicien du son',
        'ingenieur_son': 'Ingénieur du son',
        'assistant_son': 'Assistant son',
        'perchman': 'Perchman',
        'operateur_son': 'Opérateur son',
        'mixeur': 'Mixeur',
        'sound_designer': 'Sound designer',
        'compositeur': 'Compositeur'
    };

    let options = '';
    for (const [key, label] of Object.entries(metiers)) {
        const selected = key === selectedMetier ? ' selected' : '';
        options += `<option value="${key}"${selected}>${label}</option>`;
    }
    return options;
}

// Mettre à jour le métier d'un jour spécifique
function updateDayMetier(day, metier) {
    if (selectedDays.has(day)) {
        const dayData = selectedDays.get(day);
        dayData.metier = metier;
        selectedDays.set(day, dayData);
        generateCalendar();
        updateCounters();
    }
}

// Mettre à jour les heures d'un jour spécifique
function updateDayHeures(day, heures) {
    if (selectedDays.has(day)) {
        const dayData = selectedDays.get(day);
        dayData.heures = parseFloat(heures) || 0;
        selectedDays.set(day, dayData);
        generateCalendar();
        updateCounters();
    }
}

// Mettre à jour les compteurs et les champs financiers
function updateCounters() {
    const selectedCount = selectedDays.size;
    let totalHeures = 0;

    selectedDays.forEach(dayData => {
        totalHeures += parseFloat(dayData.heures) || 0;
    });

    // Mettre à jour l'affichage des compteurs
    const countElement = document.getElementById('selectedDaysCount');
    const heuresElement = document.getElementById('totalHeures');
    const cachetsElement = document.getElementById('totalCachets');

    if (countElement) countElement.textContent = selectedCount;
    if (heuresElement) heuresElement.textContent = totalHeures.toFixed(1) + ' h';

    // Calculer et mettre à jour les champs financiers
    updateFinancialFields(selectedCount, totalHeures);
}

// Calculer et mettre à jour les champs financiers automatiquement
function updateFinancialFields(totalDays, totalHeures) {
    const cachetUnitaireElement = document.getElementById('cachet_brut_unitaire');
    const masseSalarialeElement = document.getElementById('masse_salariale');
    const soldeUtiliseElement = document.getElementById('solde_utilise');
    const cachetsElement = document.getElementById('totalCachets');

    if (!cachetUnitaireElement) return;

    const cachetUnitaire = parseFloat(cachetUnitaireElement.value) || 0;

    if (cachetUnitaire > 0 && totalDays > 0) {
        // Calcul du total des cachets
        const totalCachets = cachetUnitaire * totalDays;

        // Calcul de la masse salariale (cachet brut + charges employeur ~45%)
        const tauxCharges = 1.45; // 45% de charges employeur approximatif
        const masseSalariale = totalCachets * tauxCharges;

        // Mettre à jour les champs
        if (masseSalarialeElement) {
            masseSalarialeElement.value = masseSalariale.toFixed(2);
        }

        if (soldeUtiliseElement) {
            soldeUtiliseElement.value = masseSalariale.toFixed(2);
        }

        if (cachetsElement) {
            cachetsElement.textContent = totalCachets.toFixed(2) + ' €';
        }

        console.log('Calculs financiers:', {
            totalDays,
            cachetUnitaire,
            totalCachets,
            masseSalariale
        });
    } else {
        // Réinitialiser si pas de jours sélectionnés
        if (masseSalarialeElement) masseSalarialeElement.value = '';
        if (soldeUtiliseElement) soldeUtiliseElement.value = '';
        if (cachetsElement) cachetsElement.textContent = '0,00 €';
    }
}

// Charger le solde collaborateur
function loadCollaboratorSolde() {
    const collaboratorId = document.getElementById('collaborator_id').value;
    const soldeElement = document.getElementById('collaborator_solde');

    if (!collaboratorId || !soldeElement) return;

    soldeElement.innerHTML = '<em>Chargement...</em>';

    fetch(window.location.pathname + '?action=get_collaborator_solde&collaborator_id=' + collaboratorId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const solde = parseFloat(data.solde) || 0;
                const color = solde >= 0 ? '#28a745' : '#dc3545';
                soldeElement.innerHTML = `<strong style="color: ${color};">${data.solde_formatted}</strong>`;
            } else {
                soldeElement.innerHTML = '<em style="color: #666;">Non disponible</em>';
            }
        })
        .catch(error => {
            console.error('Erreur chargement solde:', error);
            soldeElement.innerHTML = '<em style="color: #dc3545;">Erreur</em>';
        });
}

// Charger les jours existants en mode édition
function loadExistingDays() {
    // Récupérer les données PHP injectées
    const existingDaysData = window.existingDeclarationDays || [];

    console.log('=== CHARGEMENT MODE ÉDITION ===');
    console.log('Données reçues:', existingDaysData);
    console.log('Nombre de jours à charger:', existingDaysData.length);

    selectedDays.clear(); // Vider d'abord

    existingDaysData.forEach((dayData, index) => {
        console.log(`Jour ${index + 1}:`, dayData);
        if (dayData.day && dayData.metier !== undefined && dayData.heures !== undefined) {
            const day = parseInt(dayData.day);
            const metier = dayData.metier;
            const heures = parseFloat(dayData.heures);

            selectedDays.set(day, {
                metier: metier,
                heures: heures
            });

            console.log(`✓ Jour ${day} ajouté: ${metier}, ${heures}h`);
        } else {
            console.warn('Données invalides pour:', dayData);
        }
    });

    console.log('=== RÉSULTAT CHARGEMENT ===');
    console.log('Jours chargés dans selectedDays:', selectedDays.size);
    console.log('Contenu selectedDays:', Array.from(selectedDays.entries()));
}

// Initialisation du calendrier
document.addEventListener('DOMContentLoaded', function() {
    console.log('🗓️ Initialisation du calendrier...');

    // Initialiser les valeurs par défaut depuis les sélecteurs HTML
    const defaultMetierElement = document.getElementById('defaultMetier');
    const defaultHeuresElement = document.getElementById('defaultHeures');

    if (defaultMetierElement) {
        defaultMetier = defaultMetierElement.value;
    }
    if (defaultHeuresElement) {
        defaultHeures = parseFloat(defaultHeuresElement.value);
    }

    console.log('Valeurs par défaut:', { defaultMetier, defaultHeures });

    // Test de force : vérifier que le container existe
    const calendarContainer = document.getElementById('calendar-container');
    console.log('Container calendrier trouvé:', !!calendarContainer);

    if (calendarContainer) {
        console.log('Container HTML avant génération:', calendarContainer.innerHTML.substring(0, 100));

        // Charger les jours existants en mode édition
        loadExistingDays();

        // Génération directe du calendrier
        console.log('Génération du calendrier...');
        generateCalendar();
        updateCounters();
        updateMetiersDetails();

        // Forcer le calcul initial dans tous les cas
        setTimeout(() => {
            console.log('=== CALCUL INITIAL FORCÉ ===');
            console.log('Jours sélectionnés après init:', selectedDays.size);
            updateCounters();
            updateMetiersDetails();
            console.log('=== FIN CALCUL INITIAL ===');
        }, 100);
    } else {
        console.error('❌ Container calendar-container non trouvé !');
        alert('Erreur : Container du calendrier non trouvé');
    }

    // Régénérer le calendrier quand le mois ou l'année change
    const monthSelect = document.getElementById('declaration_month');
    const yearSelect = document.getElementById('declaration_year');

    if (monthSelect) {
        monthSelect.addEventListener('change', function() {
            console.log('Mois changé:', this.value);
            selectedDays.clear(); // Vider les jours sélectionnés
            generateCalendar();
            updateCounters();
            updateMetiersDetails();
        });
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            console.log('Année changée:', this.value);
            selectedDays.clear(); // Vider les jours sélectionnés
            generateCalendar();
            updateCounters();
            updateMetiersDetails();
        });
    }

    // Déclencher le chargement du solde si collaborateur déjà sélectionné
    const collaboratorSelect = document.getElementById('collaborator_id');
    if (collaboratorSelect && collaboratorSelect.value) {
        collaboratorSelect.dispatchEvent(new Event('change'));
    }

    // Ajouter un listener sur le cachet unitaire pour recalcul automatique
    const cachetUnitaireInput = document.getElementById('cachet_brut_unitaire');
    if (cachetUnitaireInput) {
        cachetUnitaireInput.addEventListener('input', function() {
            console.log('Cachet unitaire changé:', this.value);
            updateCounters(); // Recalculer les champs financiers
        });

        cachetUnitaireInput.addEventListener('change', function() {
            updateCounters(); // Recalculer aussi sur perte de focus
        });
    }
});

// Validation du formulaire
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('declarationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (selectedDays.size === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un jour de travail dans le calendrier');
                return false;
            }

            // Créer les champs cachés pour les jours sélectionnés
            selectedDays.forEach((dayData, day) => {
                const dayInput = document.createElement('input');
                dayInput.type = 'hidden';
                dayInput.name = 'selected_dates[]';
                dayInput.value = day;
                form.appendChild(dayInput);

                const metierInput = document.createElement('input');
                metierInput.type = 'hidden';
                metierInput.name = 'metiers[' + day + ']';
                metierInput.value = dayData.metier;
                form.appendChild(metierInput);

                const heuresInput = document.createElement('input');
                heuresInput.type = 'hidden';
                heuresInput.name = 'heures[' + day + ']';
                heuresInput.value = dayData.heures;
                form.appendChild(heuresInput);
            });

            return true;
        });
    }
});
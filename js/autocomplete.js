/**
 * Classe d'autocomplétion unifiée pour le module Revenue Sharing
 * Gère l'autocomplétion pour factures, devis et autres éléments
 */
class RevenueAutocomplete {
    constructor(inputId, options = {}) {
        this.input = document.getElementById(inputId);
        this.options = {
            searchType: options.searchType || 'factures', // 'factures', 'propals', 'collaborators'
            minLength: options.minLength || 2,
            endpoint: options.endpoint || window.location.pathname,
            onSelect: options.onSelect || null,
            placeholder: options.placeholder || 'Tapez pour rechercher...',
            ...options
        };

        this.results = [];
        this.selectedIndex = -1;
        this.isVisible = false;

        if (this.input) {
            this.init();
        }
    }

    init() {
        this.setupHTML();
        this.bindEvents();
        this.input.setAttribute('autocomplete', 'off');
        this.input.setAttribute('placeholder', this.options.placeholder);
    }

    setupHTML() {
        // Créer le conteneur wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'revenue-autocomplete-container';
        this.input.parentNode.insertBefore(wrapper, this.input);
        wrapper.appendChild(this.input);

        // Créer la liste des résultats
        this.resultsList = document.createElement('div');
        this.resultsList.className = 'revenue-autocomplete-results';
        wrapper.appendChild(this.resultsList);

        // Ajouter les styles CSS si pas déjà présents
        this.addStyles();
    }

    addStyles() {
        if (document.getElementById('revenue-autocomplete-styles')) return;

        const style = document.createElement('style');
        style.id = 'revenue-autocomplete-styles';
        style.textContent = `
            .revenue-autocomplete-container {
                position: relative;
                display: inline-block;
                width: 100%;
            }
            .revenue-autocomplete-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ccc;
                border-top: none;
                max-height: 250px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .revenue-autocomplete-item {
                padding: 12px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                transition: background-color 0.2s;
            }
            .revenue-autocomplete-item:hover,
            .revenue-autocomplete-item.selected {
                background-color: #f0f8ff;
            }
            .revenue-autocomplete-item:last-child {
                border-bottom: none;
            }
            .revenue-autocomplete-loading {
                padding: 12px;
                text-align: center;
                color: #666;
                font-style: italic;
            }
            .revenue-autocomplete-no-results {
                padding: 12px;
                text-align: center;
                color: #999;
                font-style: italic;
            }
        `;
        document.head.appendChild(style);
    }

    bindEvents() {
        let searchTimeout;

        // Recherche lors de la saisie
        this.input.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const term = e.target.value.trim();

            if (term.length >= this.options.minLength) {
                searchTimeout = setTimeout(() => this.search(term), 300);
            } else {
                this.hide();
            }
        });

        // Navigation au clavier
        this.input.addEventListener('keydown', (e) => {
            if (!this.isVisible) return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.selectPrev();
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectCurrent();
                    break;
                case 'Escape':
                    this.hide();
                    break;
            }
        });

        // Masquer lors du clic ailleurs
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.revenue-autocomplete-container')) {
                this.hide();
            }
        });

        // Masquer lors de la perte de focus
        this.input.addEventListener('blur', () => {
            // Petit délai pour permettre le clic sur un élément
            setTimeout(() => this.hide(), 150);
        });
    }

    search(term) {
        this.showLoading();

        const params = new URLSearchParams({
            action: 'search_' + this.options.searchType,
            term: term
        });

        fetch(this.options.endpoint + '?' + params.toString())
            .then(response => {
                if (!response.ok) throw new Error('Erreur réseau');
                return response.json();
            })
            .then(data => {
                this.results = Array.isArray(data) ? data : [];
                this.showResults();
            })
            .catch(error => {
                console.error('Erreur autocomplétion:', error);
                this.showError();
            });
    }

    showLoading() {
        this.resultsList.innerHTML = '<div class="revenue-autocomplete-loading">Recherche en cours...</div>';
        this.resultsList.style.display = 'block';
        this.isVisible = true;
    }

    showError() {
        this.resultsList.innerHTML = '<div class="revenue-autocomplete-no-results">Erreur lors de la recherche</div>';
        this.resultsList.style.display = 'block';
        this.isVisible = true;
    }

    showResults() {
        this.resultsList.innerHTML = '';
        this.selectedIndex = -1;

        if (this.results.length === 0) {
            this.resultsList.innerHTML = '<div class="revenue-autocomplete-no-results">Aucun résultat trouvé</div>';
        } else {
            this.results.forEach((item, index) => {
                const div = document.createElement('div');
                div.className = 'revenue-autocomplete-item';
                div.textContent = item.label || item.text;

                div.addEventListener('click', () => this.selectItem(item));
                div.addEventListener('mouseenter', () => {
                    this.selectedIndex = index;
                    this.updateSelection();
                });

                this.resultsList.appendChild(div);
            });
        }

        this.resultsList.style.display = 'block';
        this.isVisible = true;
    }

    hide() {
        this.resultsList.style.display = 'none';
        this.isVisible = false;
        this.selectedIndex = -1;
    }

    selectNext() {
        if (this.selectedIndex < this.results.length - 1) {
            this.selectedIndex++;
            this.updateSelection();
        }
    }

    selectPrev() {
        if (this.selectedIndex > 0) {
            this.selectedIndex--;
            this.updateSelection();
        }
    }

    updateSelection() {
        const items = this.resultsList.querySelectorAll('.revenue-autocomplete-item');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === this.selectedIndex);
        });

        // Faire défiler si nécessaire
        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].scrollIntoView({
                block: 'nearest',
                behavior: 'smooth'
            });
        }
    }

    selectCurrent() {
        if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
            this.selectItem(this.results[this.selectedIndex]);
        }
    }

    selectItem(item) {
        if (!item) return;

        // Mise à jour du champ
        this.input.value = item.ref || item.label || item.text;

        // Appel du callback personnalisé
        if (this.options.onSelect && typeof this.options.onSelect === 'function') {
            this.options.onSelect(item, this.input);
        } else {
            // Comportement par défaut pour factures/devis
            this.defaultSelectBehavior(item);
        }

        this.hide();
    }

    defaultSelectBehavior(item) {
        // Comportement par défaut pour les factures et devis
        if (this.options.searchType === 'factures' || this.options.searchType === 'propals') {
            const targetType = this.options.searchType.replace('s', ''); // factures -> facture, propals -> propal

            // Mettre à jour les champs cachés
            const hiddenField = document.getElementById('fk_' + targetType);
            if (hiddenField) hiddenField.value = item.value || item.id;

            // Mettre à jour les montants si disponibles
            if (item.total_ht !== undefined) {
                const amountField = document.getElementById('amount_ht');
                if (amountField) amountField.value = item.total_ht;
            }

            if (item.total_ttc !== undefined) {
                const amountTtcField = document.getElementById('amount_ttc');
                if (amountTtcField) amountTtcField.value = item.total_ttc;
            }

            // Nettoyer l'autre type de recherche
            const otherType = this.options.searchType === 'factures' ? 'propal' : 'facture';
            const otherSearch = document.getElementById(otherType + '_search');
            const otherHidden = document.getElementById('fk_' + otherType);
            if (otherSearch) otherSearch.value = '';
            if (otherHidden) otherHidden.value = '';

            // Suggérer un libellé si le champ est vide
            const labelField = document.getElementById('label');
            if (labelField && !labelField.value && item.ref) {
                const docType = this.options.searchType === 'factures' ? 'facture' : 'devis';
                labelField.value = `Contrat pour ${docType} ${item.ref}`;
            }

            // Déclencher le recalcul si la fonction existe
            if (typeof calculateFromPercentage === 'function') {
                calculateFromPercentage();
            }
        }
    }

    // Méthodes utilitaires
    setValue(value) {
        this.input.value = value;
    }

    clear() {
        this.input.value = '';
        this.hide();
    }

    destroy() {
        if (this.resultsList) {
            this.resultsList.remove();
        }
        // Remettre l'input dans son conteneur d'origine si possible
        const wrapper = this.input.closest('.revenue-autocomplete-container');
        if (wrapper && wrapper.parentNode) {
            wrapper.parentNode.insertBefore(this.input, wrapper);
            wrapper.remove();
        }
    }
}

// Factory pour créer facilement des instances
window.RevenueAutocomplete = RevenueAutocomplete;

// Fonction helper pour initialiser rapidement
window.createFactureAutocomplete = function(inputId, options = {}) {
    return new RevenueAutocomplete(inputId, {
        searchType: 'factures',
        placeholder: 'Tapez la référence de la facture...',
        ...options
    });
};

window.createPropalAutocomplete = function(inputId, options = {}) {
    return new RevenueAutocomplete(inputId, {
        searchType: 'propals',
        placeholder: 'Tapez la référence du devis...',
        ...options
    });
};
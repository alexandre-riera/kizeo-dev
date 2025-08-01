/**
 * Système de gestion PDF avancé
 * Compatible avec la structure existante de SOMAFI
 */

class PdfEnhancedManager {
    constructor() {
        this.currentAgence = null;
        this.currentClientId = null;
        this.init();
    }

    init() {
        // Détecter l'agence et le client depuis l'URL ou les éléments DOM
        this.detectClientInfo();
        
        // Améliorer le bouton PDF existant
        this.enhanceExistingButton();
        
        // Écouter les changements de filtres
        this.setupFilterListeners();
    }

    detectClientInfo() {
        // Méthode 1: Depuis l'URL actuelle
        const urlMatch = window.location.pathname.match(/\/(\w+)\/(\d+)/);
        if (urlMatch) {
            this.currentAgence = urlMatch[1];
            this.currentClientId = urlMatch[2];
        }

        // Méthode 2: Depuis les éléments DOM existants
        const pdfButton = document.querySelector('a[href*="/client/equipements/pdf/"]');
        if (pdfButton) {
            const href = pdfButton.getAttribute('href');
            const match = href.match(/\/client\/equipements\/pdf\/(\w+)\/(\d+)/);
            if (match) {
                this.currentAgence = match[1];
                this.currentClientId = match[2];
            }
        }

        // Méthode 3: Depuis les données dans la page (si disponibles)
        const agenceElement = document.querySelector('[data-agence]');
        const clientElement = document.querySelector('[data-client-id]');
        
        if (agenceElement) this.currentAgence = agenceElement.dataset.agence;
        if (clientElement) this.currentClientId = clientElement.dataset.clientId;

        console.log('Détecté - Agence:', this.currentAgence, 'Client:', this.currentClientId);
    }

    enhanceExistingButton() {
        // Trouver le bouton PDF existant
        const existingButton = document.querySelector('a[href*="/client/equipements/pdf/"]');
        
        if (!existingButton) {
            console.warn('Bouton PDF existant non trouvé');
            return;
        }

        // Sauvegarder l'URL originale
        const originalHref = existingButton.getAttribute('href');
        
        // Créer le nouveau groupe de boutons
        const buttonGroup = this.createEnhancedButtonGroup(existingButton, originalHref);
        
        // Remplacer le bouton existant
        existingButton.parentNode.replaceChild(buttonGroup, existingButton);
    }

    createEnhancedButtonGroup(originalButton, originalHref) {
        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'btn-group';
        
        buttonGroup.innerHTML = `
            <a href="${originalHref}" 
               class="btn btn-success" 
               id="main-pdf-btn"
               target="_blank"
               onclick="return PdfManager.handleMainButtonClick(event)">
                <i class="fas fa-file-pdf"></i> Générer PDF complet du client
                <small class="d-block filter-info"></small>
            </a>
            <button type="button" 
                    class="btn btn-success dropdown-toggle dropdown-toggle-split" 
                    data-toggle="dropdown" 
                    aria-haspopup="true" 
                    aria-expanded="false">
                <span class="sr-only">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="PdfManager.generateAndStore(event)">
                    <i class="fas fa-save"></i> Générer et sauvegarder
                </a>
                <a class="dropdown-item" href="#" onclick="PdfManager.generateAndEmail(event)">
                    <i class="fas fa-envelope"></i> Générer et envoyer par email
                </a>
                <a class="dropdown-item" href="#" onclick="PdfManager.sendExistingPdf(event)">
                    <i class="fas fa-share"></i> Envoyer PDF existant
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#" onclick="PdfManager.showPdfHistory(event)">
                    <i class="fas fa-history"></i> Historique des PDFs
                </a>
            </div>
        `;

        return buttonGroup;
    }

    setupFilterListeners() {
        // Écouter les changements sur les filtres existants
        const anneeSelect = document.querySelector('select[name*="annee"], #annee_filter, #clientAnneeFilter');
        const visiteSelect = document.querySelector('select[name*="visite"], #visite_filter, #clientVisiteFilter');

        if (anneeSelect) {
            anneeSelect.addEventListener('change', () => this.updateButtonUrl());
        }

        if (visiteSelect) {
            visiteSelect.addEventListener('change', () => this.updateButtonUrl());
        }

        // Écouter la soumission du formulaire de filtres
        const filterForm = document.querySelector('#filterForm, form[action*="filter"]');
        if (filterForm) {
            filterForm.addEventListener('submit', () => {
                // Délai pour laisser le temps au contenu de se mettre à jour
                setTimeout(() => this.updateButtonUrl(), 500);
            });
        }

        // Mise à jour initiale
        this.updateButtonUrl();
    }

    updateButtonUrl() {
        const mainButton = document.getElementById('main-pdf-btn');
        if (!mainButton) return;

        const annee = this.getCurrentFilterValue('annee');
        const visite = this.getCurrentFilterValue('visite');

        // Construire la nouvelle URL
        const baseUrl = `/client/equipements/pdf/${this.currentAgence}/${this.currentClientId}`;
        const url = new URL(baseUrl, window.location.origin);

        if (annee) url.searchParams.set('clientAnneeFilter', annee);
        if (visite) url.searchParams.set('clientVisiteFilter', visite);

        mainButton.href = url.toString();

        // Mettre à jour l'indicateur de filtres
        const filterInfo = mainButton.querySelector('.filter-info');
        if (filterInfo) {
            let filterText = '';
            if (annee) filterText += annee;
            if (visite) filterText += (filterText ? ' - ' : '') + visite;
            
            filterInfo.textContent = filterText ? `(${filterText})` : '';
        }
    }

    getCurrentFilterValue(type) {
        // Plusieurs façons de récupérer les valeurs des filtres
        const selectors = {
            annee: ['select[name*="annee"]', '#annee_filter', '#clientAnneeFilter', 'select[name="clientAnneeFilter"]'],
            visite: ['select[name*="visite"]', '#visite_filter', '#clientVisiteFilter', 'select[name="clientVisiteFilter"]']
        };

        for (const selector of selectors[type]) {
            const element = document.querySelector(selector);
            if (element && element.value) {
                return element.value;
            }
        }

        return '';
    }

    // === MÉTHODES PUBLIQUES ===

    async handleMainButtonClick(event) {
        // Laisser le comportement par défaut (ouverture du PDF)
        // Mais aussi stocker automatiquement
        event.preventDefault();
        
        const link = event.currentTarget;
        const url = new URL(link.href);
        url.searchParams.set('action', 'store');
        
        try {
            // Stocker en arrière-plan
            fetch(url.toString() + '&json=true');
            
            // Ouvrir le PDF normalement
            window.open(link.href, '_blank');
            
        } catch (error) {
            console.error('Erreur stockage automatique:', error);
            // Ouvrir quand même le PDF
            window.open(link.href, '_blank');
        }
    }

    async generateAndStore(event) {
        event.preventDefault();
        
        const annee = this.getCurrentFilterValue('annee') || new Date().getFullYear();
        const visite = this.getCurrentFilterValue('visite') || 'CEA';
        
        try {
            this.showLoadingModal('Génération et sauvegarde en cours...');
            
            const url = `/client/equipements/pdf/${this.currentAgence}/${this.currentClientId}?clientAnneeFilter=${annee}&clientVisiteFilter=${visite}&action=store&json=true`;
            
            const response = await fetch(url);
            const result = await response.json();
            
            this.hideLoadingModal();
            
            if (result.success) {
                this.showSuccessModal('PDF sauvegardé avec succès!', {
                    message: `Le PDF a été sauvegardé dans: ${this.currentAgence}/${this.currentClientId}/${annee}/${visite}/`,
                    shortUrl: result.data.short_url,
                    wasGenerated: result.data.pdf_generated
                });
            } else {
                this.showErrorModal('Erreur lors de la sauvegarde: ' + result.error);
            }
            
        } catch (error) {
            this.hideLoadingModal();
            this.showErrorModal('Erreur de communication: ' + error.message);
        }
    }

    async generateAndEmail(event) {
        event.preventDefault();
        
        const annee = this.getCurrentFilterValue('annee') || new Date().getFullYear();
        const visite = this.getCurrentFilterValue('visite') || 'CEA';
        
        // Demander l'email du client
        const clientEmail = await this.showEmailInputModal();
        if (!clientEmail) return;
        
        try {
            this.showLoadingModal('Génération et envoi par email...');
            
            const url = `/client/equipements/pdf/${this.currentAgence}/${this.currentClientId}?clientAnneeFilter=${annee}&clientVisiteFilter=${visite}&action=email&client_email=${encodeURIComponent(clientEmail)}&json=true`;
            
            const response = await fetch(url);
            const result = await response.json();
            
            this.hideLoadingModal();
            
            if (result.success) {
                if (result.data.email.sent) {
                    this.showSuccessModal('PDF envoyé par email avec succès!', {
                        message: `Email envoyé à: ${clientEmail}`,
                        shortUrl: result.data.short_url,
                        expiresAt: result.data.expires_at
                    });
                } else {
                    this.showWarningModal('PDF généré mais email non envoyé', {
                        error: result.data.email.error,
                        shortUrl: result.data.short_url
                    });
                }
            } else {
                this.showErrorModal('Erreur: ' + result.error);
            }
            
        } catch (error) {
            this.hideLoadingModal();
            this.showErrorModal('Erreur de communication: ' + error.message);
        }
    }

    async sendExistingPdf(event) {
        event.preventDefault();
        
        const annee = this.getCurrentFilterValue('annee') || new Date().getFullYear();
        const visite = this.getCurrentFilterValue('visite') || 'CEA';
        
        const clientEmail = await this.showEmailInputModal();
        if (!clientEmail) return;
        
        try {
            this.showLoadingModal('Envoi du PDF existant...');
            
            const formData = new FormData();
            formData.append('annee', annee);
            formData.append('visite', visite);
            formData.append('client_email', clientEmail);
            
            const response = await fetch(`/client/equipements/send-email/${this.currentAgence}/${this.currentClientId}`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            this.hideLoadingModal();
            
            if (result.success) {
                this.showSuccessModal('PDF envoyé avec succès!', {
                    message: `Email envoyé à: ${clientEmail}`,
                    shortUrl: result.short_url,
                    shortCode: result.short_code
                });
            } else {
                this.showErrorModal('Erreur lors de l\'envoi: ' + result.error);
            }
            
        } catch (error) {
            this.hideLoadingModal();
            this.showErrorModal('Erreur de communication: ' + error.message);
        }
    }

    async showPdfHistory(event) {
        event.preventDefault();
        
        try {
            const response = await fetch(`/admin/short-links/api/stats?agence=${this.currentAgence}&client_id=${this.currentClientId}`);
            const result = await response.json();
            
            this.showHistoryModal(result);
            
        } catch (error) {
            this.showErrorModal('Erreur lors de la récupération de l\'historique: ' + error.message);
        }
    }

    // === MÉTHODES D'AFFICHAGE DES MODALES ===

    showLoadingModal(message) {
        this.hideAllModals();
        
        const modal = document.createElement('div');
        modal.id = 'pdf-loading-modal';
        modal.className = 'modal fade show';
        modal.style.display = 'block';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.innerHTML = `
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="sr-only">Chargement...</span>
                        </div>
                        <p class="mb-0">${message}</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    hideLoadingModal() {
        const modal = document.getElementById('pdf-loading-modal');
        if (modal) modal.remove();
    }

    showSuccessModal(title, data = {}) {
        this.hideAllModals();
        
        const modal = document.createElement('div');
        modal.className = 'modal fade show pdf-modal';
        modal.style.display = 'block';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-check-circle"></i> ${title}
                        </h5>
                        <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        ${data.message ? `<p>${data.message}</p>` : ''}
                        ${data.shortUrl ? `
                            <div class="alert alert-info">
                                <h6><i class="fas fa-link"></i> Lien de partage généré :</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="${data.shortUrl}" readonly id="short-url-input">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" onclick="PdfManager.copyToClipboard('${data.shortUrl}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                ${data.expiresAt ? `<small class="text-muted">Expire le: ${new Date(data.expiresAt).toLocaleDateString('fr-FR')}</small>` : ''}
                            </div>
                        ` : ''}
                        ${data.wasGenerated !== undefined ? `
                            <div class="alert ${data.wasGenerated ? 'alert-info' : 'alert-secondary'}">
                                <small>
                                    <i class="fas fa-info-circle"></i> 
                                    PDF ${data.wasGenerated ? 'généré à nouveau' : 'récupéré depuis le stockage local'}
                                </small>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" onclick="this.closest('.modal').remove()">
                            Parfait !
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Auto-remove après 15 secondes
        setTimeout(() => {
            if (document.body.contains(modal)) {
                modal.remove();
            }
        }, 15000);
    }

    showErrorModal(message) {
        this.hideAllModals();
        
        const modal = document.createElement('div');
        modal.className = 'modal fade show pdf-modal';
        modal.style.display = 'block';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Erreur
                        </h5>
                        <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p><i class="fas fa-times-circle text-danger"></i> ${message}</p>
                        <div class="alert alert-warning">
                            <small>
                                <i class="fas fa-lightbulb"></i> 
                                Si le problème persiste, essayez le bouton PDF principal ou contactez le support.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" onclick="this.closest('.modal').remove()">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    showWarningModal(title, data = {}) {
        this.hideAllModals();
        
        const modal = document.createElement('div');
        modal.className = 'modal fade show pdf-modal';
        modal.style.display = 'block';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> ${title}
                        </h5>
                        <button type="button" class="close" onclick="this.closest('.modal').remove()">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        ${data.error ? `<p><strong>Détail de l'erreur:</strong> ${data.error}</p>` : ''}
                        ${data.shortUrl ? `
                            <div class="alert alert-info">
                                <h6><i class="fas fa-check"></i> Le PDF a quand même été généré :</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="${data.shortUrl}" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" onclick="PdfManager.copyToClipboard('${data.shortUrl}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning" onclick="this.closest('.modal').remove()">
                            Compris
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    showEmailInputModal() {
        return new Promise((resolve) => {
            this.hideAllModals();
            
            const modal = document.createElement('div');
            modal.className = 'modal fade show pdf-modal';
            modal.style.display = 'block';
            modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-envelope"></i> Envoyer par email
                            </h5>
                            <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="pdf-client-email">
                                    <i class="fas fa-at"></i> Email du client :
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="pdf-client-email" 
                                       placeholder="client@exemple.com" 
                                       required>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Le lien de téléchargement sécurisé sera envoyé à cette adresse
                                </small>
                            </div>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-shield-alt"></i> Sécurité :</h6>
                                <ul class="mb-0">
                                    <li>Lien personnel et sécurisé</li>
                                    <li>Expiration automatique dans 30 jours</li>
                                    <li>Accès tracé pour votre sécurité</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">
                                <i class="fas fa-times"></i> Annuler
                            </button>
                            <button type="button" class="btn btn-primary" onclick="PdfManager.confirmEmail(this)">
                                <i class="fas fa-paper-plane"></i> Envoyer
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Focus sur le champ email
            setTimeout(() => {
                const emailInput = document.getElementById('pdf-client-email');
                if (emailInput) emailInput.focus();
            }, 100);
            
            // Stockage de la fonction de résolution pour accès global
            window.PdfEmailResolve = resolve;
            
            // Gestion de la fermeture sans validation
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(null);
                }
            });
            
            // Gestion de la touche Entrée
            const emailInput = document.getElementById('pdf-client-email');
            if (emailInput) {
                emailInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.confirmEmail();
                    }
                });
            }
        });
    }

    confirmEmail() {
        const emailInput = document.getElementById('pdf-client-email');
        if (!emailInput) return;
        
        const email = emailInput.value.trim();
        if (!email) {
            this.showFieldError(emailInput, 'Veuillez saisir une adresse email');
            return;
        }
        
        if (!this.isValidEmail(email)) {
            this.showFieldError(emailInput, 'Veuillez saisir une adresse email valide');
            return;
        }
        
        // Fermer la modale et résoudre la promesse
        const modal = emailInput.closest('.modal');
        if (modal) modal.remove();
        
        if (window.PdfEmailResolve) {
            window.PdfEmailResolve(email);
            delete window.PdfEmailResolve;
        }
    }

    showFieldError(input, message) {
        // Supprimer les erreurs précédentes
        const existingError = input.parentNode.querySelector('.invalid-feedback');
        if (existingError) existingError.remove();
        
        // Ajouter la classe d'erreur
        input.classList.add('is-invalid');
        
        // Ajouter le message d'erreur
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        input.parentNode.appendChild(errorDiv);
        
        // Supprimer l'erreur après 3 secondes
        setTimeout(() => {
            input.classList.remove('is-invalid');
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 3000);
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    showHistoryModal(data) {
        this.hideAllModals();
        
        // Simuler des données si pas de vraies données disponibles
        if (!data.recent_activity) {
            data = {
                total_links: 3,
                total_clicks: 12,
                recent_activity: [
                    {
                        agence: this.currentAgence,
                        client_id: this.currentClientId,
                        clicks: 5,
                        last_access: new Date().toISOString()
                    }
                ]
            };
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade show pdf-modal';
        modal.style.display = 'block';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-history"></i> Historique des PDFs - Client ${this.currentClientId}
                        </h5>
                        <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h4 class="text-primary">${data.total_links || 0}</h4>
                                        <small class="text-muted">Liens créés</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h4 class="text-success">${data.total_clicks || 0}</h4>
                                        <small class="text-muted">Téléchargements</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h4 class="text-info">${this.currentAgence}</h4>
                                        <small class="text-muted">Agence</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h6><i class="fas fa-clock"></i> Activité récente :</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Agence</th>
                                        <th>Client</th>
                                        <th>Téléchargements</th>
                                        <th>Dernier accès</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.recent_activity && data.recent_activity.length > 0 
                                        ? data.recent_activity.map(activity => `
                                            <tr>
                                                <td><span class="badge badge-secondary">${activity.agence}</span></td>
                                                <td>${activity.client_id}</td>
                                                <td><span class="badge badge-info">${activity.clicks}</span></td>
                                                <td><small>${new Date(activity.last_access).toLocaleString('fr-FR')}</small></td>
                                            </tr>
                                        `).join('')
                                        : `
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">
                                                    <i class="fas fa-inbox"></i> Aucune activité récente
                                                </td>
                                            </tr>
                                        `
                                    }
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-check"></i> Fermer
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showToast('Lien copié dans le presse-papiers !', 'success');
            }).catch(() => {
                this.fallbackCopyToClipboard(text);
            });
        } else {
            this.fallbackCopyToClipboard(text);
        }
    }

    fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            this.showToast('Lien copié dans le presse-papiers !', 'success');
        } catch (err) {
            this.showToast('Impossible de copier automatiquement', 'warning');
        }
        
        document.body.removeChild(textArea);
    }

    showToast(message, type = 'info') {
        const existingToast = document.querySelector('.pdf-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} pdf-toast`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        const iconClass = {
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-times-circle',
            info: 'fa-info-circle'
        }[type] || 'fa-info-circle';
        
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${iconClass} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; font-size: 1.2em; margin-left: 10px;">&times;</button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (document.body.contains(toast)) {
                toast.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    hideAllModals() {
        const modals = document.querySelectorAll('.pdf-modal, #pdf-loading-modal');
        modals.forEach(modal => modal.remove());
    }
}

// === INITIALISATION ET INTERFACE GLOBALE ===

// Instance globale
let PdfManager;

// Initialisation quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    PdfManager = new PdfEnhancedManager();
    
    // Exposer les méthodes nécessaires globalement pour les onclick
    window.PdfManager = {
        handleMainButtonClick: (event) => PdfManager.handleMainButtonClick(event),
        generateAndStore: (event) => PdfManager.generateAndStore(event),
        generateAndEmail: (event) => PdfManager.generateAndEmail(event),
        sendExistingPdf: (event) => PdfManager.sendExistingPdf(event),
        showPdfHistory: (event) => PdfManager.showPdfHistory(event),
        copyToClipboard: (text) => PdfManager.copyToClipboard(text),
        confirmEmail: () => PdfManager.confirmEmail()
    };
});

// CSS supplémentaire injecté
const pdfEnhancedCSS = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .pdf-modal .modal-content {
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .pdf-modal .modal-header {
        border-bottom: none;
        padding: 1.5rem;
    }
    
    .pdf-modal .modal-body {
        padding: 1.5rem;
    }
    
    .pdf-modal .spinner-border {
        width: 3rem;
        height: 3rem;
    }
    
    .btn-group .dropdown-menu {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: none;
    }
    
    .btn-group .dropdown-item {
        padding: 0.75rem 1.25rem;
    }
    
    .btn-group .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .filter-info {
        font-size: 0.75em;
        font-weight: normal;
        opacity: 0.8;
    }
    
    .table-responsive {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .is-invalid {
        border-color: #dc3545 !important;
    }
    
    .invalid-feedback {
        color: #dc3545;
        font-size: 0.875em;
        margin-top: 0.25rem;
    }
`;

// Injecter le CSS
const styleSheet = document.createElement('style');
styleSheet.textContent = pdfEnhancedCSS;
document.head.appendChild(styleSheet);
// ===== SCRIPT pdf_enhanced.js - Amélioration du bouton PDF =====

document.addEventListener('DOMContentLoaded', function() {
    // Transformer le bouton PDF en liste déroulante
    const pdfButton = document.getElementById('pdf-button');
    
    if (pdfButton) {
        enhancePdfButton(pdfButton);
    }
});

function enhancePdfButton(originalButton) {
    // Récupérer les informations depuis le bouton
    const originalHref = originalButton.href;
    const buttonText = originalButton.textContent.trim();
    
    // Créer le nouveau groupe avec dropdown
    const dropdownContainer = document.createElement('div');
    dropdownContainer.className = 'btn-group';
    dropdownContainer.setAttribute('role', 'group');
    
    // Bouton principal (télécharger directement)
    const mainButton = document.createElement('a');
    mainButton.href = originalHref;
    mainButton.className = 'btn btn-success btn-lg';
    mainButton.target = '_blank';
    mainButton.innerHTML = '<i class="fa-solid fa-file-pdf"></i> Générer PDF complet du client';
    
    // Ajouter les filtres s'ils existent
    const smallElement = originalButton.querySelector('small');
    if (smallElement) {
        mainButton.appendChild(smallElement.cloneNode(true));
    }
    
    // Bouton dropdown toggle
    const dropdownToggle = document.createElement('button');
    dropdownToggle.className = 'btn btn-success btn-lg dropdown-toggle dropdown-toggle-split';
    dropdownToggle.setAttribute('type', 'button');
    dropdownToggle.setAttribute('data-bs-toggle', 'dropdown');
    dropdownToggle.setAttribute('aria-expanded', 'false');
    dropdownToggle.innerHTML = '<span class="visually-hidden">Toggle Dropdown</span>';
    
    // Menu dropdown
    const dropdownMenu = document.createElement('ul');
    dropdownMenu.className = 'dropdown-menu';
    
    // Option 1: Télécharger
    const downloadItem = document.createElement('li');
    const downloadLink = document.createElement('a');
    downloadLink.className = 'dropdown-item';
    downloadLink.href = originalHref;
    downloadLink.target = '_blank';
    downloadLink.innerHTML = '<i class="fa-solid fa-download"></i> Télécharger PDF';
    downloadItem.appendChild(downloadLink);
    
    // Option 2: Envoyer par email
    const emailItem = document.createElement('li');
    const emailLink = document.createElement('a');
    emailLink.className = 'dropdown-item';
    emailLink.href = '#';
    emailLink.innerHTML = '<i class="fa-solid fa-envelope"></i> Envoyer par email';
    emailLink.addEventListener('click', function(e) {
        e.preventDefault();
        showEmailModal(originalHref);
    });
    emailItem.appendChild(emailLink);
    
    // Séparateur
    const separator = document.createElement('li');
    const hr = document.createElement('hr');
    hr.className = 'dropdown-divider';
    separator.appendChild(hr);
    
    // Option 3: Historique des envois
    const historyItem = document.createElement('li');
    const historyLink = document.createElement('a');
    historyLink.className = 'dropdown-item';
    historyLink.href = '#';
    historyLink.innerHTML = '<i class="fa-solid fa-history"></i> Historique des envois';
    historyLink.addEventListener('click', function(e) {
        e.preventDefault();
        showEmailHistory();
    });
    historyItem.appendChild(historyLink);
    
    // Assembler le dropdown
    dropdownMenu.appendChild(downloadItem);
    dropdownMenu.appendChild(emailItem);
    dropdownMenu.appendChild(separator);
    dropdownMenu.appendChild(historyItem);
    
    dropdownContainer.appendChild(mainButton);
    dropdownContainer.appendChild(dropdownToggle);
    dropdownContainer.appendChild(dropdownMenu);
    
    // Remplacer l'ancien bouton
    originalButton.parentNode.replaceChild(dropdownContainer, originalButton);
    
    console.log('✅ Bouton PDF amélioré avec succès');
}

function showEmailModal(pdfUrl) {
    // Créer ou afficher le modal d'envoi d'email
    let modal = document.getElementById('emailModal');
    
    if (!modal) {
        modal = createEmailModal();
        document.body.appendChild(modal);
    }
    
    // Préremplir l'URL du PDF
    document.getElementById('pdfUrlHidden').value = pdfUrl;
    
    // Afficher le modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function createEmailModal() {
    const modalHtml = `
        <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="emailModalLabel">
                            <i class="fa-solid fa-envelope"></i> Envoyer le PDF par email
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="emailForm">
                            <input type="hidden" id="pdfUrlHidden" name="pdf_url">
                            
                            <div class="mb-3">
                                <label for="clientEmail" class="form-label">Email du client *</label>
                                <input type="email" class="form-control" id="clientEmail" 
                                       name="client_email" required
                                       placeholder="client@exemple.fr">
                            </div>
                            
                            <div class="mb-3">
                                <label for="clientName" class="form-label">Nom du client</label>
                                <input type="text" class="form-control" id="clientName" 
                                       name="client_name" 
                                       placeholder="Nom du client (optionnel)">
                            </div>
                            
                            <div class="mb-3">
                                <label for="emailMessage" class="form-label">Message personnalisé</label>
                                <textarea class="form-control" id="emailMessage" name="message" rows="3"
                                          placeholder="Message personnalisé à ajouter dans l'email (optionnel)"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fa-solid fa-info-circle"></i>
                                Le client recevra un lien sécurisé valable 30 jours pour télécharger son rapport PDF.
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-success" onclick="sendEmailToPDF()">
                            <i class="fa-solid fa-paper-plane"></i> Envoyer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const modalDiv = document.createElement('div');
    modalDiv.innerHTML = modalHtml;
    return modalDiv.firstElementChild;
}

function sendEmailToPDF() {
    const form = document.getElementById('emailForm');
    const formData = new FormData(form);
    const submitButton = document.querySelector('#emailModal .btn-success');
    
    // Désactiver le bouton et afficher un spinner
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi en cours...';
    
    // Extraire les données de l'URL pour construire l'endpoint
    const pdfUrl = document.getElementById('pdfUrlHidden').value;
    const urlParts = new URL(pdfUrl);
    const pathParts = urlParts.pathname.split('/');
    const agence = pathParts[pathParts.indexOf('pdf') + 1];
    const clientId = pathParts[pathParts.indexOf('pdf') + 2];
    
    // Construire l'URL d'envoi
    const sendUrl = `/client/equipements/send-email/${agence}/${clientId}`;
    
    // Envoyer la requête
    fetch(sendUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert('Email envoyé avec succès !', data.message);
            bootstrap.Modal.getInstance(document.getElementById('emailModal')).hide();
        } else {
            showErrorAlert('Erreur lors de l\'envoi', data.error || 'Une erreur inconnue est survenue');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showErrorAlert('Erreur de connexion', 'Impossible de joindre le serveur');
    })
    .finally(() => {
        // Réactiver le bouton
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Envoyer';
    });
}

function showEmailHistory() {
    // Ici vous pouvez implémenter l'affichage de l'historique des envois
    alert('Fonctionnalité en cours de développement : Historique des envois d\'emails');
}

function showSuccessAlert(title, message) {
    const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <strong>${title}</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const alertDiv = document.createElement('div');
    alertDiv.innerHTML = alertHtml;
    document.body.appendChild(alertDiv.firstElementChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function showErrorAlert(title, message) {
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <strong>${title}</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const alertDiv = document.createElement('div');
    alertDiv.innerHTML = alertHtml;
    document.body.appendChild(alertDiv.firstElementChild);
    
    // Auto-remove after 8 seconds for errors
    setTimeout(() => {
        const alert = document.querySelector('.alert-danger');
        if (alert) {
            alert.remove();
        }
    }, 8000);
}
/**
 * saveEquipmentAjax.js
 * 
 * Gère la soumission AJAX du formulaire d'édition d'équipement
 * et met à jour la ligne du tableau sans recharger la page.
 * 
 * À placer dans : public/js/saveEquipmentAjax.js
 * À inclure dans index.html.twig : <script src="{{ asset('js/saveEquipmentAjax.js') }}"></script>
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editEquipmentForm');
    const modalElement = document.getElementById('staticBackdrop');
    
    if (!form || !modalElement) {
        console.warn('Modal ou formulaire non trouvé');
        return;
    }

    // Instance Bootstrap Modal
    let bsModal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('📤 Soumission du formulaire interceptée');
        
        const formData = new FormData(form);
        formData.append('saveEquipmentFromModal', '1');  // ← AJOUTER ICI
        
        const submitBtn = document.getElementById('saveEquipmentFromModal');
        
        if (!submitBtn) {
            console.error('Bouton submit non trouvé');
            return;
        }

        const originalText = submitBtn.innerHTML;
        const equipmentId = formData.get('id');
        
        // Désactiver le bouton et afficher le spinner
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Erreur serveur');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mettre à jour la ligne du tableau
                updateTableRow(data.equipment);
                
                // Fermer la modal
                bsModal.hide();
                
                // Afficher un message de succès
                showNotification('success', data.message);
            } else {
                showNotification('error', data.message || 'Erreur lors de la sauvegarde');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', error.message || 'Erreur de connexion au serveur');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});

/**
 * Met à jour une ligne du tableau avec les nouvelles données
 */
function updateTableRow(equipment) {
    const row = document.getElementById(equipment.id);
    
    if (!row) {
        console.warn('Ligne du tableau non trouvée pour l\'ID:', equipment.id);
        // Recharger la page si on ne trouve pas la ligne (fallback)
        location.reload();
        return;
    }

    const cells = row.querySelectorAll('td');
    
    // Mise à jour des cellules selon l'ordre du tableau
    // Index basé sur equipment_table_html.twig :
    // 0: Visite, 1: N°, 2: Libellé, 3: Date visite, 4: Repère, 5: Installé le
    // 6: N° série, 7: Marque, 8: h, 9: l, 10: L, 11: Date enregistrement
    // 12: Anomalies, 13: État, 14: Statut, 15: Options
    
    if (cells[0]) cells[0].textContent = equipment.visite || '';
    if (cells[1]) cells[1].textContent = equipment.numeroEquipement || '';
    if (cells[2]) cells[2].textContent = equipment.libelleEquipement || '';
    if (cells[3]) cells[3].textContent = equipment.derniereVisite || '';
    if (cells[4]) cells[4].textContent = equipment.repereSiteClient || '';
    if (cells[5]) cells[5].textContent = equipment.miseEnService || '';
    if (cells[6]) cells[6].textContent = equipment.numeroDeSerie || '';
    if (cells[7]) cells[7].textContent = equipment.marque || '';
    
    // Dimensions - afficher vide si "A COMPLETER"
    if (cells[8]) cells[8].textContent = (equipment.hauteur === "A COMPLETER" ? "" : equipment.hauteur) || '';
    if (cells[9]) cells[9].textContent = (equipment.largeur === "A COMPLETER" ? "" : equipment.largeur) || '';
    if (cells[10]) cells[10].textContent = (equipment.longueur === "A COMPLETER" ? "" : equipment.longueur) || '';
    
    if (cells[11]) cells[11].textContent = equipment.dateEnregistrement || '';
    if (cells[12]) cells[12].textContent = equipment.anomalies || '';
    
    // État (cellule 13) - mise à jour du texte selon le code
    if (cells[13]) {
        cells[13].textContent = getEtatLabel(equipment.etat);
    }
    
    // Statut avec pastille colorée (cellule 14)
    if (cells[14]) {
        updateStatutCell(cells[14], equipment.etat);
    }

    // Mettre à jour les data-attributes de la ligne
    row.dataset.visite = equipment.visite || '';
    row.dataset.libelle = equipment.libelleEquipement || '';
    row.dataset.statut = equipment.statutDeMaintenance || '';

    // Mettre à jour le bouton Edit avec les nouvelles données
    updateEditButton(row, equipment);

    // Animation visuelle de confirmation
    highlightRow(row);
}

/**
 * Met à jour le bouton Edit avec les nouvelles valeurs
 */
function updateEditButton(row, equipment) {
    const editBtn = row.querySelector('button.edit_equipement');
    if (!editBtn) return;

    // Reconstruire l'onclick avec les nouvelles valeurs
    const onclick = `editEquipement(
        '${escapeQuotes(equipment.id)}',
        '${escapeQuotes(equipment.libelleEquipement)}',
        '${escapeQuotes(equipment.visite)}',
        '${escapeQuotes(equipment.raisonSociale)}',
        '${escapeQuotes(equipment.modeleNacelle)}',
        '${escapeQuotes(equipment.hauteurNacelle)}',
        '${escapeQuotes(equipment.ifExistDB)}',
        '${escapeQuotes(equipment.signatureTech)}',
        '${escapeQuotes(equipment.trigrammeTech)}',
        '${escapeQuotes(equipment.anomalies)}',
        '${escapeQuotes(equipment.idContact)}',
        '${escapeQuotes(equipment.codeSociete)}',
        '${escapeQuotes(equipment.codeAgence)}',
        '${escapeQuotes(equipment.numeroEquipement)}',
        '${escapeQuotes(equipment.modeFonctionnement)}',
        '${escapeQuotes(equipment.repereSiteClient)}',
        '${escapeQuotes(equipment.miseEnService)}',
        '${escapeQuotes(equipment.numeroDeSerie)}',
        '${escapeQuotes(equipment.marque)}',
        '${escapeQuotes(equipment.hauteur)}',
        '${escapeQuotes(equipment.largeur)}',
        '${escapeQuotes(equipment.longueur)}',
        '${escapeQuotes(equipment.plaqueSignaletique)}',
        '${escapeQuotes(equipment.etat)}',
        '${escapeQuotes(equipment.derniereVisite)}',
        '${escapeQuotes(equipment.statutDeMaintenance)}',
        '${escapeQuotes(equipment.presenceCarnetEntretien)}',
        '${escapeQuotes(equipment.statutConformite)}'
    )`;
    
    editBtn.setAttribute('onclick', onclick);
}

/**
 * Échappe les quotes pour éviter les erreurs JS
 */
function escapeQuotes(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

/**
 * Retourne le libellé de l'état selon le code
 */
function getEtatLabel(etat) {
    const labels = {
        'A': 'Bon état de fonctionnement le jour de la visite',
        'B': 'Travaux préventifs',
        'C': 'Travaux curatifs',
        'D': 'Equipement inaccessible le jour de la visite',
        'E': 'Equipement à l\'arrêt le jour de la visite',
        'F': 'Equipement mis à l\'arrêt lors de l\'intervention',
        'G': 'Equipement non présent sur site'
    };
    return labels[etat] || etat || '';
}

/**
 * Met à jour la cellule statut avec la pastille colorée
 */
function updateStatutCell(cell, etat) {
    const baseUrl = 'https://www.backend-kizeo.somafi-group.fr/public/img/logos/';
    let imgSrc = '';
    let caption = '';
    
    switch(etat) {
        case 'A':
            imgSrc = baseUrl + 'vert.png';
            caption = 'Vert';
            break;
        case 'B':
            imgSrc = baseUrl + 'orange.png';
            caption = 'Orange';
            break;
        case 'C':
        case 'E':
        case 'F':
            imgSrc = baseUrl + 'rouge.png';
            caption = 'Rouge';
            break;
        case 'D':
        case 'G':
            imgSrc = baseUrl + 'noir.png';
            caption = 'Noir';
            break;
        default:
            cell.textContent = etat || '';
            return;
    }
    
    cell.innerHTML = `<figure><img src="${imgSrc}" alt="etat-equipement" /><figcaption>${caption}</figcaption></figure>`;
}

/**
 * Animation de surbrillance sur la ligne mise à jour
 */
function highlightRow(row) {
    row.style.transition = 'background-color 0.3s ease';
    row.style.backgroundColor = '#d4edda'; // Vert clair Bootstrap success
    
    setTimeout(() => {
        row.style.backgroundColor = '';
        setTimeout(() => {
            row.style.transition = '';
        }, 300);
    }, 2000);
}

/**
 * Affiche une notification à l'utilisateur
 */
function showNotification(type, message) {
    // Vérifier si Toastr est disponible
    if (typeof toastr !== 'undefined') {
        if (type === 'success') {
            toastr.success(message);
        } else {
            toastr.error(message);
        }
        return;
    }
    
    // Sinon, créer une notification Bootstrap
    const alertContainer = document.getElementById('flash-messages') || createAlertContainer();
    
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.setAttribute('role', 'alert');
    alert.innerHTML = `
        <i class="fa-solid ${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-disparition après 5 secondes
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, 5000);
}

/**
 * Crée un conteneur pour les alertes s'il n'existe pas
 */
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'flash-messages';
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    document.body.appendChild(container);
    return container;
}
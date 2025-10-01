document.addEventListener('DOMContentLoaded', function() {
    const pdfButton = document.getElementById('pdf-button');
    const radioButtons = document.querySelectorAll('input[name="photoOption"]');
    
    if (!pdfButton) {
        console.error('Bouton PDF non trouvé');
        return;
    }
    
    const baseUrl = pdfButton.dataset.baseUrl;
    console.log('Script chargé - Base URL:', baseUrl);
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('Radio changé:', this.value);
            const url = new URL(baseUrl, window.location.origin);
            url.searchParams.set('withPhotos', this.value);
            pdfButton.href = url.pathname + url.search;
            console.log('Nouvelle URL:', pdfButton.href);
        });
    });
});
window.addEventListener('load', function() {
    console.log('🔍 Recherche du bouton PDF...');
    
    const pdfButton = document.getElementById('pdf-button');
    const radioButtons = document.querySelectorAll('input[name="photoOption"]');
    
    if (!pdfButton) {
        console.error('❌ Bouton PDF non trouvé');
        return;
    }
    
    console.log('✅ Bouton PDF trouvé:', pdfButton);
    
    const baseUrl = pdfButton.dataset.baseUrl;
    console.log('📍 Base URL:', baseUrl);
    
    if (!baseUrl) {
        console.error('❌ data-base-url manquant sur le bouton');
        return;
    }
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('📻 Radio changé:', this.value);
            const url = new URL(baseUrl, window.location.origin);
            url.searchParams.set('withPhotos', this.value);
            pdfButton.href = url.pathname + url.search;
            console.log('🔗 Nouvelle URL:', pdfButton.href);
        });
    });
    
    console.log('✅ Script photo-option initialisé');
});
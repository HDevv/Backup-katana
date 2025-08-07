document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les dropdowns Semantic UI
    $('.ui.dropdown').dropdown();

    // Gérer le tri des colonnes
    const table = document.querySelector('.ui.sortable.table');
    if (!table) return;

    const headers = table.querySelectorAll('th a');
    headers.forEach(header => {
        header.addEventListener('click', function(e) {
            e.preventDefault();
            const url = new URL(this.href);
            const sort = url.searchParams.get('sort');
            const direction = url.searchParams.get('direction');
            
            // Mettre à jour l'URL avec les nouveaux paramètres de tri
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sort', sort);
            currentUrl.searchParams.set('direction', direction);
            
            // Conserver les paramètres de filtrage existants
            const form = document.querySelector('form');
            if (form) {
                const formData = new FormData(form);
                for (let [key, value] of formData.entries()) {
                    if (value) {
                        currentUrl.searchParams.set(key, value);
                    }
                }
            }
            
            // Rediriger vers la nouvelle URL
            window.location.href = currentUrl.toString();
        });
    });
});

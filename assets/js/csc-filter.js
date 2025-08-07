document.addEventListener('DOMContentLoaded', function() {
    // Initialize Semantic UI dropdowns
    $('.ui.dropdown').dropdown();

    // Handle sorting when dropdown values change
    $('.csc-sort, .csc-direction').on('change', function() {
        $(this).closest('form').submit();
    });

    // Handle column header clicks
    $('.sortable').on('click', function() {
        const sort = $(this).data('sort');
        const currentSort = $('.csc-sort').val();
        const currentDirection = $('.csc-direction').val();
        
        // Toggle direction if clicking the same column
        if (sort === currentSort) {
            $('.csc-direction').dropdown('set selected', currentDirection === 'asc' ? 'desc' : 'asc');
        } else {
            $('.csc-sort').dropdown('set selected', sort);
            $('.csc-direction').dropdown('set selected', 'asc');
        }
        
        $(this).closest('form').submit();
    });

    // Update active sort column visual state
    function updateSortVisuals() {
        const currentSort = $('.csc-sort').val();
        const currentDirection = $('.csc-direction').val();
        
        // Reset all headers
        $('.sortable').css('--sort-arrow', '↕');
        
        if (currentSort) {
            const th = $(`.sortable[data-sort="${currentSort}"]`);
            th.css('--sort-arrow', currentDirection === 'asc' ? '↑' : '↓');
        }
    }

    // Update visuals on page load and when form changes
    updateSortVisuals();
    $('.csc-sort, .csc-direction').on('change', updateSortVisuals);
});


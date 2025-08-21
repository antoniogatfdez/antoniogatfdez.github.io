/**
 * Clase reutilizable para barras de búsqueda en tablas
 */
class TableSearchBar {
    constructor(config) {
        this.searchInputId = config.searchInputId;
        this.clearBtnId = config.clearBtnId;
        this.searchInfoId = config.searchInfoId;
        this.searchResultsId = config.searchResultsId;
        this.totalCountId = config.totalCountId;
        this.tableSelector = config.tableSelector || 'tbody tr';
        this.noResultsId = config.noResultsId || 'noResultsRow';
        this.columnsCount = config.columnsCount || 6;
        this.placeholder = config.placeholder || 'Sin resultados encontrados';
        
        this.init();
    }
    
    init() {
        this.searchInput = document.getElementById(this.searchInputId);
        this.clearBtn = document.getElementById(this.clearBtnId);
        this.searchInfo = document.getElementById(this.searchInfoId);
        this.searchResults = document.getElementById(this.searchResultsId);
        this.totalCount = document.getElementById(this.totalCountId);
        this.tableRows = document.querySelectorAll(this.tableSelector);
        this.totalItems = this.tableRows.length;
        
        this.setupEventListeners();
        this.searchInput.focus();
        
        // Hacer disponible globalmente para botones inline
        window.clearTableSearch = () => this.clearSearch();
    }
    
    setupEventListeners() {
        this.searchInput.addEventListener('input', () => this.filterTable());
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.clearSearch();
            }
        });
        
        if (this.clearBtn) {
            this.clearBtn.addEventListener('click', () => this.clearSearch());
        }
    }
    
    filterTable() {
        const searchTerm = this.searchInput.value.toLowerCase().trim();
        let visibleCount = 0;
        
        this.tableRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let matchFound = false;
            
            cells.forEach(cell => {
                const cellText = cell.textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    matchFound = true;
                }
            });
            
            if (matchFound || searchTerm === '') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        this.updateSearchInfo(searchTerm, visibleCount);
        this.handleNoResults(searchTerm, visibleCount);
    }
    
    updateSearchInfo(searchTerm, visibleCount) {
        if (searchTerm === '') {
            if (this.searchInfo) this.searchInfo.style.display = 'none';
            if (this.clearBtn) this.clearBtn.style.display = 'none';
            if (this.totalCount) this.totalCount.textContent = this.totalItems;
        } else {
            if (this.searchInfo) this.searchInfo.style.display = 'block';
            if (this.clearBtn) this.clearBtn.style.display = 'inline-block';
            if (this.searchResults) this.searchResults.textContent = visibleCount;
            if (this.totalCount) this.totalCount.textContent = visibleCount;
        }
    }
    
    handleNoResults(searchTerm, visibleCount) {
        const tbody = document.querySelector('tbody');
        let noResultsRow = document.getElementById(this.noResultsId);
        
        if (visibleCount === 0 && searchTerm !== '') {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.id = this.noResultsId;
                noResultsRow.innerHTML = `
                    <td colspan="${this.columnsCount}" class="text-center p-4">
                        <i class="fas fa-search" style="font-size: 2rem; color: var(--medium-gray); margin-bottom: 10px;"></i>
                        <h5>No se encontraron resultados</h5>
                        <p class="text-muted">No hay elementos que coincidan con "${searchTerm}"</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="clearTableSearch()">
                            <i class="fas fa-times"></i> Limpiar búsqueda
                        </button>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
            }
            noResultsRow.style.display = '';
        } else if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }
    
    clearSearch() {
        this.searchInput.value = '';
        this.filterTable();
        this.searchInput.focus();
    }
}

/**
 * Función helper para inicializar búsqueda básica
 * Soporta ambos formatos: nuevo (con configuración) y antiguo (con parámetros)
 */
function initBasicTableSearch(tableIdOrConfig, config = null, columnsCount = null) {
    // Si el primer parámetro es un objeto, usar nuevo formato
    if (typeof tableIdOrConfig === 'object' && config === null) {
        const tableId = Object.keys(tableIdOrConfig)[0];
        return new TableSearchBar(tableIdOrConfig[tableId]);
    }
    
    // Si el primer parámetro es string y el segundo es objeto, usar nuevo formato
    if (typeof tableIdOrConfig === 'string' && typeof config === 'object' && config !== null) {
        return new TableSearchBar({
            ...config,
            tableId: tableIdOrConfig
        });
    }
    
    // Formato antiguo para compatibilidad (será deprecado)
    if (typeof tableIdOrConfig === 'string' && config === null) {
        console.warn('Formato antiguo de initBasicTableSearch está deprecado. Usar nuevo formato con configuración.');
        return new TableSearchBar({
            searchInputId: tableIdOrConfig,
            clearBtnId: tableIdOrConfig.replace('Input', 'Clear'),
            searchInfoId: tableIdOrConfig.replace('Input', 'Info'), 
            searchResultsId: tableIdOrConfig.replace('Input', 'Results'),
            totalCountId: 'total-count',
            tableSelector: config || 'tbody tr',
            columnsCount: columnsCount || 6
        });
    }
}

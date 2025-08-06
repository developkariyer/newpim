'use strict';

class CatalogSystem {
    constructor() {
        this.config = this.loadConfig();
        this.state = {
            products: [...this.config.initialProducts],
            totalProducts: this.config.totalProducts,
            hasMore: this.config.hasMore,
            currentOffset: this.config.limit,
            isLoading: false,
            currentSearch: this.config.currentSearch,
            currentCategory: this.config.currentCategory,
            currentIwasku: '',
            currentAsin: '',
            currentBrand: '',
            currentEan: '',
            isAdvancedOpen: false,
            expandedProducts: new Set(),
            debounceTimer: null
        };
        this.elements = this.initializeElements();
        this.bindEvents();
        this.renderInitialProducts();
        this.updateUI();
        console.log('✅ Catalog System initialized successfully');
    }
    
    loadConfig() {
        try {
            const configElement = document.getElementById('catalogData');
            return JSON.parse(configElement.textContent);
        } catch (error) {
            console.error('❌ Failed to load catalog config:', error);
            return {
                initialProducts: [],
                totalProducts: 0,
                hasMore: false,
                currentCategory: null,
                currentSearch: '',
                limit: 20,
                apiEndpoints: {}
            };
        }
    }

    initializeElements() {
        return {
            searchInput: document.getElementById('searchInput'),
            categoryFilter: document.getElementById('categoryFilter'),
            clearFilters: document.getElementById('clearFilters'),
            exportExcel: document.getElementById('exportExcel'),
            productsGrid: document.getElementById('productsGrid'),
            loadMoreBtn: document.getElementById('loadMoreBtn'),
            loadMoreContainer: document.getElementById('loadMoreContainer'),
            loadingProducts: document.getElementById('loadingProducts'),
            emptyState: document.getElementById('emptyState'),
            totalCount: document.getElementById('totalCount'),
            filterInfo: document.getElementById('filterInfo'),
            loadingOverlay: document.getElementById('loadingOverlay'),
            advancedToggle: document.getElementById('advancedToggle'),
            advancedPanel: document.getElementById('advancedPanel'),
            iwaskuFilter: document.getElementById('iwaskuFilter'),
            asinFilter: document.getElementById('asinFilter'),
            brandFilter: document.getElementById('brandFilter'),
            eanFilter: document.getElementById('eanFilter'),
            clearAdvanced: document.getElementById('clearAdvanced'),
        };
    }

    bindEvents() {
        // Search with debounce
        if (this.elements.searchInput) {
            this.elements.searchInput.addEventListener('input', (e) => {
                this.debounceSearch(e.target.value);
            });

            this.elements.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.performSearch(e.target.value);
                }
            });
        }

        // Category filter
        if (this.elements.categoryFilter) {
            this.elements.categoryFilter.addEventListener('change', (e) => {
                this.state.currentCategory = e.target.value;
                this.resetAndReload();
            });
        }

        // Clear filters
        if (this.elements.clearFilters) {
            this.elements.clearFilters.addEventListener('click', () => {
                this.clearAllFilters();
            });
        }

        // Export Excel
        if (this.elements.exportExcel) {
            this.elements.exportExcel.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportToExcel();
            });
        }

        // Load more
        if (this.elements.loadMoreBtn) {
            this.elements.loadMoreBtn.addEventListener('click', () => {
                this.loadMoreProducts();
            });
        }

        // Product row clicks (for expanding variants)
        if (this.elements.productsGrid) {
            this.elements.productsGrid.addEventListener('click', (e) => {
                const productRow = e.target.closest('.product-row');
                if (productRow && !e.target.closest('.variants-section')) {
                    this.toggleProductExpansion(productRow);
                }
            });
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.clearAllFilters();
            }
        });

        // Infinite scroll
        window.addEventListener('scroll', this.throttle(() => {
            if (this.shouldLoadMore()) {
                this.loadMoreProducts();
            }
        }, 200));

        // Product row clicks
        if (this.elements.productsGrid) {
            this.elements.productsGrid.addEventListener('click', (e) => {
                if (e.target.closest('.variant-set-toggle')) {
                    e.stopPropagation();
                    e.preventDefault();
                    const toggleBtn = e.target.closest('.variant-set-toggle');
                    const variantId = toggleBtn.dataset.variantId;
                    this.toggleVariantSetProducts(variantId, toggleBtn);
                    return false;
                }
                if (e.target.closest('.edit-btn')) {
                    e.stopPropagation(); 
                    e.preventDefault();
                    const editBtn = e.target.closest('.edit-btn');
                    const productId = editBtn.dataset.productId;
                    this.editProduct(productId);
                    return false; 
                }
                if (e.target.closest('.marketplace-btn')) {
                    e.stopPropagation();
                    e.preventDefault();
                    const marketplaceBtn = e.target.closest('.marketplace-btn');
                    const sku = marketplaceBtn.dataset.sku;
                    this.showMarketplaceListings(sku);
                    return false;
                }
                const productRow = e.target.closest('.product-row');
                if (productRow && 
                    !e.target.closest('.variants-section') && 
                    !e.target.closest('.edit-btn') &&
                    !e.target.closest('.product-actions') &&
                    !e.target.closest('.variant-set-toggle') &&
                    !e.target.closest('.variant-set-products-section')) {
                    this.toggleProductExpansion(productRow);
                }
            });
        }

        // NEW: Advanced search events
        if (this.elements.advancedToggle) {
            this.elements.advancedToggle.addEventListener('click', () => {
                this.toggleAdvancedPanel();
            });
        }

        if (this.elements.iwaskuFilter) {
            this.elements.iwaskuFilter.addEventListener('input', (e) => {
                this.state.currentIwasku = e.target.value.trim();
                this.debounceSearch();
            });
        }

        if (this.elements.asinFilter) {
            this.elements.asinFilter.addEventListener('input', (e) => {
                this.state.currentAsin = e.target.value.trim();
                this.debounceSearch();
            });
        }

        if (this.elements.brandFilter) {
            this.elements.brandFilter.addEventListener('input', (e) => {
                this.state.currentBrand = e.target.value.trim();
                this.debounceSearch();
            });
        }

        if (this.elements.eanFilter) {
            this.elements.eanFilter.addEventListener('input', (e) => {
                this.state.currentEan = e.target.value.trim();
                this.debounceSearch();
            });
        }

        if (this.elements.clearAdvanced) {
            this.elements.clearAdvanced.addEventListener('click', () => {
                this.clearAdvancedFilters();
            });
        }

    }

    debounceSearch(query = null) {
        clearTimeout(this.state.debounceTimer);
        this.state.debounceTimer = setTimeout(() => {
            if (query !== null) {
                this.performSearch(query);
            } else {
                this.resetAndReload();
            }
        }, 500);
    }

    async performSearch(query = '') {
        try {
            this.state.currentSearch = (query || '').toString().trim();
            this.resetAndReload();
        } catch (error) {
            console.error('Search failed:', error);
            this.showError('Arama işlemi başarısız oldu.');
        }
    }

    async resetAndReload() {
        try {
            this.state.products = [];
            this.state.currentOffset = 0;
            this.state.hasMore = true;
            this.state.expandedProducts.clear();
            this.showLoading();
            await this.loadProducts(true);
            this.hideLoading();
            this.updateUI();
            this.updateURL();
        } catch (error) {
            console.error('Reset and reload failed:', error);
            this.hideLoading();
            this.showError('Ürünler yüklenirken hata oluştu.');
        }
    }

    // Edit Product Method
    editProduct(productId) {
        try {
            this.showLoading();
            const editUrl = `/product?edit=${productId}`;
            window.location.href = editUrl;
        } catch (error) {
            console.error('❌ Edit product failed:', error);
            this.hideLoading();
            this.showError('Ürün düzenleme sayfasına yönlendirilemedi.');
        }
    }

    async showMarketplaceListings(sku) {
        try {
            console.log('🔍 Marketplace listings requested for SKU:', sku);
            const modalElement = document.getElementById('marketplaceModal');
            if (!modalElement) {
                console.error('🚨 Marketplace modal not found in DOM!');
                this.showError('Pazaryeri modal\'ı bulunamadı.');
                return;
            }
            const skuElement = document.getElementById('marketplaceSku');
            if (skuElement) {
                skuElement.textContent = `(SKU: ${sku})`;
            }
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            this.showMarketplaceLoading();
            const url = `/catalog/api/marketplace-listings/${encodeURIComponent(sku)}`;
            console.log('🌐 Fetching URL:', url);
            const response = await fetch(url);
            console.log('📡 Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            console.log('📦 API Response:', data);
            if (data.success) {
                console.log('✅ Listings received:', data.listings);
                this.renderMarketplaceListings(data.listings);
            } else {
                console.error('❌ API returned error:', data.message);
                throw new Error(data.message || 'API error');
            }
        } catch (error) {
            console.error('💥 Marketplace listings failed:', error);
            this.showMarketplaceError();
        }
    }
    
    showMarketplaceLoading() {
        const loadingSpinner = document.getElementById('marketplaceLoadingSpinner');
        const content = document.getElementById('marketplaceContent');
        const error = document.getElementById('marketplaceError');
        
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        if (content) content.style.display = 'none';
        if (error) error.style.display = 'none';
    }
    
    showMarketplaceError() {
        const loadingSpinner = document.getElementById('marketplaceLoadingSpinner');
        const content = document.getElementById('marketplaceContent');
        const error = document.getElementById('marketplaceError');
        
        if (loadingSpinner) loadingSpinner.style.display = 'none';
        if (content) content.style.display = 'none';
        if (error) error.style.display = 'block';
    }
    
    renderMarketplaceListings(listings) {
        const container = document.getElementById('marketplaceListings');
        const emptyState = document.getElementById('marketplaceEmpty');
        const loadingSpinner = document.getElementById('marketplaceLoadingSpinner');
        const content = document.getElementById('marketplaceContent');
        const error = document.getElementById('marketplaceError');
        
        // Hide loading
        if (loadingSpinner) loadingSpinner.style.display = 'none';
        if (content) content.style.display = 'block';
        if (error) error.style.display = 'none';
        
        if (!container || !emptyState) {
            console.error('🚨 Marketplace modal elements not found!');
            return;
        }
        
        if (!listings || listings.length === 0) {
            container.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }
        
        emptyState.style.display = 'none';
        
        const listingsHtml = listings.map(listing => {
            const statusColor = this.getMarketplaceStatusColor(listing.status);
            const marketplaceIcon = this.getMarketplaceIcon(listing.marketplace_key);
            const marketplaceName = this.getMarketplaceName(listing.marketplace_key);
            const statusLabel = this.getStatusLabel(listing.status);
            const formattedPrice = this.formatPrice(listing.marketplace_price, listing.marketplace_currency);
            const lastUpdated = listing.last_updated ? new Date(listing.last_updated).toLocaleString('tr-TR') : '';
            
            return `
                <div class="marketplace-listing-card mb-3 border rounded p-3">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <span class="marketplace-icon me-2">${marketplaceIcon}</span>
                                <div>
                                    <h6 class="mb-0">${this.escapeHtml(marketplaceName)}</h6>
                                    <small class="text-muted">SKU: ${this.escapeHtml(listing.marketplace_sku)}</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="text-center">
                                <span class="badge" style="background-color: ${statusColor};">
                                    ${this.escapeHtml(statusLabel)}
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong class="text-success">${this.escapeHtml(formattedPrice)}</strong>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="text-center">
                                <span class="badge ${listing.marketplace_stock > 0 ? 'bg-success' : 'bg-warning'}">
                                    ${listing.marketplace_stock} adet
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="text-end">
                                ${listing.marketplace_product_url ? `
                                    <a href="${this.escapeHtml(listing.marketplace_product_url)}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-external-link-alt"></i> Görüntüle
                                    </a>
                                ` : ''}
                                
                                <div class="small text-muted mt-1">
                                    ${lastUpdated ? `Son güncelleme: ${lastUpdated}` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = `
            <div class="marketplace-listings">
                <div class="mb-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Toplam ${listings.length} pazaryerinde listeleniyor
                    </small>
                </div>
                ${listingsHtml}
            </div>
        `;
    }
    
    getMarketplaceStatusColor(status) {
        const statusStr = String(status);
        const colors = {
            '0': '#6c757d',    
            '1': '#28a745',    
            'active': '#28a745',
            'inactive': '#6c757d', 
            'pending': '#ffc107',
            'error': '#dc3545',
            'out_of_stock': '#fd7e14'
        };
        return colors[statusStr] || '#6c757d';
    }
    
    getMarketplaceIcon(marketplaceKey) {
        const icons = {
            'amazon_tr': '🛒',
            'amazon_de': '🛒', 
            'amazon_us': '🛒',
            'trendyol': '🛍️',
            'hepsiburada': '🏪',
            'n11': '🏬',
            'gittigidiyor': '🛍️',
            'ciceksepeti': '🌸'
        };
        return icons[marketplaceKey] || '🏪';
    }
    
    getMarketplaceName(marketplaceKey) {
        const names = {
            'amazon_tr': 'Amazon Türkiye',
            'amazon_de': 'Amazon Almanya',
            'amazon_us': 'Amazon ABD',
            'trendyol': 'Trendyol',
            'hepsiburada': 'Hepsiburada',
            'n11': 'N11',
            'gittigidiyor': 'GittiGidiyor',
            'ciceksepeti': 'ÇiçekSepeti'
        };
        return names[marketplaceKey] || marketplaceKey.charAt(0).toUpperCase() + marketplaceKey.slice(1);
    }
    
    getStatusLabel(status) {
        if (status === null || status === undefined) return 'Bilinmiyor';
        const statusStr = String(status);
        const labels = {
            '0': 'Pasif',
            '1': 'Aktif',
            'active': 'Aktif',
            'inactive': 'Pasif',
            'pending': 'Beklemede',
            'error': 'Hata',
            'out_of_stock': 'Stok Yok'
        };
        
        return labels[statusStr] || `Durum: ${statusStr}`;
    }
    
    formatPrice(price, currency) {
        if (!price) return '-';
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: currency || 'TRY'
        }).format(parseFloat(price));
    }

    toggleAdvancedPanel() {
        this.state.isAdvancedOpen = !this.state.isAdvancedOpen;
        if (this.state.isAdvancedOpen) {
            this.elements.advancedPanel.style.display = 'block';
            this.elements.advancedToggle.textContent = '⚙️ Gizle';
        } else {
            this.elements.advancedPanel.style.display = 'none';
            this.elements.advancedToggle.textContent = '⚙️ Gelişmiş';
        }
    }

    clearAdvancedFilters() {
        this.state.currentAsin = '';
        this.state.currentBrand = '';
        this.state.currentEan = '';
        this.state.currentIwasku = '';
        if (this.elements.iwaskuFilter) this.elements.iwaskuFilter.value = '';
        if (this.elements.asinFilter) this.elements.asinFilter.value = '';
        if (this.elements.brandFilter) this.elements.brandFilter.value = '';
        if (this.elements.eanFilter) this.elements.eanFilter.value = '';
        this.resetAndReload();
    }

    async loadProducts(isReset = false) {
        if (this.state.isLoading && !isReset) return;

        try {
            this.state.isLoading = true;
            
            if (!isReset) {
                this.showLoadingProducts();
            }

            const params = new URLSearchParams({
                limit: this.config.limit,
                offset: isReset ? 0 : this.state.currentOffset
            });

            if (this.state.currentSearch) {
                params.append('search', this.state.currentSearch);
            }

            if (this.state.currentCategory) {
                params.append('category', this.state.currentCategory);
            }

            if (this.state.currentIwasku) {
                params.append('iwasku', this.state.currentIwasku);
            }

            if (this.state.currentAsin) {
                params.append('asin', this.state.currentAsin);
            }

            if (this.state.currentBrand) {
                params.append('brand', this.state.currentBrand);
            }

            if (this.state.currentEan) {
                params.append('ean', this.state.currentEan);
            }

            const response = await fetch(`${this.config.apiEndpoints.products}?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                if (isReset) {
                    this.state.products = data.products;
                    this.state.currentOffset = data.limit;
                } else {
                    this.state.products.push(...data.products);
                    this.state.currentOffset += data.limit;
                }

                this.state.totalProducts = data.total;
                this.state.hasMore = data.hasMore;

                this.renderProducts(isReset);
            } else {
                throw new Error(data.message || 'API error');
            }

        } catch (error) {
            console.error('Load products failed:', error);
            this.showError('Ürünler yüklenemedi. Lütfen tekrar deneyin.');
        } finally {
            this.state.isLoading = false;
            this.hideLoadingProducts();
        }
    }

    async loadMoreProducts() {
        if (!this.state.hasMore || this.state.isLoading) return;
        await this.loadProducts(false);
        this.updateUI();
    }

    renderInitialProducts() {
        if (this.state.products.length > 0) {
            this.renderProducts(true);
        } else {
            this.showEmptyState();
        }
    }

    renderProducts(isReset = false) {
        if (!this.elements.productsGrid) return;

        if (isReset) {
            this.elements.productsGrid.innerHTML = '';
        }

        if (this.state.products.length === 0) {
            this.showEmptyState();
            return;
        }

        const startIndex = isReset ? 0 : this.elements.productsGrid.children.length;
        const productsToRender = this.state.products.slice(startIndex);

        const fragment = document.createDocumentFragment();

        productsToRender.forEach(product => {
            const productRow = this.createProductRow(product);
            fragment.appendChild(productRow);
        });

        this.elements.productsGrid.appendChild(fragment);
        this.hideEmptyState();
    }

    createProductRow(product) {
        const row = document.createElement('div');
        row.className = 'product-row';
        row.dataset.productId = product.id;
        row.setAttribute('tabindex', '0');
        row.setAttribute('role', 'button');
        row.setAttribute('aria-expanded', 'false');
        const hasVariants = product.hasVariants;
        const variantBadge = hasVariants 
            ? `<span class="variant-badge">✅ ${product.variantCount} Varyant</span>`
            : `<span class="no-variant-badge">➖ Varyant Yok</span>`;
        row.innerHTML = `
            <div class="product-content">
                <div class="product-image">
                    ${product.imageUrl 
                        ? `<img src="${this.escapeHtml(product.imageUrl)}" alt="${this.escapeHtml(product.name)}" loading="lazy">` 
                        : '<div class="no-image">📷</div>'
                    }
                </div>                
                <div class="product-info">
                    <h3 class="product-name">${this.escapeHtml(product.name)}</h3>
                    <div class="product-identifier">${this.escapeHtml(product.productIdentifier)}</div>
                    ${product.category ? `<span class="product-category">${this.escapeHtml(product.category.displayName)}</span>` : ''}
                    ${product.description ? `<p class="product-description">${this.escapeHtml(product.description)}</p>` : ''}
                </div>                
                <div class="product-meta">
                    <div class="product-actions">
                        <button class="edit-btn" data-product-id="${product.id}" data-action="edit" title="Ürünü Düzenle">
                            <i class="fas fa-edit"></i>
                            <span>Düzenle</span>
                        </button>
                        ${variantBadge}
                        ${(hasVariants) ? '<span class="expand-icon" data-action="expand">▼</span>' : ''}
                    </div>
                </div>
            </div>            
            ${hasVariants ? this.createVariantsSection(product.variants) : ''}
        `;
        return row;
    }

    toggleVariantSetProducts(variantId, toggleBtn) {
        const setSection = document.getElementById(`variant-set-${variantId}`);
        const toggleIcon = toggleBtn.querySelector('.toggle-icon');
        if (!setSection) return;
        const isVisible = setSection.style.display !== 'none';
        if (isVisible) {
            setSection.style.display = 'none';
            toggleIcon.textContent = '▼';
            toggleBtn.title = 'Set İçeriğini Göster';
            toggleBtn.setAttribute('data-expanded', 'false');
            console.log(`Set products hidden for variant ${variantId}`);
        } else {
            setSection.style.display = 'block';
            toggleIcon.textContent = '▲';
            toggleBtn.title = 'Set İçeriğini Gizle';
            toggleBtn.setAttribute('data-expanded', 'true');
            console.log(`Set products shown for variant ${variantId}`);
        }
    }

    createVariantsSection(variants) {
        if (!variants || variants.length === 0) return '';
        const uniqueVariants = [];
        const seenVariantIds = new Set();
        variants.forEach(variant => {
            if (!seenVariantIds.has(variant.id)) {
                uniqueVariants.push(variant);
                seenVariantIds.add(variant.id);
            }
            if (variant.bundleProducts && variant.bundleProducts.length > 0) {
                console.log(`📦 Variant ${variant.id} bundle products:`, variant.bundleProducts);
            }
        });
        const sortedVariants = [...uniqueVariants].sort((a, b) => {
            const sizeA = a.variationSize || '';
            const sizeB = b.variationSize || '';
            const sizeOrder = {
                'XS': 1, 'S': 2, 'M': 3, 'L': 4, 'XL': 5, '2XL': 6, '3XL': 7, '4XL': 8, '5XL': 9, '6XL': 10
            };
            const orderA = sizeOrder[sizeA.toUpperCase()] || 999;
            const orderB = sizeOrder[sizeB.toUpperCase()] || 999;
            if (orderA === 999 && orderB === 999) {
                return sizeA.localeCompare(sizeB, 'tr');
            }
            return orderA - orderB;
        });
        const variantRows = sortedVariants.map(variant => {
            const fields = [];
            if (variant.iwasku) {
                fields.push(`
                    <div class="variant-field iwasku">
                        <span class="variant-field-label">🏷️ IWASKU</span>
                        <span class="variant-field-value">${this.escapeHtml(variant.iwasku)}</span>
                    </div>
                `);
            }
            if (variant.variationSize) {
                fields.push(`
                    <div class="variant-field size">
                        <span class="variant-field-label">📏 Beden</span>
                        <span class="variant-field-value">${this.escapeHtml(variant.variationSize)}</span>
                    </div>
                `);
            }
            if (variant.color?.name) {
                fields.push(`
                    <div class="variant-field color">
                        <span class="variant-field-label">🎨 Renk</span>
                        <span class="variant-field-value">${this.escapeHtml(variant.color.name)}</span>
                    </div>
                `);
            }
            if (variant.eans && variant.eans.length > 0) {
                fields.push(`
                    <div class="variant-field eans">
                        <span class="variant-field-label">📊 EAN Kodları</span>
                        <div class="variant-eans-inline">
                            ${variant.eans.map(ean => `<span class="ean-badge-inline">${this.escapeHtml(ean)}</span>`).join('')}
                        </div>
                    </div>
                `);
            }
            if (variant.customField) {
                fields.push(`
                    <div class="variant-field">
                        <span class="variant-field-label">⚙️ ${this.escapeHtml(variant.customFieldTitle)}</span>
                        <span class="variant-field-value">${this.escapeHtml(variant.customField)}</span>
                    </div>
                `);
            }
            if (variant.asins && variant.asins.length > 0) {
                fields.push(`
                    <div class="variant-field asins">
                        <span class="variant-field-label">🛒 ASIN/FNSKU</span>
                        <div class="variant-asins-container">
                            ${variant.asins.map(asinObj => `
                                <div class="asin-group">
                                    <div class="asin-code">ASIN: <span class="asin-badge">${this.escapeHtml(asinObj.asin || '-')}</span></div>
                                    ${asinObj.fnskus && asinObj.fnskus.length > 0 ? `
                                        <div class="fnsku-list">
                                            <span class="fnsku-label">FNSKU:</span>
                                            <div class="fnsku-badges">
                                                ${asinObj.fnskus.map(fnsku => `
                                                    <span class="fnsku-badge">${this.escapeHtml(fnsku)}</span>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `);
            }
            const isPublished = variant.published !== false; 
            const statusClass = isPublished ? 'variant-status-active' : 'variant-status-inactive';
            const statusText = isPublished ? '✨ Aktif' : '❌ Pasif';
            const variantSetSection = (variant.bundleProducts && variant.bundleProducts.length > 0) 
                ? this.createVariantSetProductsSection(variant.bundleProducts, variant.id)
                : '';
            return `
                <div class="variant-row ${isPublished ? '' : 'variant-row-inactive'}">
                    <span class="variant-status ${statusClass}">${statusText}</span>
                    <div class="variant-info">
                        ${fields.join('')}
                    </div>
                    <div class="variant-actions">
                        <div class="variant-name">${this.escapeHtml(variant.name || 'Varyant')}</div>
                        ${variant.createdAt ? `<div class="variant-date">📅 ${this.escapeHtml(variant.createdAt)}</div>` : ''}
                        <div class="variant-buttons mt-2">
                            ${variant.iwasku ? `
                                <button class="marketplace-btn btn btn-sm btn-outline-info" 
                                        data-sku="${this.escapeHtml(variant.iwasku)}"
                                        title="Pazaryeri Bilgilerini Görüntüle">
                                    <i class="fas fa-store"></i> Pazaryeri
                                </button>
                            ` : ''}
                            
                            ${variant.bundleProducts && variant.bundleProducts.length > 0 ? `
                                <button class="variant-set-toggle btn btn-sm btn-outline-secondary" 
                                        data-variant-id="${variant.id}" 
                                        title="Set İçeriğini Göster/Gizle">
                                    📦 ${variant.bundleProducts.length} Set Ürünü
                                    <span class="toggle-icon">▼</span>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    ${variantSetSection}
                </div>
            `;
        }).join('');
        return `
            <div class="variants-section">
                <h4 class="variants-title">
                    Ürün Varyantları
                    <span style="font-weight: 400; color: var(--text-secondary); font-size: 0.85rem;">(${variants.length} adet)</span>
                </h4>
                <div class="variants-table">
                    ${variantRows}
                </div>
            </div>
        `;
    }

    createVariantSetProductsSection(bundleProducts, variantId) {
        console.log('Creating variant set products section with:', bundleProducts);
        if (!bundleProducts || bundleProducts.length === 0) {
            return '';
        }
        const setProductRows = bundleProducts.map(bundleProduct => {
            const fields = [];
            if (bundleProduct.iwasku) {
                fields.push(`
                    <div class="set-item-field iwasku">
                        <span class="set-item-field-label">🏷️ IWASKU</span>
                        <span class="set-item-field-value">${this.escapeHtml(bundleProduct.iwasku)}</span>
                    </div>
                `);
            }
            if (bundleProduct.key) {
                fields.push(`
                    <div class="set-item-field name">
                        <span class="set-item-field-label">📦 Ürün</span>
                        <span class="set-item-field-value">${this.escapeHtml(bundleProduct.key)}</span>
                    </div>
                `);
            }
            if (bundleProduct.size) {
                fields.push(`
                    <div class="set-item-field beden">
                        <span class="set-item-field-label">📏 Beden</span>
                        <span class="set-item-field-value">${this.escapeHtml(bundleProduct.size)}</span>
                    </div>
                `);
            }
            if (bundleProduct.color) {
                fields.push(`
                    <div class="set-item-field renk">
                        <span class="set-item-field-label">🎨 Renk</span>
                        <span class="set-item-field-value">${this.escapeHtml(bundleProduct.color)}</span>
                    </div>
                `);
            }
            if (bundleProduct.customField) {
                fields.push(`
                    <div class="set-item-field custom">
                        <span class="set-item-field-label">⚙️ Custom</span>
                        <span class="set-item-field-value">${this.escapeHtml(bundleProduct.customField)}</span>
                    </div>
                `);  
            }
            if (bundleProduct.quantity && bundleProduct.quantity > 1) {
                fields.push(`
                    <div class="set-item-field quantity">
                        <span class="set-item-field-label">🔢 Adet</span>
                        <span class="set-item-field-value">${bundleProduct.quantity}</span>
                    </div>
                `);
            }
            if (bundleProduct.identifier) {
                fields.push(`
                    <div class="set-item-field id">
                        <span class="set-item-field-label">🆔 ID</span>
                        <span class="set-item-field-value">${this.escapeHtml(bundleProduct.identifier)}</span>
                    </div>
                `);
            }
            const isPublished = bundleProduct.published !== false;
            const statusClass = isPublished ? 'set-item-status-active' : 'set-item-status-inactive';
            const statusText = isPublished ? '✨ Aktif' : '❌ Pasif';
            return `
                <div class="set-item-row ${isPublished ? '' : 'set-item-row-inactive'}">
                    <span class="set-item-status ${statusClass}">${statusText}</span>
                    
                    <div class="set-item-info">
                        ${fields.join('')}
                    </div>
                    
                    <div class="set-item-actions">
                        <div class="set-item-name">${this.escapeHtml(bundleProduct.key || 'Set Ürünü')}</div>
                    </div>
                </div>
            `;
        }).join('');
        return `
            <div class="set-section" id="variant-set-${variantId}" style="display: none;">
                <h5 class="set-title">
                    Bu Varyantın Set İçeriği
                    <span style="font-weight: 400; color: var(--text-secondary); font-size: 0.8rem;">(${bundleProducts.length} ürün)</span>
                </h5>
                <div class="set-items-table">
                    ${setProductRows}
                </div>
            </div>
        `;
    }

    toggleProductExpansion(productRow) {
        const productId = productRow.dataset.productId;
        const isExpanded = this.state.expandedProducts.has(productId);

        if (isExpanded) {
            this.state.expandedProducts.delete(productId);
            productRow.classList.remove('expanded');
            productRow.setAttribute('aria-expanded', 'false');
        } else {
            this.state.expandedProducts.add(productId);
            productRow.classList.add('expanded');
            productRow.setAttribute('aria-expanded', 'true');
        }
    }

    clearAllFilters() {
        if (this.elements.searchInput) {
            this.elements.searchInput.value = '';
        }
        if (this.elements.categoryFilter) {
            this.elements.categoryFilter.selectedIndex = 0;
        }
        this.state.currentSearch = '';
        this.state.currentCategory = '';
        this.resetAndReload();
        this.clearAdvancedFilters();
    }

    async exportToExcel() {
        try {
            const productCountElement = document.getElementById('exportProductCount');
            if (productCountElement) {
                productCountElement.textContent = `Toplam ${this.state.totalProducts} ürün indirilecek.`;
            }
            const params = new URLSearchParams();
            if (this.state.currentSearch) {
                params.append('search', this.state.currentSearch);
            }
            if (this.state.currentCategory) {
                params.append('category', this.state.currentCategory);
            }
            if (this.state.currentIwasku) {
                params.append('iwasku', this.state.currentIwasku);
            }
            if (this.state.currentAsin) {
                params.append('asin', this.state.currentAsin);
            }
            if (this.state.currentBrand) {
                params.append('brand', this.state.currentBrand);
            }
            if (this.state.currentEan) {
                params.append('ean', this.state.currentEan);
            }
            const url = `${this.config.apiEndpoints.export}?${params}`;
            const confirmBtn = document.getElementById('confirmExportBtn');
            if (confirmBtn) {
                confirmBtn.href = url;
            }
            const modal = new bootstrap.Modal(document.getElementById('exportConfirmModal'));
            modal.show();
        } catch (error) {
            console.error('Excel export preparation failed:', error);
            this.showError('Excel dışa aktarım hazırlığı başarısız oldu.');
        }
    }

    updateUI() {
        this.updateStats();
        this.updateLoadMoreButton();
        this.updateFilterInfo();
    }

    updateStats() {
        if (this.elements.totalCount) {
            this.elements.totalCount.textContent = this.state.totalProducts;
        }
    }

    updateLoadMoreButton() {
        if (!this.elements.loadMoreContainer || !this.elements.loadMoreBtn) return;

        if (this.state.hasMore && this.state.products.length > 0) {
            this.elements.loadMoreContainer.style.display = 'block';
            this.elements.loadMoreBtn.disabled = this.state.isLoading;
            this.elements.loadMoreBtn.textContent = this.state.isLoading 
                ? '📦 Yükleniyor...' 
                : '📦 Daha Fazla Ürün Yükle';
        } else {
            this.elements.loadMoreContainer.style.display = 'none';
        }
    }

    updateFilterInfo() {
        if (!this.elements.filterInfo) return;
        const filters = [];
        if (this.state.currentSearch) {
            filters.push(`arama: "${this.state.currentSearch}"`);
        }
        if (this.state.currentCategory) {
            const categoryOption = this.elements.categoryFilter?.querySelector(`option[value="${this.state.currentCategory}"]`);
            if (categoryOption) {
                filters.push(`kategori: "${categoryOption.textContent.split(' (')[0]}"`);
            }
        }
        if (this.state.currentIwasku) {
            filters.push(`IWASKU: "${this.state.currentIwasku}"`);
        }
        if (this.state.currentAsin) {
            filters.push(`ASIN: "${this.state.currentAsin}"`);
        }

        if (this.state.currentBrand) {
            filters.push(`marka: "${this.state.currentBrand}"`);
        }

        if (this.state.currentEan) {
            filters.push(`EAN: "${this.state.currentEan}"`);
        }

        this.elements.filterInfo.textContent = filters.length > 0 
            ? ` (${filters.join(', ')})` 
            : '';
    }

    updateURL() {
        const params = new URLSearchParams();
        if (this.state.currentSearch) {
            params.append('search', this.state.currentSearch);
        }
        if (this.state.currentCategory) {
            params.append('category', this.state.currentCategory);
        }
        if (this.state.currentIwasku) {
            params.append('iwasku', this.state.currentIwasku);
        }
        if (this.state.currentAsin) {
            params.append('asin', this.state.currentAsin);
        }
        if (this.state.currentBrand) {
            params.append('brand', this.state.currentBrand);
        }
        if (this.state.currentEan) {
            params.append('ean', this.state.currentEan);
        }
        const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newURL);
    }

    shouldLoadMore() {
        if (!this.state.hasMore || this.state.isLoading) return false;
        const scrollPosition = window.innerHeight + window.scrollY;
        const documentHeight = document.documentElement.offsetHeight;
        return scrollPosition >= documentHeight - 1000; // 1000px threshold
    }

    // UI State Management
    showLoading() {
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.style.display = 'flex';
        }
    }

    hideLoading() {
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.style.display = 'none';
        }
    }

    showLoadingProducts() {
        if (this.elements.loadingProducts) {
            this.elements.loadingProducts.style.display = 'block';
        }
    }

    hideLoadingProducts() {
        if (this.elements.loadingProducts) {
            this.elements.loadingProducts.style.display = 'none';
        }
    }

    showEmptyState() {
        if (this.elements.emptyState) {
            this.elements.emptyState.style.display = 'block';
        }
    }

    hideEmptyState() {
        if (this.elements.emptyState) {
            this.elements.emptyState.style.display = 'none';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'warning'}`;
        notification.textContent = message;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '10000';
        notification.style.maxWidth = '400px';
        document.body.appendChild(notification);
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

    // Utility Methods
    escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    try {
        window.catalogSystem = new CatalogSystem();
        console.log('✅ Catalog System ready');
    } catch (error) {
        console.error('❌ Failed to initialize Catalog System:', error);
        
        // Show fallback error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.textContent = 'Katalog sistemi yüklenemedi. Lütfen sayfayı yenileyin.';
        document.querySelector('.container')?.prepend(errorDiv);
    }
});
